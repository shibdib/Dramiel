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
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

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
$logger->pushHandler(new StreamHandler(__DIR__.'/log/dramielLog.log', Logger::DEBUG));
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



//Init Discord
use Discord\Cache\Cache;
use Discord\Cache\Drivers\ArrayCacheDriver;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Guild\Guild;
use Discord\Parts\User\Game;
use Discord\Parts\WebSockets\PresenceUpdate;
use Discord\WebSockets\Event;
use Discord\WebSockets\WebSocket;

$discord = new Discord('MTc4NjA4Mzk4NDQxNTEyOTYx.Ck5kHA.3mDMVFdFA4tRnHvUTdbggcpY56A');

$pluginDirs = array("src/plugins/onMessage/*.php");
$logger->addInfo("Loading in chat plugins");
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
        $p->init($config, $discord, $logger, $message);
        $plugins[] = $p;
    }
}

// Number of plugins loaded
$logger->addInfo("Loaded: " . count($plugins) . " chat plugins");

$ws = new WebSocket($discord);

$ws->on(
    'ready',
    function ($discord) use ($ws, $logger, $config, $plugins) {
        // In here we can access any of the WebSocket events.
        //
        // There is a list of event constants that you can
        // find here: https://teamreflex.github.io/DiscordPHP/classes/Discord.WebSockets.Event.html
        //
        // We will echo to the console that the WebSocket is ready.
        $logger->addInfo('Discord WebSocket is ready!'.PHP_EOL);
        $game = new Game(array('name' => $config["bot"]["game"], 'url' => null, 'type' => null), true);
        $ws->updatePresence($game, false);
        $ws->on(
            Event::MESSAGE_CREATE,
            function ($message, $discord, $newdiscord) use ($logger, $config, $plugins) {

                $logger->addInfo($message);

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
                if ($message->content[0] == $config["bot"]["trigger"]) {
                    foreach ($plugins as $plugin) {
                        try {
                            $plugin->onMessage($msgData, $message);
                        } catch (Exception $e) {
                            $logger->error("Error: " . $e->getMessage());
                        }
                    }
                }
            }
        );
    }
);
$ws->on(
    'error',
    function ($error, $ws) {
        $logger->error($error);
        exit(1);
    }
);
// Now we will run the ReactPHP Event Loop!
$ws->run();

