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
/** @noinspection PhpIncludeInspection */
require_once __DIR__ . '/vendor/autoload.php';

// Setup logger
use Discord\Discord;
use Discord\Parts\User\Game;
use Discord\WebSockets\Event;
use Discord\WebSockets\WebSocket;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use RestCord\DiscordClient;

// More memory allowance
ini_set('memory_limit', '1024M');

// Just in case we get launched from somewhere else
chdir(__DIR__);

// Enable garbage collection
gc_enable();

// When the bot started
$startTime = time();

// check log file and rotate if necessary
if (filesize('log/dramielLog.log') > 100000) {
    if (is_file('log/dramielLogOld.log')){
        rename('log/dramielLogOld.log', 'log/dramielLogOld2.log');
    }
    rename('log/dramielLog.log', 'log/dramielLogOld.log');
}

// create a log channel
$logger = new Logger('Dramiel');
$logger->pushHandler(new StreamHandler(__DIR__ . '/log/dramielLog.log', Logger::INFO));
$logger->addInfo('Logger Initiated');

GLOBAL $logger;

// Load the library files (Probably a prettier way to do this that i haven't thought up yet)
foreach (glob(__DIR__ . '/src/lib/*.php') as $lib) {
    require_once $lib;
}

// Require the config
if (file_exists('config/config.php')) {
    /** @noinspection PhpIncludeInspection */
    require_once 'config/config.php';
} else {
    $logger->error('config.php not found (you might wanna start by editing and renaming config_new.php)');
    die();
}
require_once 'config/primary.php';

//Startup DB Check
updateDramielDB($logger);
authDB($logger);

// Init Discord
$discord = new Discord(['token' => $primary['bot']['token']]);
$discordWeb = new DiscordClient(['token' => $config['bot']['webBot']]);

// Load tick plugins
$pluginDirs = array('src/plugins/onTick/*.php');
$logger->info('Loading background plugins');
$plugins = array();
foreach ($pluginDirs as $dir) {
    foreach (glob($dir) as $plugin) {
        // Only load the plugins we want to load, according to the config
        if (!in_array(str_replace('.php', '', basename($plugin)), $config['enabledPlugins'])) {
            continue;
        }

        /** @noinspection PhpIncludeInspection */
        require_once $plugin;
        $fileName = str_replace('.php', '', basename($plugin));
        $p = new $fileName();
        $p->init($config, $primary, $discord, $logger);
        $pluginsT[] = $p;
    }
}
// Number of plugins loaded
$logger->info('Loaded: ' . count($pluginsT) . ' background plugins');

if ($config['bot']['silentMode'] == 'false' || !isset($config['bot']['silentMode'])) {
// Load chat plugins
    $pluginDirs = array('src/plugins/onMessage/*.php', 'src/plugins/admin/*.php');
    $adminPlugins = array('setNickname', 'getLog', 'setGame', 'setAvatar');
    $logger->addInfo('Loading in chat plugins');
    $plugins = array();
    foreach ($pluginDirs as $dir) {
        foreach (glob($dir) as $plugin) {
            // Only load the plugins we want to load, according to the config
            if (!in_array(str_replace('.php', '', basename($plugin)), $config['enabledPlugins']) && !in_array(str_replace('.php', '', basename($plugin)), $adminPlugins)) {
                continue;
            }

            /** @noinspection PhpIncludeInspection */
            require_once $plugin;
            $fileName = str_replace('.php', '', basename($plugin));
            $p = new $fileName();
            $p->init($config, $primary, $discord, $logger);
            $plugins[] = $p;
        }
    }

// Number of chat plugins loaded
    $logger->addInfo('Loaded: ' . count($plugins) . ' chat plugins');
}

// Clear queue at restart if it's too high
clearQueueCheck();

//Check initial server state (tick plugins will not run if eve is offline)
$crestData = json_decode(downloadData('https://crest-tq.eveonline.com/'), true);
$crestStatus = isset($crestData['serviceStatus']) ? $crestData['serviceStatus'] : 'offline';
setPermCache('serverState', $crestStatus);
setPermCache('statusLastState', $crestStatus);
$logger->addInfo("serverState: EVE is currently {$crestStatus}");

//Clean up any outdated databases
dbPrune();

$discord->on(
    'ready',
    function($discord) use ($logger, $config, $primary, $plugins, $pluginsT, $discordWeb) {
        // In here we can access any of the WebSocket events.
        //
        // There is a list of event constants that you can
        // find here: https://teamreflex.github.io/DiscordPHP/classes/Discord.WebSockets.Event.html
        //
        // We will echo to the console that the WebSocket is ready.
        $logger->addInfo('Discord WebSocket is ready!' . PHP_EOL);

        //Check if web bot has been invited
        $guild = $this->discord->guilds->get('id', $config['bot']['guild']);
        $webBot = @$guild->members->get('id', 311988269414088704);
        if (is_null($webBot->joined_at)) {
            $logger->error('DRAMIEL_WEB not found in server, please invite it and give it bot/admin roles (This is a new requirement). https://discordapp.com/oauth2/authorize?&client_id=311988269414088704&scope=bot');
            die();
        }


        //Set Initial Game
        $gameTitle = @$config['bot']['game'];
        if (null !== getPermCache('botGame')) {
            $gameTitle = getPermCache('botGame');
        }
        $game = $discord->factory(Game::class, [
            'name' => $gameTitle,
        ]);
        $discord->updatePresence($game);

        // Server Status Check (tick plugins will not run if eve is offline)
        $discord->loop->addPeriodicTimer(60, function() use ($logger) {
            $crestData = json_decode(downloadData('https://crest-tq.eveonline.com/'), true);
            $crestStatus = isset($crestData['serviceStatus']) ? $crestData['serviceStatus'] : 'offline';
            setPermCache('serverState', $crestStatus);
        });

        // Run the Tick plugins
        $discord->loop->addPeriodicTimer(3, function() use ($pluginsT) {
            foreach ($pluginsT as $plugin) {
                $plugin->tick();
            }
        });

        // Run Message Queue
        $discord->loop->addPeriodicTimer(10, function() use ($discord, $logger) {
            $messageCount = countMessageQueue();
            if ((int)$messageCount > 0){
                messageQueue($discord, $logger);
                sleep(1);
            }
        });

        // Run Other Queues
        $discord->loop->addPeriodicTimer(15, function() use ($discord, $discordWeb, $logger) {
            $renameCount = countRenameQueue();
            $authCount = countAuthQueue();
            if ((int)$renameCount > 0){
                renameQueue($discordWeb, $logger);
            }
            if ((int)$authCount > 0){
                authQueue($discordWeb, $logger);
            }
        });

        // Mem cleanup every 30 minutes
        $discord->loop->addPeriodicTimer(1800, function() use ($logger) {
            $logger->addInfo('Memory in use: ' . memory_get_usage() / 1024 / 1024 . 'MB');
            gc_collect_cycles(); // Collect garbage
            $logger->addInfo('Memory in use after garbage collection: ' . memory_get_usage() / 1024 / 1024 . 'MB');

            // check log file and rotate if necessary
            if (filesize('log/dramielLog.log') > 100000) {
                if (is_file('log/dramielLogOld.log')){
                    rename('log/dramielLogOld.log', 'log/dramielLogOld2.log');
                }
                rename('log/dramielLog.log', 'log/dramielLogOld.log');
                touch('log/dramielLog.log');

            }
        });

        $discord->on(
            Event::MESSAGE_CREATE,
            function($message) use ($logger, $config, $primary, $plugins) {

                $msgData = array(
                    'message' => array(
                        'timestamp' => @$message->timestamp,
                        'id' => @$message->id,
                        'message' => @$message->content,
                        'channelID' => @$message->channel_id,
                        'from' => @$message->author->username,
                        'fromID' => @$message->author->id,
                        'fromDiscriminator' => @$message->author->discriminator,
                        'fromAvatar' => @$message->author->avatar
                    )
                );

                if ($message->content == '(╯°□°）╯︵ ┻━┻') {
                    $message->reply('┬─┬﻿ ノ( ゜-゜ノ)');
                }

                // Check for plugins
                if (isset($message->content[0])) {
                    if ($message->content[0] == $config['bot']['trigger']) {
                        foreach ($plugins as $plugin) {
                            try {
                                $plugin->onMessage($msgData, $message);
                            } catch (Exception $e) {
                                $logger->addError('Error: ' . $e->getMessage());
                            }
                        }
                    }
                }
            }
        );

       /* //Welcome message
        $discord->on(
            Event::GUILD_MEMBER_ADD,
            function($member) use ($logger, $config) {
                $welcomePlugin = dbQueryField("SELECT value FROM config WHERE variable = 'welcomeMessage'", 'value', array(), 'config');
                if ($welcomePlugin === 'true') {
                    $welcomeMessage = dbQueryField("SELECT value FROM config WHERE variable = 'welcomeMessageMessage'", 'value', array(), 'config');
                    $member->user->sendMessage($welcomeMessage, false);
                    $name = $member->user->username;
                    $logger->addInfo("welcomeMessage: $name has connected to the server, sending a welcome message.");
                }
            }
        );*/
    }
);
$discord->on(
    'error',
    function($error) use ($logger) {
        $logger->addError($error);
        exit(1);
    }
);
$discord->on(
    'reconnecting',
    function() use ($logger) {
        $logger->addInfo('Websocket is reconnecting..');
    });
$discord->on(
    'reconnected',
    function() use ($logger) {
        $logger->addInfo('Websocket was reconnected..');
    });
// Now we will run the ReactPHP Event Loop!
$discord->run();

