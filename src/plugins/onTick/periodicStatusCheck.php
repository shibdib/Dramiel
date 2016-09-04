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

use Discord\Parts\Channel\Channel;

/**
 * Class periodicStatusCheck
 */
class periodicStatusCheck {
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
     * @var
     */
    var $toDiscordChannel;
    protected $keyID;
    protected $vCode;
    protected $prefix;

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
        $this->guild = $config["bot"]["guild"];
        $this->toDiscordChannel = $config["plugins"]["notifications"]["channelID"];
        $lastCheck = getPermCache("statusLastChecked");
        if ($lastCheck == NULL) {
            // Schedule it for right now if first run
            setPermCache("statusLastChecked", time() - 5);
        }
    }

    /**
     *
     */
    function tick()
    {
        $lastChecked = getPermCache("statusLastChecked");

        if ($lastChecked <= time()) {
            $this->checkStatus();
        }
    }

    function checkStatus()
    {
        $discord = $this->discord;

        if ($this->toDiscordChannel == 0) {
            setPermCache("statusLastChecked", time() + 300);
            $this->logger->addInfo("TQ Status Check Failed - Add a channel ID to the notifications section in the config.");
            return null;
        }
        // What was the servers last reported state
        $lastStatus = getPermCache("statusLastState");

        //api
        $url = "https://api.eveonline.com/server/ServerStatus.xml.aspx";
        $xml = makeApiRequest($url);

        //Crest
        $crestData = json_decode(downloadData("https://crest-tq.eveonline.com/"), true);
        $crestStatus = isset($crestData["serviceStatus"]) ? $crestData["serviceStatus"] : "offline";
        $tqOnline = (int) $crestData["userCount"];

        if (!isset($xml->result)) {
            $this->logger->addInfo("TQ Status check canceled, API Error.");
            setPermCache("statusLastChecked", time() + 300);
            return null;
        }

        foreach ($xml->result as $info) {
            $apiStatus = $info->serverOpen;
            if ($apiStatus == "True") {$apiStatus = "online"; } else {$apiStatus = "offline"; }
        }
        if ($crestStatus != "online") { $crestHolder = "offline"; } else { $crestHolder = "online"; }
        if ($crestHolder != $apiStatus) {
            $this->logger->addInfo("TQ Status check canceled, CREST and API different.");
            setPermCache("statusLastChecked", time() + 300);
            return null;
        }
        if ($lastStatus == $crestStatus) {
            // No change
            setPermCache("statusLastChecked", time() + 300);
            return null;
        }
        $msg = "**TQ Status Change:** {$crestStatus} with {$tqOnline} users online.";
        $channelID = $this->toDiscordChannel;
        $guild = $discord->guilds->get('id', $this->guild);
        $channel = $guild->channels->get('id', $channelID);
        $channel->sendMessage($msg, false);
        setPermCache("statusLastState", $crestStatus);
        setPermCache("statusLastChecked", time() + 300);
        $this->logger->addInfo("TQ Status Change Detected. New status - {$crestStatus}");
        return null;
    }

    /**
     *
     */
    function onMessage()
    {
    }

    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "",
            "trigger" => array(""),
            "information" => ""
        );
    }
}
