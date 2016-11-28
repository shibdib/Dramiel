<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2016  Robert Sardinia
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

// Require the vendor stuff
require_once(__DIR__ . "/vendor/autoload.php");

// Setup logger
use Discord\Discord;
use Discord\Parts\User\Game;
use Discord\WebSockets\Event;
use Discord\WebSockets\WebSocket;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// More memory allowance
ini_set("memory_limit", "1024M");

// Just incase we get launched from somewhere else
chdir(__DIR__);

// Enable garbage collection
gc_enable();

// When the bot started
$startTime = time();

// create a log channel
$logger = new Logger('Dramiel');
$logger->pushHandler(new StreamHandler(__DIR__ . '/log/dramielLog.log', Logger::INFO));
$logger->addInfo('Logger Initiated');

GLOBAL $logger;

// Require the config
if (file_exists("config/config.php")) {
    require_once("config/config.php");
} else {
    $logger->error("config.php not found (you might wanna start by editing and renaming config_new.php)");
    die();
}

// Load the library files (Probably a prettier way to do this that i haven't thought up yet)
foreach (glob(__DIR__ . "/src/lib/*.php") as $lib) {
    require_once($lib);
}

//Startup DB Check
updateDramielDB($logger);

// Init Discord
$discord = new Discord(["token" => $config["bot"]["token"]]);

// Load tick plugins
$pluginDirs = array("src/plugins/onTick/*.php");
$logger->info("Loading background plugins");
$plugins = array();
foreach ($pluginDirs as $dir) {
    foreach (glob($dir) as $plugin) {
        // Only load the plugins we want to load, according to the config
        if (!in_array(str_replace(".php", "", basename($plugin)), $config["enabledPlugins"])) {
            continue;
        }

        require_once($plugin);
        $fileName = str_replace(".php", "", basename($plugin));
        $p = new $fileName();
        $p->init($config, $discord, $logger);
        $pluginsT[] = $p;
    }
}
// Number of plugins loaded
$logger->info("Loaded: " . count($pluginsT) . " background plugins");

if ($config["bot"]["silentMode"] == "false" || !isset($config["bot"]["silentMode"])) {
// Load chat plugins
    $pluginDirs = array("src/plugins/onMessage/*.php", "src/plugins/admin/*.php");
    $adminPlugins = array("setNickname", "getLog", "setGame");
    $logger->addInfo("Loading in chat plugins");
    $plugins = array();
    foreach ($pluginDirs as $dir) {
        foreach (glob($dir) as $plugin) {
            // Only load the plugins we want to load, according to the config
            if (!in_array(str_replace(".php", "", basename($plugin)), $config["enabledPlugins"]) && !in_array(str_replace(".php", "", basename($plugin)), $adminPlugins)) {
                continue;
            }

            require_once($plugin);
            $fileName = str_replace(".php", "", basename($plugin));
            $p = new $fileName();
            $p->init($config, $discord, $logger);
            $plugins[] = $p;
        }
    }

// Number of chat plugins loaded
    $logger->addInfo("Loaded: " . count($plugins) . " chat plugins");
}

//Check initial server state (tick plugins will not run if eve is offline)
$crestData = json_decode(downloadData("https://crest-tq.eveonline.com/"), true);
$crestStatus = isset($crestData["serviceStatus"]) ? $crestData["serviceStatus"] : "offline";
setPermCache("serverState", $crestStatus);
setPermCache("statusLastState", $crestStatus);
$logger->addInfo("serverState: EVE is currently {$crestStatus}");

$discord->on(
    'ready',
    function ($discord) use ($logger, $config, $plugins, $pluginsT, $discord) {
        // In here we can access any of the WebSocket events.
        //
        // There is a list of event constants that you can
        // find here: https://teamreflex.github.io/DiscordPHP/classes/Discord.WebSockets.Event.html
        //
        // We will echo to the console that the WebSocket is ready.
        $logger->addInfo('Discord WebSocket is ready!' . PHP_EOL);

        // Database check
        $discord->loop->addPeriodicTimer(86400, function () use ($logger) {
            updateDramielDB($logger);
        });

        //Set Initial Game
        $gameTitle = $config["bot"]["game"];
        if (!is_null(getPermCache("botGame"))) {
            $gameTitle = getPermCache("botGame");
        }
        $game = $discord->factory(Game::class, [
            'name' => $gameTitle,
        ]);
        $discord->updatePresence($game);

        // Server Status Check (tick plugins will not run if eve is offline)
        $discord->loop->addPeriodicTimer(60, function () use ($logger) {
            $crestData = json_decode(downloadData("https://crest-tq.eveonline.com/"), true);
            $crestStatus = isset($crestData["serviceStatus"]) ? $crestData["serviceStatus"] : "offline";
            setPermCache("serverState", $crestStatus);
        });

        // Run the Tick plugins
        $discord->loop->addPeriodicTimer(3, function () use ($pluginsT) {
            foreach ($pluginsT as $plugin) {
                $plugin->tick();
            }
        });

        // Message queue
        $discord->loop->addPeriodicTimer(7, function () use ($discord, $logger) {
            $id = getOldestMessage();
            $id = $id["MIN(id)"];
            if (is_null($id)) {
                $id = 1;
            }
            $queuedMessage = getQueuedMessage($id);
            if (!is_null($queuedMessage)) {
                $guild = $discord->guilds->get('id', $queuedMessage['guild']);
                $channel = $guild->channels->get('id', $queuedMessage['channel']);
                $logger->addInfo("QueueProcessing - Completing queued item #{$id} : {$queuedMessage['message']}");
                $channel->sendMessage($queuedMessage['message'], false);
                clearQueuedMessages($id);
            }
        });

        // Rename queue
        $discord->loop->addPeriodicTimer(10, function () use ($discord, $logger) {
            $x = 0;
            while ($x < 4) {
                $id = getOldestRename();
                $id = $id["MIN(id)"];
                if (is_null($id)) {
                    $id = 1;
                    $x = 4;
                }
                $queuedRename = getQueuedRename($id);
                if (!is_null($queuedRename)) {
                    $guild = $discord->guilds->get('id', $queuedRename['guild']);
                    $member = $guild->members->get("id", $queuedRename['discordID']);
                    $member->setNickname($queuedRename['nick']);
                    clearQueuedRename($id);
                }
                $x++;
            }
        });

        // Mem cleanup every 30 minutes
        $discord->loop->addPeriodicTimer(1800, function () use ($logger) {
            $logger->addInfo("Memory in use: " . memory_get_usage() / 1024 / 1024 . "MB");
            gc_collect_cycles(); // Collect garbage
            $logger->addInfo("Memory in use after garbage collection: " . memory_get_usage() / 1024 / 1024 . "MB");
        });

        $discord->on(
            Event::MESSAGE_CREATE,
            function ($message) use ($logger, $config, $plugins) {

                $msgData = array(
                    "message" => array(
                        "timestamp" => $message->timestamp,
                        "id" => $message->id,
                        "message" => $message->content,
                        "channelID" => $message->channel_id,
                        "from" => $message->author->username,
                        "fromID" => $message->author->id,
                        "fromDiscriminator" => $message->author->discriminator,
                        "fromAvatar" => $message->author->avatar
                    )
                );

                if ($message->content == '(╯°□°）╯︵ ┻━┻') {
                    $message->reply('┬─┬﻿ ノ( ゜-゜ノ)');
                }

                // We are just checking if the message equals to ping and replying to the user with a pong!
                if ($message->content == 'ping') {
                    $message->reply('pong!');
                }
                // Check for plugins
                if (isset($message->content[0])) {
                    if ($message->content[0] == $config["bot"]["trigger"]) {
                        foreach ($plugins as $plugin) {
                            try {
                                $plugin->onMessage($msgData, $message);
                            } catch (Exception $e) {
                                $logger->addError("Error: " . $e->getMessage());
                            }
                        }
                    }
                }
            }
        );
    }
);
$discord->on(
    'error',
    function ($error) use ($logger) {
        $logger->addError($error);
        exit(1);
    }
);
$discord->on(
    'reconnecting',
    function () use ($logger) {
        $logger->addInfo('Websocket is reconnecting..');
    });
$discord->on(
    'reconnected',
    function () use ($logger) {
        $logger->addInfo('Websocket was reconnected..');
    });
// Now we will run the ReactPHP Event Loop!
$discord->run();

