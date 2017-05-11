<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2016 Robert Sardinia
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

/**
 * @property  message
 */
class help
{
    public $config;
    public $discord;
    public $logger;
    private $excludeChannel;
    private $message;
    private $triggers;

    /**
     * @param $config
     * @param $primary
     * @param $discord
     * @param $logger
     */
    public function init($config, $primary, $discord, $logger)
    {
        $this->config = $config;
        $this->discord = $discord;
        $this->logger = $logger;
        $this->excludeChannel = $this->config['bot']['restrictedChannels'];
        $this->triggers[] = $this->config['bot']['trigger'] . 'help';
        $this->triggers[] = $this->config['bot']['trigger'] . 'Help';
    }

    /**
     * @param $msgData
     * @param $message
     * @return null
     */
    public function onMessage($msgData, $message, $discordWeb)
    {
        $channelID = (int) $msgData['message']['channelID'];

        if (in_array($channelID, $this->excludeChannel, true))
        {
            return null;
        }

        $this->message = $message;

        $message = $msgData['message']['message'];
        $user = $msgData['message']['from'];

        $data = command($message, $this->information()['trigger'], $this->config['bot']['trigger']);
        if (isset($data['trigger'])) {
            global $plugins; // Need to have the plugins that are loaded available, yes it's ugly, whatever, better than shitting up the rest of the code :P
            $messageString = $data['messageString'];

            if (!$messageString) {
                // Show all modules available
                $commands = array();
                foreach ($plugins as $plugin) {
                    $info = $plugin->information();
                    if (!empty($info['name'])) {
                        $commands[] = $info['name'];
                    }
                }

                $this->message->reply('Here is a list of plugins available: **' . implode('** |  **', $commands) . "** If you'd like help with a specific plugin simply use the command !help <PluginName>");
                $this->logger->addInfo("Help: Sending help info to {$user}");
            } else {
                foreach ($plugins as $plugin) {
                    if (strtolower($messageString) === strtolower($plugin->information()['name'])) {
                        $this->message->reply($plugin->information()['information']);
                    }
                }
            }
        }
    }

    /**
     * @return array
     */
    public function information()
    {
        return array(
            'name' => 'help',
            'trigger' => $this->triggers,
            'information' => 'Shows help for a plugin, or all the plugins available. Example: **!help pc**'
        );
    }
}