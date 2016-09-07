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
class corpInfo
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
     * @return null
     */
    function onMessage($msgData, $message)
    {
        $this->message = $message;

        $message = $msgData["message"]["message"];
        $user = $msgData["message"]["from"];

        $data = command($message, $this->information()["trigger"], $this->config["bot"]["trigger"]);
        if (isset($data["trigger"])) {
            $messageString = $data["messageString"];
            $cleanString = urlencode($messageString);
            $url = "https://api.eveonline.com/eve/CharacterID.xml.aspx?names={$cleanString}";
            $xml = makeApiRequest($url);
            if (empty($data)) {
                return $this->message->reply("**Error:** Unable to find any group matching that name.");
            }
            $corpID = null;
            if (isset($xml->result->rowset->row)) {
                foreach ($xml->result->rowset->row as $character) {
                    $corpID = $character->attributes()->characterID;
                }
            }

            if (empty($corpID)) {
                return $this->message->reply("**Error:** Unable to find any group matching that name.");
            }

            // Get stats
            $statsURL = "https://beta.eve-kill.net/api/corpInfo/corporationID/" . urlencode($corpID) . "/";
            $stats = json_decode(downloadData($statsURL), true);

            if (is_null(@$stats["corporationActiveArea"])) {
                return $this->message->reply("**Error:** No data available for that group.");
            }

            $corporationName = @$stats["corporationName"];
            $allianceName = isset($stats["allianceName"]) ? $stats["allianceName"] : "None";
            $factionName = isset($stats["factionName"]) ? $stats["factionName"] : "None";
            $ceoName = @$stats["ceoName"];
            $homeStation = @$stats["stationName"];
            $taxRate = @$stats["taxRate"];
            $corporationActiveArea = @$stats["corporationActiveArea"];
            $allianceActiveArea = @$stats["allianceActiveArea"];
            $memberCount = @$stats["memberArrayCount"];
            $superCaps = @count($stats["superCaps"]);
            $ePeenSize = @$stats["ePeenSize"];
            $url = "https://zkillboard.com/corporation/" . @$stats["corporationID"] . "/";


            $msg = "```Corp Name: {$corporationName}
Alliance Name: {$allianceName}
Faction: {$factionName}
CEO: {$ceoName}
Home Station: {$homeStation}
Tax Rate: {$taxRate}%
Corp Active Region: {$corporationActiveArea}
Alliance Active Region: {$allianceActiveArea}
Member Count: {$memberCount}
Known Super Caps: {$superCaps}
ePeen Size: {$ePeenSize}
```
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
            "name" => "corp",
            "trigger" => array($this->config["bot"]["trigger"] . "corp"),
            "information" => "Returns basic EVE Online data about a corporation from projectRena. To use simply type !corp corporation_name"
        );
    }

    function onMessageAdmin()
    {
    }

}
