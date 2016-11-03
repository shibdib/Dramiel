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
use discord\discord;

/**
 * Class ops
 * @property  userID
 * @property  apiKey
 * @property  groupID
 * @property  excludeChannel
 */
class fleetUpOps
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
    public $userID;
    public $groupID;
    public $apiKey;
    public $guild;
    public $excludeChannel;
    public $message;
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
        $this->userID = $config["plugins"]["ops"]["userID"];
        $this->groupID = $config["plugins"]["ops"]["groupID"];
        $this->apiKey = $config["plugins"]["ops"]["apiKey"];
        $this->guild = $config["bot"]["guild"];
        $this->excludeChannel = $config["plugins"]["ops"]["channelID"];
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
        $user = $msgData["message"]["from"];
        $channelID = $msgData["message"]["channelID"];
        $message = $msgData["message"]["message"];

        $data = command($message, $this->information()["trigger"], $this->config["bot"]["trigger"]);
        if (isset($data["trigger"])) {

            // Check if the channel is restricted
            if ($channelID == $this->excludeChannel) {
                return $this->message->reply("**Upcoming Ops not allowed in this channel**");

                $discord = $this->discord;
                date_default_timezone_set("UTC");
            }
            //fleetUp post upcoming operations
            $ops = json_decode(downloadData("http://api.fleet-up.com/Api.svc/tlYgBRjmuXj2Yl1lEOyMhlDId/{$this->userID}/{$this->apiKey}/Operations/{$this->groupID}"), true);
            foreach ($ops["Data"] as $operation) {
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
                $this->logger->addInfo("Sending ops info to {$user}");
                $this->message->reply("
**Upcoming Operation**.
Title - {$name}.
Form Up Time - {$startTime}. EVE time
Form Up System - {$formUp}.
Target System - {$desto}.
Details - {$info}.
Link - {$link}");
            }
        }
    }

    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "ops",
            "trigger" => array($this->config["bot"]["trigger"] . "ops"),
            "information" => "This shows the upcoming operations. To use simply type <!ops>"
        );
    }
}
