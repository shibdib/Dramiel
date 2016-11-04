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
 * Class getKillmails
 */
class getKillmails
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
    public $groupConfig;

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
        $this->groupConfig = $config["plugins"]["getKillmails"]["groupConfig"];
        $this->guild = $config["bot"]["guild"];
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
        $lastChecked = getPermCache("killmailCheck");
        if ($lastChecked <= time()) {
            //check if user is still using the old config
            if(is_null($this->groupConfig)){
                $this->logger->addError("Killmails: UPDATE YOUR CONFIG TO RECEIVE KILLMAILS");
                setPermCache("killmailCheck", time() + 900);
                return null;
            }
            $this->logger->addInfo("Killmails: Checking for new killmails.");
            $this->getKM();
            setPermCache("killmailCheck", time() + 900);
        }

    }

    function getKM()
    {
        foreach($this->groupConfig as $kmGroup) {
            $lastMail = getPermCache("{$kmGroup["name"]}newestKillmailID");
            if (is_null($lastMail)){
                $lastMail = $kmGroup["startMail"];
            }
            if ($kmGroup["allianceID"] == "0" & $kmGroup["lossMails"] == 'true') {
                $url = "https://zkillboard.com/api/no-attackers/no-items/orderDirection/asc/afterKillID/{$lastMail}/corporationID/{$kmGroup["corpID"]}/";
            }
            if ($kmGroup["allianceID"] == "0" & $kmGroup["lossMails"] == 'false') {
                $url = "https://zkillboard.com/api/no-attackers/no-items/kills/orderDirection/asc/afterKillID/{$lastMail}/corporationID/{$kmGroup["corpID"]}/";
            }
            if ($kmGroup["allianceID"] != "0" & $kmGroup["lossMails"] == 'true') {
                $url = "https://zkillboard.com/api/no-attackers/no-items/orderDirection/asc/afterKillID/{$lastMail}/allianceID/{$kmGroup["allianceID"]}/";
            }
            if ($kmGroup["allianceID"] != "0" & $kmGroup["lossMails"] == 'false') {
                $url = "https://zkillboard.com/api/no-attackers/no-items/kills/orderDirection/asc/afterKillID/{$lastMail}/allianceID/{$kmGroup["allianceID"]}/";
            }

            if (!isset($url)) { // Make sure it's always set.
                $this->logger->addInfo("Killmails: ERROR - Ensure your config file is setup correctly for killmails.");
                return null;
            }

            $xml = json_decode(downloadData($url), true);
            $i = 0;
            $limit = $kmGroup["spamAmount"];
            if (isset($xml)) {
                foreach ($xml as $kill) {
                    if ($i < $limit) {
                        //if big kill isn't set, disable it
                        if (is_null($kmGroup["bigKill"])){
                            $kmGroup["bigKill"] = 99999999999999999999999999;
                        }
                        $killID = $kill['killID'];
                        //check if start id is greater than current id
                        if ($kmGroup["startMail"] > $killID) {
                            $killID = $kmGroup["startMail"];
                        }
                        $channelID = $kmGroup["channel"];
                        $solarSystemID = $kill['solarSystemID'];
                        $systemName = apiCharacterName($solarSystemID);
                        $killTime = $kill['killTime'];
                        $victimAllianceName = $kill['victim']['allianceName'];
                        $victimName = $kill['victim']['characterName'];
                        $victimCorpName = $kill['victim']['corporationName'];
                        $victimShipID = $kill['victim']['shipTypeID'];
                        $shipName = apiTypeName($victimShipID);
                        $rawValue = $kill['zkb']['totalValue'];
                        $totalValue = number_format($kill['zkb']['totalValue']);
                        // Check if it's a structure
                        if ($victimName != "") {
                            if ($rawValue >= $kmGroup["bigKill"]) {
                                $channelID = $kmGroup["bigKillChannel"];
                                $msg = "@here \n :warning:***Expensive Killmail***:warning: \n **{$killTime}**\n\n**{$shipName}** worth **{$totalValue} ISK** flown by **{$victimName}** of (***{$victimCorpName}|{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                            } elseif ($rawValue <= $kmGroup["bigKill"]) {
                                $msg = "**{$killTime}**\n\n**{$shipName}** worth **{$totalValue} ISK** flown by **{$victimName}** of (***{$victimCorpName}|{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                            }
                        } elseif ($victimName == "") {
                            $msg = "**{$killTime}**\n\n**{$shipName}** worth **{$totalValue} ISK** owned by (***{$victimCorpName}|{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                        }

                        if (!isset($msg)) { // Make sure it's always set.
                            return null;
                        }
                        queueMessage($msg, $channelID, $this->guild);
                        setPermCache("{$kmGroup["name"]}newestKillmailID", $killID++);

                        $i++;
                    } else {
                        $updatedID = getPermCache("{$kmGroup["name"]}newestKillmailID");
                        $this->logger->addInfo("Killmails: Kill posting cap reached, newest kill id for {$kmGroup["name"]} is {$updatedID}");
                        break;
                    }
                }
            }
            $updatedID = getPermCache("{$kmGroup["name"]}newestKillmailID");
            $this->logger->addInfo("Killmails: All kills posted, newest kill id for {$kmGroup["name"]} is {$updatedID}");
            continue;
        }
    }
}
