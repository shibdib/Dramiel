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

use discord\discord;

/**
 * Class fleetUpOperations
 * @property  userID
 * @property  apiKey
 * @property  groupID
 */
class fleetUpOperations {
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
        $this->toDiscordChannel = $config["plugins"]["fleetUp"]["channelID"];
        $this->userID = $config["plugins"]["fleetUp"]["userID"];
        $this->groupID = $config["plugins"]["fleetUp"]["groupID"];
        $this->apiKey = $config["plugins"]["fleetUp"]["apiKey"];
        $this->guild = $config["bot"]["guild"];
        $lastCheck = getPermCache("fleetUpPostLastChecked");
        if ($lastCheck == NULL) {
            // Schedule it for right now if first run
            setPermCache("fleetUpPostLastChecked", time() - 5);
        }
    }

    /**
     *
     */
    function tick()
    {
        $lastChecked = getPermCache("fleetUpPostLastChecked");

        if ($lastChecked <= time()) {
            $this->postFleetUp();
            $this->checkFleetUp();
        }
    }

    function postFleetUp()
    {
        $discord = $this->discord;

        date_default_timezone_set("UTC");
        $eveTime = time();
        //fleetUp post upcoming operations
        $currentID = getPermCache("fleetUpLastPostedOperation");
        $fleetUpOperations = json_decode(downloadData("http://api.fleet-up.com/Api.svc/tlYgBRjmuXj2Yl1lEOyMhlDId/{$this->userID}/{$this->apiKey}/Operations/{$this->groupID}"), true);
        foreach ($fleetUpOperations["Data"] as $operation) {
            $name = $operation["Subject"];
            $startTime = $operation["StartString"];
            preg_match_all('!\d+!', $operation["Start"], $epochStart);
            $startTimeUnix = substr($epochStart[0][0], 0, -3);
            $desto = $operation["Location"];
            $formUp = $operation["LocationInfo"];
            $info = $operation["Details"];
            $id = $operation["Id"];
            $link = "https://fleet-up.com/Operation#{$id}\
			";
            $timeDifference = $startTimeUnix - $eveTime;
            if ($currentID < $id) {
                if ($timeDifference < 1800) {
                    $msg = "@everyone
**Upcoming Operation** 
Title - {$name} 
Form Up Time - {$startTime} 
Form Up System - {$formUp} 
Target System - {$desto} 
Details - {$info} 

Link - {$link}";
                    $channelID = $this->toDiscordChannel;
                    $guild = $discord->guilds->get('id', $this->guild);
                    $channel = $guild->channels->get('id', $channelID);
                    $channel->sendMessage($msg, false);
                    setPermCache("fleetUpLastPostedOperation", $id);
                    $this->logger->addInfo("Latest upcoming operation ID - {$id}");

                }
            }
        }
        setPermCache("fleetUpPostLastChecked", time() + 120);
    }

    function checkFleetUp()
    {
        $discord = $this->discord;

        $lastChecked = getPermCache("fleetUpLastChecked");

        if ($lastChecked >= time()) {
            return null;
        }

        //fleetUp check for new operations
        $currentID = getPermCache("fleetUpLastOperation");
        $fleetUpOperations = json_decode(downloadData("http://api.fleet-up.com/Api.svc/tlYgBRjmuXj2Yl1lEOyMhlDId/{$this->userID}/{$this->apiKey}/Operations/{$this->groupID}"), true);
        foreach ($fleetUpOperations["Data"] as $operation) {
            $name = $operation["Subject"];
            $startTime = $operation["StartString"];
            $desto = $operation["Location"];
            $formUp = $operation["LocationInfo"];
            $info = $operation["Details"];
            $id = $operation["Id"];
            $link = "https://fleet-up.com/Operation#{$id}\
			";
            if ($currentID < $id) {
                $msg = "
**New Operation Posted** 
Title - {$name} 
Form Up Time - {$startTime} 
Form Up System - {$formUp} 
Target System - {$desto} 
Details - {$info} 

Link - {$link}";
                $channelID = $this->toDiscordChannel;
                $guild = $discord->guilds->get('id', $this->guild);
                $channel = $guild->channels->get('id', $channelID);
                $channel->sendMessage($msg, false);
                setPermCache("fleetUpLastOperation", $id);
            }
        }
        setPermCache("fleetUpLastChecked", time() + 1800);
        if (isset($id) and $id != $currentID) {
            $this->logger->addInfo("Newest fleetUp operation ID - {$id}");
        }
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
