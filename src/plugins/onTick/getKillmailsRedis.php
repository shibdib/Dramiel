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
 * Class getKillmailsRedis
 */
class getKillmailsRedis
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
     * @param $discord
     * @param $logger
     */
    public function init($config, $discord, $logger)
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
                setPermCache('killmailCheck', time() + 60);
                $this->logger->addInfo('Killmails: Killmail check complete.');
            }
        }
    }

    private function getKM()
    {
        $i = 0;
        while ($i < 25) {
            $kill = zKillRedis();
            $i++;

            //Check if there is a killmail available and mail is not null
            if(!is_array($kill)){
                break;
            }
            if (!array_key_exists('killID', $kill)) {
                break;
            }

            //Check if mail is a big kill
            if (@$kill['zkb']['totalValue'] >= 10000000000 && @$this->config['plugins']['getKillmails']['bigKills']['shareBigKills'] === 'true') {
                $killID = getPermCache('bigKillNewestKillmailID');
                if ($this->config['plugins']['getKillmails']['bigKills']['bigKillStartID'] > $killID || null === $killID) {
                    $killID = $this->config['plugins']['getKillmails']['bigKills']['bigKillStartID'];
                }
                $channelID = $this->config['plugins']['getKillmails']['bigKills']['bigKillChannel'];
                $killID = $kill['killmail']['killID'];
                $systemName = $kill['killmail']['solarSystem']['name'];
                $killTime = $kill['killmail']['killTime'];
                $victimAllianceName = @$kill['killmail']['victim']['alliance']['name'];
                $victimName = @$kill['killmail']['victim']['character']['name'];
                $victimCorpName = @$kill['killmail']['victim']['corporation']['name'];
                $shipName = $kill['killmail']['victim']['shipType']['name'];
                $totalValue = number_format($kill['zkb']['totalValue']);
                // Check if it's a structure
                if ($victimName !== '') {
                    $msg = "**10b+ Kill Reported**\n\n**{$killTime}**\n\n**{$shipName}** worth **{$totalValue} ISK** flown by **{$victimName}** of (***{$victimCorpName}|{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                } else {
                    $msg = "**10b+ Kill Reported**\n\n**{$killTime}**\n\n**{$shipName}** worth **{$totalValue} ISK** owned by (***{$victimCorpName}|{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                }
                if (null === $msg) { // Make sure it's always set.
                    return null;
                }
                queueMessage($msg, $channelID, $this->guild);
                $newID = $killID++;
                setPermCache('bigKillNewestKillmailID', $newID);
                $this->logger->addInfo("Killmails: BIG KILLMAIL Mail {$killID} queued.");
                continue;
            }

            foreach ($this->groupConfig as $kmGroup) {
                $killID = getPermCache("{$kmGroup['name']}newestKillmailID");

                //check if start id is greater than current id and if it is set
                if ($kmGroup['startMail'] > $killID || null === $killID) {
                    $killID = $kmGroup['startMail'];
                }

                //Check if id's are in the kill
                $corpLoss = false;
                $allianceLoss = false;
                $corpKill = false;
                $allianceKill = false;
                $attackerCorpArray = array();
                $attackerAllianceArray = array();

                foreach ($kill['killmail']['attackers'] as $attacker) {
                    $attackerCorpArray[] = (int) @$attacker['corporation']['id'];
                    $attackerAllianceArray[] = (int) @$attacker['alliance']['id'];
                }

                if ((int) @$kill['killmail']['victim']['corporation']['id'] === $kmGroup['corpID'] && (int) $kmGroup['corpID'] !== 0) {
                    $corpLoss = true;
                } elseif ((int) @$kill['killmail']['victim']['alliance']['id'] === $kmGroup['allianceID'] && (int) $kmGroup['allianceID'] !== 0) {
                    $allianceLoss = true;
                } elseif (in_array((int) $kmGroup['corpID'], $attackerCorpArray) && (int) $kmGroup['corpID'] !== 0) {
                    $corpKill = true;
                } elseif (in_array((int) $kmGroup['allianceID'], $attackerAllianceArray) && (int) $kmGroup['allianceID'] !== 0) {
                    $allianceKill = true;
                } else {
                    break;
                }

                //Check if it's a lossmail and lossmails are turned off
                if (($corpLoss === true || $allianceLoss === true) && $kmGroup['lossMails'] === 'false') {
                    break;
                }

                //if big kill isn't set, disable it
                if (null === $kmGroup['bigKill']) {
                    $kmGroup['bigKill'] = 99999999999999999999999999;
                }
                $killID = $kill['killmail']['killID'];
                $channelID = $kmGroup['channel'];
                $systemName = $kill['killmail']['solarSystem']['name'];
                $killTime = $kill['killmail']['killTime'];
                $victimAllianceName = @$kill['killmail']['victim']['alliance']['name'];
                $victimName = @$kill['killmail']['victim']['character']['name'];
                $victimCorpName = @$kill['killmail']['victim']['corporation']['name'];
                $shipName = @$kill['killmail']['victim']['shipType']['name'];
                $rawValue = $kill['zkb']['totalValue'];
                //Check if killmail is older than 2 days
                if ((time() - (60 * 60 * 48)) > DateTime::createFromFormat('Y.m.d H:i:s', $killTime)->getTimestamp()) {
                    continue;
                }
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
                    if ($kmGroup['bigKill'] != null && $rawValue >= $kmGroup['bigKill']) {
                        $channelID = $kmGroup['bigKillChannel'];
                        $msg = "@here \n :warning:***Expensive Killmail***:warning: \n **{$killTime}**\n\n**{$shipName}** worth **{$totalValue} ISK** flown by **{$victimName}** of (***{$victimCorpName}|{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                    } elseif ($kmGroup['bigKill'] == null || $rawValue <= $kmGroup['bigKill']) {
                        $msg = "**{$killTime}**\n\n**{$shipName}** worth **{$totalValue} ISK** flown by **{$victimName}** of (***{$victimCorpName}|{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                    }
                } elseif ($victimName === '') {
                    $msg = "**{$killTime}**\n\n**{$shipName}** worth **{$totalValue} ISK** owned by (***{$victimCorpName}|{$victimAllianceName}***) killed in {$systemName}\nhttps://zkillboard.com/kill/{$killID}/";
                }

                if (null === $msg) { // Make sure it's always set.
                    return null;
                }
                queueMessage($msg, $channelID, $this->guild);
                $this->logger->addInfo("Killmails: Mail {$killID} queued.");
                setPermCache("{$kmGroup['name']}newestKillmailID", $killID);
                break;
            }
        }
    }
}
