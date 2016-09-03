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
     * @return null
     */
    function onMessage($msgData, $message)
    {
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

            $url = "https://api.eveonline.com/eve/CharacterID.xml.aspx?names={$cleanString}";
            $xml = makeApiRequest($url);
            $characterID = null;

            if (isset($xml->result->rowset->row)) { foreach ($xml->result->rowset->row as $character) {
                $characterID = $character->attributes()->characterID;
            }
            }
            if (empty($characterID)) {
                return $this->message->reply("**Error:** no data available");
            }
            // Get stats
            $statsURL = "https://beta.eve-kill.net/api/charInfo/characterID/" . urlencode($characterID) . "/";
            $stats = json_decode(downloadData($statsURL), true);

            if (empty($stats)) {
                return $this->message->reply("**Error:** no data available");
            }

            $characterName = @$stats["characterName"];
            if (empty($characterName)) {
                return $this->message->reply("**Error:** No Character Found");
            }
            $corporationName = @$stats["corporationName"];
            $allianceName = isset($stats["allianceName"]) ? $stats["allianceName"] : "None";
            $factionName = isset($stats["factionName"]) ? $stats["factionName"] : "None";
            $securityStatus = @$stats["securityStatus"];
            $lastSeenSystem = @$stats["lastSeenSystem"];
            $lastSeenRegion = @$stats["lastSeenRegion"];
            $lastSeenShip = @$stats["lastSeenShip"];
            $lastSeenDate = @$stats["lastSeenDate"];
            $corporationActiveArea = @$stats["corporationActiveArea"];
            $allianceActiveArea = @$stats["allianceActiveArea"];
            $ePeenSize = @$stats["ePeenSize"];
            $facepalms = @$stats["facepalms"];
            $lastUpdated = @$stats["lastUpdatedOnBackend"];
            $url = "https://zkillboard.com/character/" . $stats["characterID"] . "/";


            $msg = "```characterName: {$characterName}
corporationName: {$corporationName}
allianceName: {$allianceName}
factionName: {$factionName}
securityStatus: {$securityStatus}
lastSeenSystem: {$lastSeenSystem}
lastSeenRegion: {$lastSeenRegion}
lastSeenShip: {$lastSeenShip}
lastSeenDate: {$lastSeenDate}
corporationActiveArea: {$corporationActiveArea}
allianceActiveArea: {$allianceActiveArea}
ePeenSize: {$ePeenSize}
facepalms: {$facepalms}
lastUpdated: $lastUpdated```
For more info, visit: $url";

            $this->logger->addInfo("Sending character info to {$user}");
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

    /**
     * @param $msgData
     */
    function onMessageAdmin($msgData)
    {
    }

}
