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

class help
{
    /**
     * @var
     */
    var $config;
    /**
     * @var
     */
    var $discord;
    /**
     * @var
     */
    var $logger;

    /**
     * @param $config
     * @param $discord
     * @param $logger
     */
    function init($config, $discord, $logger)
    {
        $this->config = $config;
        $this->discord = $discord;
        $this->logger = $logger;
    }

    /**
     *
     */
    function tick()
    {
    }

    /**
     * @param $msgData
     */
    function onMessage($msgData)
    {
        $message = $msgData["message"]["message"];
        $channelID = $msgData["message"]["channelID"];

        $data = command($message, $this->information()["trigger"]);
        if (isset($data["trigger"])) {
            global $plugins; // Need to have the plugins that are loaded available, yes it's ugly, whatever, better than shitting up the rest of the code :P
            $messageString = $data["messageString"];

            if (!$messageString) {
                // Show all modules available
                $commands = array();
                foreach ($plugins as $plugin) {
                    $info = $plugin->information();
                    if (!empty($info["name"])) {
                                            $commands[] = $info["name"];
                    }
                }

                $this->discord->api("channel")->messages()->create($channelID, "Here is a list of plugins available: **" . implode("** |  **", $commands) . "** If you'd like help with a specific plugin simply use the command !help <PluginName>");
            } else {
                foreach ($plugins as $plugin) {
                    if ($messageString == $plugin->information()["name"]) {
                        $this->discord->api("channel")->messages()->create($channelID, $plugin->information()["information"]);
                    }
                }
            }
        }
    }

    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "help",
            "trigger" => array("!help"),
            "information" => "Shows help for a plugin, or all the plugins available. Example: **!help pc**"
        );
    }
}