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

use Discord\Parts\Channel\Message;
use Discord\Parts\Channel\Channel;

/**
 * Class fileReaderJabber
 */
class fileReader
{
    /**
     * @var
     */
    var $config;
    /**
     * @var
     */
    var $db;
    /**
     * @var
     */
    var $discord;
    /**
     * @var
     */
    var $channelConfig;
    /**
     * @var int
     */
    var $lastCheck = 0;
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
        $this->channelConfig = $config["plugins"]["fileReader"]["channelConfig"];
        $this->db = $config["plugins"]["fileReader"]["db"];
        if (!is_file($this->db)) {
            touch($this->db);
        }
    }

    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "",
            "trigger" => array(),
            "information" => ""
        );
    }

    /**
     *
     */
    function tick()
    {
        if (filemtime($this->db) >= $this->lastCheck) {
            $data = file($this->db);
            if ($data) {
                $ping = "";
                foreach ($data as $row) {
                    $row = str_replace("^@", "", $row);
                    if ($row == "" || $row == " ") {
                        continue;
                    }

                    $ping .= $row . "  ";
                    usleep(300000);
                }

                // Remove |  from the line or whatever else is at the last two characters in the string
                $message = trim(substr($ping, 0, -2));

                foreach ($this->channelConfig as $chanName => $chanConfig) {
                    if ($chanConfig["searchString"] == false) { // If no match was found, and searchString is false, just use that
                        $message = $chanConfig["textStringPrepend"] . " \n " . $message . "  " . $chanConfig["textStringAppend"];
                        $channelID = $chanConfig["channelID"];
                    } elseif (stristr($message, $chanConfig["searchString"])) {
                        $message = $chanConfig["textStringPrepend"] . " \n " . $message . "  " . $chanConfig["textStringAppend"];
                        $channelID = $chanConfig["channelID"];
                    }
                }
                $begin = mb_substr($message, 0, 15);
                if (stristr($begin, "#")) {
                    $message = "skip";
                }
                if ($channelID == "" || $channelID == null) {
                    $message = "skip";
                }
                if ($message != "skip") {
                    $this->logger->addInfo("Ping sent to channel {$channelID}, Message - {$message}");
                    // Send the pings to the channel
                    $channel = Channel::find($channelID);
                    $channel->sendMessage($message, false);
                }
            }
            $h = fopen($this->db, "w+");
            fclose($h);
            chmod($this->db, 0777);
            $data = null;
            $h = null;
        }
        clearstatcache();
        $this->lastCheck = time();
    }

    /**
     * @param $msgData
     */
    function onMessage($msgData)
    {
    }
}
