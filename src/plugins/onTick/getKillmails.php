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
    public $config;
    public $db;
    public $discord;
    public $channelConfig;
    public $lastCheck = 0;
    public $logger;
    public $groupConfig;

    /**
     * @param $config
     * @param $primary
     * @param $discord
     * @param $logger
     */
    public function init($config, $primary, $discord, $logger)
    {
        $this->config = $config;
        $this->discord = $discord;
        $this->logger = $logger;
        $this->groupConfig = $config['plugins']['getKillmails']['groupConfig'];
        $this->guild = $config['bot']['guild'];
        //Refresh check at bot start
        setPermCache('killmailCheck', time() - 5);
    }

    /**
     *
     */
    public function tick()
    {
        // What was the servers last reported state
        $lastStatus = getPermCache('serverState');
        if ($lastStatus === 'online') {
            $lastChecked = getPermCache('killmailCheck');
            if ($lastChecked <= time()) {
                //check if user is still using the old config
                if (null === $this->groupConfig) {
                    $this->logger->addError('Killmails: UPDATE YOUR CONFIG TO RECEIVE KILLMAILS');
                    setPermCache('killmailCheck', time() + 600);
                    return null;
                }
                $this->logger->addInfo('Killmails: Checking for new killmails.');
                $this->getKM();
                setPermCache('killmailCheck', time() + 600);
                if (@$this->config['plugins']['getKillmails']['bigKills']['shareBigKills'] === 'true') {
                    $this->logger->addInfo('Killmails: Checking for 10b+ kills.');
                    $this->getBigKM();
                }
            }
        }
    }

    private function getKM()
    {
        foreach ($this->groupConfig as $kmGroup) {
            $killID = getPermCache("{$kmGroup['corpID']}-{$kmGroup['allianceID']}-newestKillmailID");
            //Check if the corp/alliance is set
            if (strlen($kmGroup['corpID']) < 5 && strlen($kmGroup['allianceID']) < 5) {
                continue;
            }
            //check if start id is greater than current id and if it is set
            if (null === $killID || preg_match('/[a-z]/i', $killID)) {
                getStartMail($kmGroup);
                $killID = getPermCache("{$kmGroup['corpID']}-{$kmGroup['allianceID']}-newestKillmailID");
            }
            if ($kmGroup['allianceID'] === '0' & $kmGroup['lossMails'] === 'true') {
                $url = "https://zkillboard.com/api/no-attackers/no-items/orderDirection/asc/corporationID/{$kmGroup['corpID']}/afterKillID/{$killID}/";
            }
            if ($kmGroup['allianceID'] === '0' & $kmGroup['lossMails'] === 'false') {
                $url = "https://zkillboard.com/api/no-attackers/no-items/kills/orderDirection/asc/corporationID/{$kmGroup['corpID']}/afterKillID/{$killID}/";
            }
            if ($kmGroup['allianceID'] !== '0' & $kmGroup['lossMails'] === 'true') {
                $url = "https://zkillboard.com/api/no-attackers/no-items/orderDirection/asc/allianceID/{$kmGroup['allianceID']}/afterKillID/{$killID}/";
            }
            if ($kmGroup['allianceID'] !== '0' & $kmGroup['lossMails'] === 'false') {
                $url = "https://zkillboard.com/api/no-attackers/no-items/kills/orderDirection/asc/allianceID/{$kmGroup['allianceID']}/afterKillID/{$killID}/";
            }

            if (!isset($url)) { // Make sure it's always set.
                $this->logger->addInfo('Killmails: ERROR - Ensure your config file is setup correctly for killmails.');
                return null;
            }

            $kills = json_decode(downloadData($url), true);
            $i = 0;
            if (isset($kills)) {
                foreach ($kills as $kill) {
                    if ($i < 10) {
                        //if big kill isn't set, disable it
                        if (null === $kmGroup['bigKill']) {
                            $kmGroup['bigKill'] = 99999999999999999999999999;
                        }
                        $killID = $kill['killID'];
                        $channelID = $kmGroup['channel'];
                        $solarSystemID = $kill['solarSystemID'];
                        $systemName = systemName($solarSystemID);
                        $killTime = $kill['killTime'];
                        $victimAllianceName = $kill['victim']['allianceName'];
                        $victimName = $kill['victim']['characterName'];
                        $victimCorpName = $kill['victim']['corporationName'];
                        $victimShipID = $kill['victim']['shipTypeID'];
                        $shipName = apiTypeName($victimShipID);
                        $rawValue = $kill['zkb']['totalValue'];
                        //Check if killmail meets minimum value
                        if (isset($kmGroup['minimumValue'])) {
                            if ($rawValue < $kmGroup['minimumValue']) {
                                $this->logger->addInfo("Killmails: Mail {$killID} ignored for not meeting the minimum value required.");
                                setPermCache("{$kmGroup['name']}newestKillmailID", $killID);
                                continue;
                            }
                        }
                        $totalValue = number_format($kill['zkb']['totalValue']);
                        // Check if it's a structure
                        if ($victimName !== '') {
                            if ($rawValue >= $kmGroup['bigKill']) {
                                $channelID = $kmGroup['bigKillChannel'];
                                $msg = "@here \n :warning:***Expensive Killmail***:warning: \n **{$killTime}**\n\n**{$shipName}** worth **{$totalValue} ISK** flown by **{$victimName}** of (***{$victimCorpName}|{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                            } elseif ($rawValue <= $kmGroup['bigKill']) {
                                $msg = "**{$killTime}**\n\n**{$shipName}** worth **{$totalValue} ISK** flown by **{$victimName}** of (***{$victimCorpName}|{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                            }
                        } elseif ($victimName === '') {
                            $msg = "**{$killTime}**\n\n**{$shipName}** worth **{$totalValue} ISK** owned by (***{$victimCorpName}|{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                        }

                        if (!isset($msg)) { // Make sure it's always set.
                            return null;
                        }
                        queueMessage($msg, $channelID, $this->guild);
                        $this->logger->addInfo("Killmails: Mail {$killID} queued.");

                        $i++;
                    } else {
                        $updatedID = getPermCache("{$kmGroup['corpID']}-{$kmGroup['allianceID']}-newestKillmailID");
                        $this->logger->addInfo("Killmails: Kill posting cap reached, newest kill id for {$kmGroup['name']} is {$updatedID}");
                        break;
                    }
                }
                setPermCache("{$kmGroup['corpID']}-{$kmGroup['allianceID']}-newestKillmailID", $killID);
            }
            $updatedID = getPermCache("{$kmGroup['corpID']}-{$kmGroup['allianceID']}-newestKillmailID");
            $this->logger->addInfo("Killmails: All kills posted, newest kill id for {$kmGroup['name']} is {$updatedID}");
            continue;
        }
    }

    private function getBigKM()
    {
        $killID = getPermCache('bigKillNewestKillmailID');
        if ($this->config['plugins']['getKillmails']['bigKills']['bigKillStartID'] > $killID || null === $killID) {
            $killID = $this->config['plugins']['getKillmails']['bigKills']['bigKillStartID'];
        }

        $url = "https://zkillboard.com/api/kills/orderDirection/asc/iskValue/10000000000/afterKillID/{$killID}/";

        $kills = json_decode(downloadData($url), true);
        $i = 0;
        if (isset($kills)) {
            foreach ($kills as $kill) {
                if ($i < 5) {
                    $killID = $kill['killID'];
                    $channelID = $this->config['plugins']['getKillmails']['bigKills']['bigKillChannel'];
                    $solarSystemID = $kill['solarSystemID'];
                    $systemName = systemName($solarSystemID);
                    $killTime = $kill['killTime'];
                    $victimAllianceName = $kill['victim']['allianceName'];
                    $victimName = $kill['victim']['characterName'];
                    $victimCorpName = $kill['victim']['corporationName'];
                    $victimShipID = $kill['victim']['shipTypeID'];
                    $shipName = apiTypeName($victimShipID);
                    $totalValue = number_format($kill['zkb']['totalValue']);
                    // Check if it's a structure
                    if ($victimName !== '') {
                        $msg = "**10b+ Kill Reported**\n\n**{$killTime}**\n\n**{$shipName}** worth **{$totalValue} ISK** flown by **{$victimName}** of (***{$victimCorpName}|{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                    } else {
                        $msg = "**10b+ Kill Reported**\n\n**{$killTime}**\n\n**{$shipName}** worth **{$totalValue} ISK** owned by (***{$victimCorpName}|{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                    }
                    if (!isset($msg)) { // Make sure it's always set.
                        return null;
                    }
                    queueMessage($msg, $channelID, $this->guild);
                    $this->logger->addInfo("Killmails: Mail {$killID} queued.");

                    $i++;
                } else {
                    $updatedID = getPermCache('bigKillNewestKillmailID');
                    $this->logger->addInfo("Killmails: bigKill posting cap reached, newest kill id is {$updatedID}");
                    break;
                }
            }
            $newID = $killID++;
            setPermCache('bigKillNewestKillmailID', $newID);
        }
        $updatedID = getPermCache('bigKillNewestKillmailID');
        $this->logger->addInfo("Killmails: All bigKills posted, newest kill id is {$updatedID}");
    }
}