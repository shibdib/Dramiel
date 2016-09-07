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
class eveStatus
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
    public $message;

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
     * @param $message
     */
    function onMessage($msgData, $message)
    {
        $this->message = $message;

        $message = $msgData["message"]["message"];
        $user = $msgData["message"]["from"];

        $data = command($message, $this->information()["trigger"], $this->config["bot"]["trigger"]);
        if (isset($data["trigger"])) {

            $crestData = json_decode(downloadData("https://crest-tq.eveonline.com/"), true);

            $tqStatus = isset($crestData["serviceStatus"]) ? $crestData["serviceStatus"] : "offline";
            $tqOnline = (int) $crestData["userCount"];

            $msg = "**TQ Status:** {$tqStatus} with {$tqOnline} users online.";
            $this->logger->addInfo("Sending eve status info to {$user}");
            $this->message->reply($msg);
        }
    }

    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "tq",
            "trigger" => array($this->config["bot"]["trigger"] . "tq", $this->config["bot"]["trigger"] . "status"),
            "information" => "Shows the current status of Tranquility"
        );
    }

    function onMessageAdmin()
    {
    }

}
