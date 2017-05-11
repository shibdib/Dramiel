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
class getKillmailsOld
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
    public function init($config, $primary, $discord, $discordWeb, $logger)
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
            $SavedKillID = getPermCache("{$kmGroup['name']}newestKillmailID");
            //check if start id is greater than current id and if it is set
            if (null !== $SavedKillID && preg_match('/[a-z]/i', $SavedKillID)) {
                getStartMail($kmGroup);
                $SavedKillID = getPermCache("{$kmGroup['name']}newestKillmailID");
            }
            if ($kmGroup['startMail'] > $SavedKillID || null === $SavedKillID) {
                $SavedKillID = $kmGroup['startMail'];
            }
            if ((string)$kmGroup['allianceID'] === '0' & $kmGroup['lossMails'] === 'true') {
                $url = "https://zkillboard.com/api/corporationID/{$kmGroup['corpID']}/no-attackers/no-items/orderDirection/asc/pastSeconds/10800/";
            }
            if ((string)$kmGroup['allianceID'] === '0' & $kmGroup['lossMails'] === 'false') {
                $url = "https://zkillboard.com/api/corporationID/{$kmGroup['corpID']}/no-attackers/no-items/kills/orderDirection/asc/pastSeconds/10800/";
            }
            if ((string)$kmGroup['allianceID'] !== '0' & $kmGroup['lossMails'] === 'true') {
                $url = "https://zkillboard.com/api/allianceID/{$kmGroup['allianceID']}/no-attackers/no-items/orderDirection/asc/pastSeconds/10800/";
            }
            if ((string)$kmGroup['allianceID'] !== '0' & $kmGroup['lossMails'] === 'false') {
                $url = "https://zkillboard.com/api/allianceID/{$kmGroup['allianceID']}/no-attackers/no-items/kills/orderDirection/asc/pastSeconds/10800/";
            }

            if (!isset($url)) { // Make sure it's always set.
                $this->logger->addInfo('Killmails: ERROR - Ensure your config file is setup correctly for killmails.');
                return null;
            }

            $kills = json_decode(downloadData($url), true);
//            $i = 0;
            if (isset($kills)) {
                foreach ($kills as $kill) {
                    //                   if ($i < 10) {
                    //if big kill isn't set, disable it
                    if (!array_key_exists('bigKill', $kmGroup)) {
                        $kmGroup['bigKill'] = 99999999999999999999999999;
                    }
                    $killID = $kill['killID'];
                    if ($killID <= $SavedKillID) {
//                            $i++;
                        continue;
                    }
                    $channelID = $kmGroup['channel'];
                    $solarSystemID = $kill['solarSystemID'];
                    $systemName = getSystemName($solarSystemID);
                    $killTime = $kill['killTime'];
                    $victimAllianceName = '';
                    if ($kill['victim']['allianceName'] !== null && $kill['victim']['allianceName'] !== '') {
                        $victimAllianceName = "|{$kill['victim']['allianceName']}";
                    }
                    $victimName = $kill['victim']['characterName'];
                    $victimCorpName = $kill['victim']['corporationName'];
                    $victimShipID = $kill['victim']['shipTypeID'];
                    $shipName = getTypeName($victimShipID);
                    $rawValue = $kill['zkb']['totalValue'];
                    //Check if killmail meets minimum value and if it meets lost minimum value
                    if (isset($kmGroup['minimumValue']) && isset($kmGroup['minimumlossValue'])) {
                        if ($rawValue < $kmGroup['minimumValue']) {
                            if ($kill['victim']['corporationID'] == $kmGroup['corpID'] && $rawValue > $kmGroup['minimumlossValue'] ||
                                $kill['victim']['allianceID'] == $kmGroup['allianceID'] && $rawValue > $kmGroup['minimumlossValue']
                            ) {
                                $this->logger->addInfo("Killmails: Mail {$killID} posted because it meet minimum loss value required.");
                            } else {
                                $this->logger->addInfo("Killmails: Mail {$killID} ignored for not meeting the minimum value required.");
                                setPermCache("{$kmGroup['name']}newestKillmailID", $killID);
                                continue;
                            }
                        }
                    }
                    $totalValue = number_format($kill['zkb']['totalValue']);
                    // Check if it's a structure
                    if ($victimName !== '') {
                        if ($kmGroup['bigKill'] != null && $rawValue >= $kmGroup['bigKill']) {
                            $channelID = $kmGroup['bigKillChannel'];
                            $msg = "@here \n :warning:***Expensive Killmail***:warning: \n **{$killTime}**\n\n**{$shipName}** worth **{$totalValue} ISK** flown by **{$victimName}** of (***{$victimCorpName}{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                        } elseif ($kmGroup['bigKill'] == null || $rawValue <= $kmGroup['bigKill']) {
                            $msg = "**{$killTime}**\n\n**{$shipName}** worth **{$totalValue} ISK** flown by **{$victimName}** of (***{$victimCorpName}{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                        }
                    } elseif ($victimName === '') {
                        $msg = "**{$killTime}**\n\n**{$shipName}** worth **{$totalValue} ISK** owned by (***{$victimCorpName}{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                    }

                    if (!isset($msg)) { // Make sure it's always set.
                        return null;
                    }
                    queueMessage($msg, $channelID, $this->guild);
                    $this->logger->addInfo("Killmails: Mail {$killID} queued.");

//                        $i++;
                    /*                    } else {
                                            $updatedID = getPermCache("{$kmGroup['name']}newestKillmailID");
                                            $this->logger->addInfo("Killmails: Kill posting cap reached, newest kill id for {$kmGroup['name']} is {$updatedID}");
                                            break;
                                        }*/
                }
                if (null === $killID) {
                    $killID = $SavedKillID;
                }
                setPermCache("{$kmGroup['name']}newestKillmailID", $killID);
            }
            $updatedID = getPermCache("{$kmGroup['name']}newestKillmailID");
            $this->logger->addInfo("Killmails: All kills posted, newest kill id for {$kmGroup['name']} is {$updatedID}");
            continue;
        }
    }

    private function getBigKM()
    {
        $oldID = getPermCache('bigKillNewestKillmailID');
        if (null === $oldID || preg_match('/[a-z]/i', $oldID)) {
            getStartBigMail();
            $oldID = getPermCache('bigKillNewestKillmailID');
        }

        $url = 'https://zkillboard.com/api/kills/orderDirection/desc/iskValue/10000000000/limit/10/';

        $kills = json_decode(downloadData($url), true);
        $i = 0;
        if (isset($kills)) {
            foreach ($kills as $kill) {
                $cacheID = getPermCache('bigKillNewestKillmailID');
                //               if ($i < 10) {
                $killID = $kill['killID'];
                //check if mail is old
                if ((int)$killID <= (int)$oldID) {
                    continue;
                }
                //save highest killID for cache
                if ($killID > $cacheID) {
                    setPermCache('bigKillNewestKillmailID', $killID);
                }
                $channelID = $this->config['plugins']['getKillmails']['bigKills']['bigKillChannel'];
                $solarSystemID = $kill['solarSystemID'];
                $systemName = getSystemName($solarSystemID);
                $killTime = $kill['killTime'];
                $victimAllianceName = '';
                if ($kill['victim']['allianceName'] !== null && $kill['victim']['allianceName'] !== '') {
                    $victimAllianceName = "|{$kill['victim']['allianceName']}";
                }
                $victimName = $kill['victim']['characterName'];
                $victimCorpName = $kill['victim']['corporationName'];
                $victimShipID = $kill['victim']['shipTypeID'];
                $shipName = getTypeName($victimShipID);
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

                /*                    $i++;
                                } else {
                                    $cacheID = getPermCache('bigKillNewestKillmailID');
                                    $this->logger->addInfo("Killmails: bigKill posting cap reached, newest kill id is {$cacheID}");
                                    break;
                                }*/
            }
        }
        $updatedID = getPermCache('bigKillNewestKillmailID');
        $this->logger->addInfo("Killmails: All bigKills posted, newest kill id is {$updatedID}");
    }
}