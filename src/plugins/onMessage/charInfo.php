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

use Discord\Discord;

/**
 * @property  message
 */
class charInfo
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
    var $excludeChannel;
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
        $this->excludeChannel = $this->config["bot"]["restrictedChannels"];
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
     * @return null
     */
    function onMessage($msgData, $message)
    {
        $channelID = (int)$msgData["message"]["channelID"];

        if (in_array($channelID, $this->excludeChannel, true))
        {
            return null;
        }

        $this->message = $message;

        $message = $msgData["message"]["message"];
        $user = $msgData["message"]["from"];

        $data = command($message, $this->information()["trigger"], $this->config["bot"]["trigger"]);
        if (isset($data["trigger"])) {

            // Most EVE players on Discord use their ingame name, so lets support @highlights
            $messageString = stristr($data["messageString"], "@") ? str_replace("<@", "", str_replace(">", "", $data["messageString"])) : $data["messageString"];
            if (is_numeric($messageString)) {
                // The person used @highlighting, so now we got a discord id, lets map that to a name
                $messageString = dbQueryField("SELECT name FROM usersSeen WHERE id = :id", "name", array(":id" => $messageString));
            }

            $cleanString = urlencode($messageString);

            //API call 1
            $url = "https://api.eveonline.com/eve/CharacterID.xml.aspx?names={$cleanString}";
            $xml = makeApiRequest($url);
            $characterID = null;

            if (isset($xml->result->rowset->row)) {
                foreach ($xml->result->rowset->row as $character) {
                    $characterID = $character->attributes()->characterID;
                }
            }
            if (empty($characterID)) {
                return $this->message->reply("**Error:** no data available");
            }
            $characterID = urlencode($characterID);

            //API call 2
            $url = "https://api.eveonline.com/eve/CharacterInfo.xml.aspx?characterID={$characterID}";
            $xml = makeApiRequest($url);
            foreach ($xml->result as $characterDetails) {
                $dob = $characterDetails->DoB;
                $corporationName = $characterDetails->corporation;
                $corporationJoinDate = $characterDetails->corporationDate;
                $allianceName = $characterDetails->alliance;
                $securityStatus = $characterDetails->securityStatus;
                $characterName = $characterDetails->characterName;
            }

            if ($characterName == null || $characterName == "") {
                return $this->message->reply("**Error:** No character found.");
            }

            //ZKill lookup
            $url = "https://zkillboard.com/api/orderDirection/desc/limit/1/no-items/characterID/{$characterID}/xml/";
            $xml = makeApiRequest($url);
            if (empty($xml)) {
                return $this->message->reply("**Error:** ZKill is down. Try again later.");
            }
            foreach ($xml->result->rowset->row as $kill) {
                $lastSeenSystemID = $kill->attributes()->solarSystemID;
                $lastSeenSystem = apiCharacterName($lastSeenSystemID);
                $lastSeenDate = $kill->attributes()->killTime;
            }
            foreach ($xml->result->rowset->row->rowset->row as $attacker) {
                if ($attacker->attributes()->characterID == $characterID){
                    $lastSeenShipID = $attacker->attributes()->shipTypeID;
                    $lastSeenShip = apiTypeName($lastSeenShipID);
                }
            }
            $url = "https://zkillboard.com/character/{$characterID}/";

            $msg = "```Name: {$characterName}      
			
Corporation Name: {$corporationName}
Joined Corporation On: {$corporationJoinDate}
Alliance Name: {$allianceName}
Security Status: {$securityStatus}

Last Seen In System: {$lastSeenSystem}
Last Seen Flying a: {$lastSeenShip}
Last Seen On: {$lastSeenDate}```

For more info, visit: $url";

            $this->logger->addInfo("charInfo: Sending character info to {$user}");
            $this->message->reply($msg);
        }
        return null;
    }

    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "char",
            "trigger" => array($this->config["bot"]["trigger"] . "char"),
            "information" => "Returns basic EVE Online data about a character. To use simply type !char character_name"
        );
    }

    function onMessageAdmin()
    {
    }

}
