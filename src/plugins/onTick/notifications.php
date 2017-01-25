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
 * Class notifications
 * @property string allianceOnly
 */
class notifications
{
    public $config;
    public $discord;
    public $logger;
    private $toDiscordChannel;
    private $newestNotificationID;
    private $maxID;
    private $apiKey;
    private $numberOfKeys;
    private $fuelChannel;
    private $fuelSkip;
    private $guild;

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
        $this->toDiscordChannel = $config['plugins']['notifications']['channelID'];
        $this->allianceOnly = strtolower($config['plugins']['notifications']['allianceOnly']);
        $this->fuelChannel = $config['plugins']['fuel']['channelID'];
        $this->fuelSkip = $config['plugins']['fuel']['skip'];
        $this->newestNotificationID = getPermCache('newestNotificationID');
        $this->maxID = 0;
        $this->apiKey = $config['eve']['apiKeys'];
        $this->guild = $config['bot']['guild'];

        //check if allianceOnly is set
        if (null === $this->allianceOnly) {
            $this->allianceOnly = 'false';
        }

        //Get number of keys
        $x = 0;
        foreach ($this->apiKey as $apiKey) {
            //Check if api is set
            if ($apiKey['keyID'] === '' || $apiKey['vCode'] === '' || $apiKey['characterID'] === null) {
                continue;
            }
            $x++;
        }
        $this->numberOfKeys = $x;
    }

    /**
     *
     */
    public function tick()
    {
        // What was the servers last reported state
        $lastStatus = getPermCache('serverState');
        if ($lastStatus === 'online') {
            foreach ($this->apiKey as $apiKey) {
                //Check if api is set
                if ($apiKey['keyID'] === '' || $apiKey['vCode'] === '' || $apiKey['characterID'] === null) {
                    continue;
                }
                //Get
                $lastChecked = getPermCache('notificationsLastChecked');
                if ($lastChecked <= time()) {
                    $lastCheckedAPI = getPermCache("notificationsLastChecked{$apiKey['keyID']}");
                    if ($lastCheckedAPI <= time()) {
                        $this->logger->addInfo("Notifications: Checking API Key {$apiKey['keyID']} for notifications..");
                        $this->getNotifications($apiKey['keyID'], $apiKey['vCode'], $apiKey['characterID']);
                    }
                }
            }
        }
    }

    /**
     * @param $keyID
     * @param $vCode
     * @param $characterID
     * @return null
     */
    private function getNotifications($keyID, $vCode, $characterID)
    {
        try {
            $url = "https://api.eveonline.com/char/Notifications.xml.aspx?keyID={$keyID}&vCode={$vCode}&characterID={$characterID}";
            $xml = makeApiRequest($url);
            date_default_timezone_set('UTC');
            $cached = $xml->cachedUntil[0];
            $baseUnix = strtotime($cached);
            $cacheClr = $baseUnix - 13500;
            if (null === $this->fuelChannel) {
                $this->fuelChannel = $this->toDiscordChannel;
            }
            //Set timer for next key based on number of keys
            $nextKey = (1900 / (int)$this->numberOfKeys) + time();
            $nextKeyTime = gmdate('Y-m-d H:i:s', $nextKey);
            setPermCache('notificationsLastChecked', $nextKey);
            //Set cache timer for api key
            if ($cacheClr <= time()) {
                $weirdTime = time() + 1830;
                setPermCache("notificationsLastChecked{$keyID}", $weirdTime);
            } else {
                setPermCache("notificationsLastChecked{$keyID}", $cacheClr);
            }
            $data = json_decode(json_encode(simplexml_load_string(downloadData($url),
                'SimpleXMLElement', LIBXML_NOCDATA)), true);
            // If there is no data, just quit..
            if (empty($data['result']['rowset']['row'])) {
                return null;
            }
            $data = $data['result']['rowset']['row'];
            $fixedData = array();
            // Sometimes there is only ONE notification, so.. yeah..
            if (isset($data['@attributes'])) {
                $fixedData[] = $data['@attributes'];
            }
            if (count($data) > 1) {
                foreach ($data as $multiNotif) {
                    $fixedData[] = $multiNotif['@attributes'];
                }
            }
            foreach ($fixedData as $notification) {
                $notificationID = $notification['notificationID'];
                $typeID = $notification['typeID'];
                $sentDate = $notification['sentDate'];
                $channelID = $this->toDiscordChannel;
                if ($notificationID > $this->newestNotificationID) {
                    $notificationString = $this->getNotificationText($keyID, $vCode, $characterID, $notificationID);
                    $notificationArray = explode("\n", $this->getNotificationText($keyID, $vCode, $characterID, $notificationID));
                    switch ($typeID) {
                        case 1: // Old
                            $msg = 'skip';
                            break;
                        case 2: // biomassed
                            $msg = 'skip';
                            break;
                        case 3: // medal awarded
                            $msg = 'skip';
                            break;
                        case 4: // alliance bill
                            $msg = 'skip';
                            break;
                        case 5: // War Declared
                            $result = preg_split('/againstID:/', $notificationString);
                            if (count($result) > 1) {
                                $result_split = explode(' ', $result[1]);
                                $defAllianceID = $result_split[1];
                            }
                            $defAllianceName = allianceName($defAllianceID);
                            if ($defAllianceName === 'Unknown') {
                                $defAllianceName = corpName($defAllianceID);
                            }
                            $result = preg_split('/declaredByID:/', $notificationString);
                            if (count($result) > 1) {
                                $result_split = explode(' ', $result[1]);
                                $aggAllianceID = $result_split[1];
                            }
                            $aggAllianceName = allianceName($aggAllianceID);
                            if ($aggAllianceName === 'Unknown') {
                                $aggAllianceName = corpName($aggAllianceID);
                            }
                            if ($aggAllianceName === null || '' || $defAllianceName === null || '') {
                                $msg = '@everyone | War declared. Fighting begins in roughly 24 hours.';
                            } else {
                                $msg = "@everyone | War declared by {$aggAllianceName} against {$defAllianceName}. Fighting begins in roughly 24 hours.";
                            }
                            break;
                        case 6: // Corp joins war (Not enough info in api to say who the 3rd party is)
                            $defAllianceID = trim(explode(': ', $notificationArray[0])[1]);
                            $aggAllianceID = trim(explode(': ', $notificationArray[2])[1]);
                            $defAllianceName = allianceName($defAllianceID);
                            $aggAllianceName = allianceName($aggAllianceID);
                            $msg = "The war between {$aggAllianceName} and {$defAllianceName} has been joined by a third party. This new group may begin fighting in roughly 24 hours.";
                            break;
                        case 7: // War Declared corp
                            $defCorpID = trim(explode(': ', $notificationArray[0])[1]);
                            $aggCorpID = trim(explode(': ', $notificationArray[2])[1]);
                            $defCorpName = corpName($defCorpID);
                            $aggCorpName = corpName($aggCorpID);
                            $msg = "@everyone | War declared by {$aggCorpName} against {$defCorpName}. Fighting begins in roughly 24 hours.";
                            break;
                        case 8: // Alliance war invalidated by CONCORD
                            $result = preg_split('/againstID:/', $notificationString);
                            if (count($result) > 1) {
                                $result_split = explode(' ', $result[1]);
                                $defAllianceID = $result_split[1];
                            }
                            $defAllianceName = allianceName($defAllianceID);
                            if ($defAllianceName === 'Unknown') {
                                $defAllianceName = corpName($defAllianceID);
                            }
                            $result = preg_split('/declaredByID:/', $notificationString);
                            if (count($result) > 1) {
                                $result_split = explode(' ', $result[1]);
                                $aggAllianceID = $result_split[1];
                            }
                            $aggAllianceName = allianceName($aggAllianceID);
                            if ($aggAllianceName === 'Unknown') {
                                $aggAllianceName = corpName($aggAllianceID);
                            }
                            if ($aggAllianceName === null || '' || $defAllianceName === null || '') {
                                $msg = 'The war has been invalidated. Fighting ends in roughly 24 hours.';
                            } else {
                                $msg = "The war between {$aggAllianceName} and {$defAllianceName} has been invalidated. Fighting ends in roughly 24 hours.";
                            }
                            break;
                        case 9: // Pilot bill
                            $msg = 'skip';
                            break;
                        case 10: // Bill issued
                            $msg = 'skip';
                            break;
                        case 11: // Bill stuff
                            $msg = 'skip';
                            break;
                        case 12: // Bill Stuff
                            $msg = 'skip';
                            break;
                        case 13: // Bill issued
                            $msg = 'skip';
                            break;
                        case 14: // Bounty payment
                            $msg = 'skip';
                            break;
                        case 16: // Mail
                            $msg = 'skip';
                            break;
                        case 17: // Corp app
                            $msg = 'skip';
                            break;
                        case 18: // Corp app
                            $msg = 'skip';
                            break;
                        case 19: // corp tax changed
                            $corpID = trim(explode(': ', $notificationArray[0])[1]);
                            $corpName = corpName($corpID);
                            $oldTax = trim(explode(': ', $notificationArray[2])[1]);
                            $newTax = trim(explode(': ', $notificationArray[1])[1]);
                            $msg = "{$corpName} tax changed from {$oldTax}% to {$newTax}%";
                            if ($this->allianceOnly === 'true') {
                                $msg = 'skip';
                            }
                            break;
                        case 20: // Corp news?
                            $msg = 'skip';
                            break;
                        case 21: // member left corp
                            $msg = 'skip';
                            break;
                        case 31: // Alliance war invalidated by CONCORD
                            $aggAllianceID = trim(explode(': ', $notificationArray[2])[1]);
                            $aggAllianceName = allianceName($aggAllianceID);
                            $msg = "War with {$aggAllianceName} has been invalidated. Fighting ends in roughly 24 hours.";
                            break;
                        case 34: // Noob ship
                            $msg = 'skip';
                            break;
                        case 35: // Insurance payment
                            $msg = 'skip';
                            break;
                        case 41: // System lost
                            $solarSystemID = trim(explode(': ', $notificationArray[2])[1]);
                            $systemName = systemName($solarSystemID);
                            $allianceID = trim(explode(': ', $notificationArray[0])[1]);
                            $allianceName = allianceName($allianceID);
                            $msg = "{$allianceName} has lost control of **{$systemName}**";
                            break;
                        case 43: // System captured
                            $solarSystemID = trim(explode(': ', $notificationArray[2])[1]);
                            $systemName = systemName($solarSystemID);
                            $allianceID = trim(explode(': ', $notificationArray[0])[1]);
                            $allianceName = allianceName($allianceID);
                            $msg = "{$allianceName} now controls **{$systemName}**";
                            break;
                        case 45: // Tower anchoring
                            $msg = 'skip';
                            break;
                        case 50: // Office Expired
                            $msg = 'skip';
                            break;
                        case 52: // clone revoked
                            $msg = 'skip';
                            break;
                        case 54: // insurance
                            $msg = 'skip';
                            break;
                        case 55: // insurance
                            $msg = 'skip';
                            break;
                        case 57: // jump clone destruction
                            $msg = 'skip';
                            break;
                        case 69: // agent info
                            $msg = 'skip';
                            break;
                        case 71: // Mission Expiration
                            $msg = 'skip';
                            break;
                        case 73: // special mission
                            $msg = 'skip';
                            break;
                        case 75: // POS / POS Module under attack
                            $aggAllianceID = trim(explode(': ', $notificationArray[0])[1]);
                            $aggAllianceName = allianceName($aggAllianceID);
                            $aggCorpID = trim(explode(': ', $notificationArray[1])[1]);
                            $aggCorpName = corpName($aggCorpID);
                            $aggID = trim(explode(': ', $notificationArray[2])[1]);
                            $aggCharacterName = characterName($aggID);
                            $moonID = trim(explode(': ', $notificationArray[5])[1]);
                            $moonName = apiMoonName($moonID);
                            $solarSystemID = trim(explode(': ', $notificationArray[7])[1]);
                            $typeID = trim(explode(': ', $notificationArray[8])[1]);
                            $typeName = apiTypeName($typeID);
                            $systemName = systemName($solarSystemID);
                            $msg = "**{$typeName}** under attack in **{$systemName} - {$moonName}** by {$aggCharacterName} ({$aggCorpName} / {$aggAllianceName}).";
                            if ($this->allianceOnly === 'true') {
                                $msg = 'skip';
                            }
                            break;
                        case 76: // Tower resource alert
                            $moonID = trim(explode(': ', $notificationArray[2])[1]);
                            $moonName = apiMoonName($moonID);
                            $solarSystemID = trim(explode(': ', $notificationArray[3])[1]);
                            $systemName = systemName($solarSystemID);
                            $blocksRemaining = trim(explode(': ', $notificationArray[6])[1]);
                            $typeID = trim(explode(': ', $notificationArray[7])[1]);
                            $channelID = $this->fuelChannel;
                            $typeName = apiTypeName($typeID);
                            $msg = "POS in {$systemName} - {$moonName} needs fuel. Only {$blocksRemaining} {$typeName}'s remaining.";
                            if ($this->fuelSkip === 'true' || $this->allianceOnly === 'true') {
                                $msg = 'skip';
                            }

                            break;
                        case 88: // IHUB is being attacked
                            $aggAllianceID = trim(explode(': ', $notificationArray[0])[1]);
                            $aggAllianceName = allianceName($aggAllianceID);
                            $aggCorpID = trim(explode(': ', $notificationArray[0])[1]);
                            $aggCorpName = corpName($aggCorpID);
                            $aggID = trim(explode(': ', $notificationArray[1])[1]);
                            $aggCharacterName = characterName($aggID);
                            $armorValue = trim(explode(': ', $notificationArray[3])[1]);
                            $hullValue = trim(explode(': ', $notificationArray[4])[1]);
                            $shieldValue = trim(explode(': ', $notificationArray[5])[1]);
                            $solarSystemID = trim(explode(': ', $notificationArray[6])[1]);
                            $systemName = systemName($solarSystemID);
                            $msg = "IHUB under attack in **{$systemName}** by {$aggCharacterName} ({$aggCorpName} / {$aggAllianceName}). Status: Hull: {$hullValue}, Armor: {$armorValue}, Shield: {$shieldValue}";
                            break;
                        case 89: // Sov level?
                            $msg = 'skip';
                            break;
                        case 90: // Sov level?
                            $msg = 'skip';
                            break;
                        case 93: // Customs office is being attacked
                            $aggAllianceID = trim(explode(': ', $notificationArray[0])[1]);
                            $aggAllianceName = allianceName($aggAllianceID);
                            $aggCorpID = trim(explode(': ', $notificationArray[0])[1]);
                            $aggCorpName = corpName($aggCorpID);
                            $aggID = trim(explode(': ', $notificationArray[2])[1]);
                            $aggCharacterName = characterName($aggID);
                            $shieldValue = trim(explode(': ', $notificationArray[5])[1]);
                            $solarSystemID = trim(explode(': ', $notificationArray[6])[1]);
                            $systemName = systemName($solarSystemID);
                            $msg = "Customs Office under attack in **{$systemName}** by {$aggCharacterName} ({$aggCorpName} / {$aggAllianceName}). Shield Status: {$shieldValue}";
                            if ($this->allianceOnly === 'true') {
                                $msg = 'skip';
                            }
                            break;
                        case 94: // POCO Reinforced
                            $msg = 'Customs Office reinforced.';
                            break;
                        case 95: // IHub Transfer
                            $msg = 'skip';
                            break;
                        case 100: // Aggressor corp joins war
                            $aggCorpID = trim(explode(': ', $notificationArray[0])[1]);
                            $defCorpID = trim(explode(': ', $notificationArray[1])[1]);
                            $aggCorpName = corpName($aggCorpID);
                            $defCorpName = corpName($defCorpID);
                            $msg = "{$aggCorpName} has joined the war against {$defCorpName}.";
                            break;
                        case 101: // corp joins war
                            $result = preg_split('/defenderID:/', $notificationString);
                            if (count($result) > 1) {
                                $result_split = explode(' ', $result[1]);
                                $defCorpID = $result_split[1];
                            }
                            $defCorpName = allianceName($defCorpID);
                            if ($defCorpName === 'Unknown') {
                                $defCorpName = corpName($defCorpID);
                            }
                            $result = preg_split('/aggressorID:/', $notificationString);
                            if (count($result) > 1) {
                                $result_split = explode(' ', $result[1]);
                                $aggCorpID = $result_split[1];
                            }
                            $aggCorpName = allianceName($aggCorpID);
                            if ($aggCorpName === 'Unknown') {
                                $aggCorpName = corpName($aggCorpID);
                            }
                            $result = preg_split('/allyID:/', $notificationString);
                            if (count($result) > 1) {
                                $result_split = explode(' ', $result[1]);
                                $thirdCorpID = $result_split[1];
                            }
                            $thirdCorpName = allianceName($thirdCorpID);
                            if ($thirdCorpName === 'Unknown') {
                                $thirdCorpName = corpName($thirdCorpID);
                            }
                            $msg = "{$thirdCorpName} has joined the war involving {$defCorpName} and {$aggCorpName}.";
                            break;
                        case 102: // War support offer? I think?
                            $msg = 'skip';
                            break;
                        case 103: // War support offer? I think?
                            $msg = 'skip';
                            break;
                        case 111: // Bounty
                            $msg = 'skip';
                            break;
                        case 112: // Bounty
                            $msg = 'skip';
                            break;
                        case 113: // Bounty
                            $msg = 'skip';
                            break;
                        case 114: // Bounty
                            $msg = 'skip';
                            break;
                        case 115: // Kill right
                            $msg = 'skip';
                            break;
                        case 116: // Kill right
                            $msg = 'skip';
                            break;
                        case 117: // Kill right
                            $msg = 'skip';
                            break;
                        case 118: // Kill right
                            $msg = 'skip';
                            break;
                        case 119: // Kill right
                            $msg = 'skip';
                            break;
                        case 120: // Kill right
                            $msg = 'skip';
                            break;
                        case 128: // Corp App
                            $msg = 'skip';
                            break;
                        case 129: // App denied
                            $msg = 'skip';
                            break;
                        case 130: // Corp app withdrawn?
                            $msg = 'skip';
                            break;
                        case 135: // ESS stolen
                            $msg = 'skip';
                            break;
                        case 138: // Clone activation
                            $msg = 'skip';
                            break;
                        case 139: // Clone activation
                            $msg = 'skip';
                            break;
                        case 140: // Kill report
                            $msg = 'skip';
                            break;
                        case 141: // Kill report
                            $msg = 'skip';
                            break;
                        case 147: // Entosis has started
                            $solarSystemID = trim(explode(': ', $notificationArray[0])[1]);
                            $systemName = systemName($solarSystemID);
                            $typeID = trim(explode(': ', $notificationArray[1])[1]);
                            $typeName = apiTypeName($typeID);
                            $msg = "Entosis has started in **{$systemName}** on **{$typeName}** (Date: **{$sentDate}**)";
                            break;
                        case 148: // Entosis enabled a module ??????
                            $solarSystemID = trim(explode(': ', $notificationArray[0])[1]);
                            $systemName = systemName($solarSystemID);
                            $typeID = trim(explode(': ', $notificationArray[1])[1]);
                            $typeName = apiTypeName($typeID);
                            $msg = "Entosis has enabled a module in **{$systemName}** on **{$typeName}** (Date: **{$sentDate}**)";
                            break;
                        case 149: // Entosis disabled a module
                            $solarSystemID = trim(explode(': ', $notificationArray[0])[1]);
                            $systemName = systemName($solarSystemID);
                            $typeID = trim(explode(': ', $notificationArray[1])[1]);
                            $typeName = apiTypeName($typeID);
                            $msg = "Entosis has disabled a module in **{$systemName}** on **{$typeName}** (Date: **{$sentDate}**)";
                            break;
                        case 152: // Sov bill
                            $msg = 'skip';
                            break;
                        case 160: // Entosis successful
                            $solarSystemID = trim(explode(': ', $notificationArray[2])[1]);
                            $systemName = systemName($solarSystemID);
                            $msg = "Hostile entosis successful. A structure in **{$systemName}** has entered reinforced mode.";
                            break;
                        case 161: //  Command Nodes Decloaking
                            $solarSystemID = trim(explode(': ', $notificationArray[2])[1]);
                            $systemName = systemName($solarSystemID);
                            $msg = "Command nodes decloaking for **{$systemName}**";
                            break;
                        case 162: //  TCU Destroyed
                            $solarSystemID = trim(explode(': ', $notificationArray[0])[1]);
                            $systemName = systemName($solarSystemID);
                            $msg = "Entosis successful, TCU in **{$systemName}** has been destroyed.";
                            break;
                        case 163: //  Outpost freeport
                            $solarSystemID = trim(explode(': ', $notificationArray[1])[1]);
                            $systemName = systemName($solarSystemID);
                            $msg = "Station in **{$systemName}** has now entered freeport mode.";
                            break;
                        case 165: //  System became Capital
                            $solarSystemID = trim(explode(': ', $notificationArray[1])[1]);
                            $systemName = systemName($solarSystemID);
                            $allianceID = trim(explode(': ', $notificationArray[0])[1]);
                            $allianceName = allianceName($allianceID);
                            $msg = "**{$systemName}** is now the capital for {$allianceName}.";
                            break;
                        case 167: //  Initiate Self-destruct on TCU
                            $solarSystemID = trim(explode(': ', $notificationArray[3])[1]);
                            $systemName = systemName($solarSystemID);
                            $msg = "TCU in **{$systemName}** has initiated a self destruct sequence.";
                            break;
                        case 169: //  TCU Self-Destructed
                            $solarSystemID = trim(explode(': ', $notificationArray[0])[1]);
                            $systemName = systemName($solarSystemID);
                            $msg = "TCU in **{$systemName}** has self destructed.";
                            break;
                        case 181: // citadel low on fuel
                            $result = preg_split('/solarsystemID:/', $notificationString);
                            if (count($result) > 1) {
                                $result_split = explode(' ', $result[1]);
                                $solarSystemID = $result_split[1];
                            }
                            $systemName = systemName($solarSystemID);
                            $msg = "Citadel in **{$systemName}** is low on fuel.";
                            if ($this->fuelSkip === 'true' || $this->allianceOnly === 'true') {
                                $msg = 'skip';
                            }
                            break;
                        case 182: //  Citadel being anchored
                            $corpName = trim(explode(': ', $notificationArray[1])[1]);
                            $solarSystemID = trim(explode(': ', $notificationArray[2])[1]);
                            $systemName = systemName($solarSystemID);
                            $msg = "Citadel owned by **{$corpName}** is being anchored in **{$systemName}**.";
                            break;
                        case 184: //  Citadel under attack
                            $aggID = trim(explode(': ', $notificationArray[7])[1]);
                            $aggCharacterName = characterName($aggID);
                            $solarSystemID = trim(explode(': ', $notificationArray[15])[1]);
                            $aggAllianceID = trim(explode(': ', $notificationArray[0])[1]);
                            $aggAllianceName = allianceName($aggAllianceID);
                            $aggCorpID = trim(explode('- ', $notificationArray[11])[1]);
                            $aggCorpName = corpName($aggCorpID);
                            $systemName = systemName($solarSystemID);
                            $msg = "@everyone | Citadel under attack in **{$systemName}** by **{$aggCharacterName}** ({$aggCorpName} / {$aggAllianceName}).";
                            break;
                        case 185: //  Citadel online
                            $solarSystemID = trim(explode(': ', $notificationArray[0])[1]);
                            $systemName = systemName($solarSystemID);
                            $msg = "Citadel now online in **{$systemName}**.";
                            break;
                        case 188: //  Citadel destroyed
                            $corpID = trim(explode('- ', $notificationArray[3])[1]);
                            $corpName = corpName($corpID);
                            $solarSystemID = trim(explode(': ', $notificationArray[5])[1]);
                            $systemName = systemName($solarSystemID);
                            $msg = "Citadel owned by **{$corpName}** in **{$systemName}** has been destroyed.";
                            break;
                        case 198: // citadel out of fuel
                            $result = preg_split('/solarsystemID:/', $notificationString);
                            if (count($result) > 1) {
                                $result_split = explode(' ', $result[1]);
                                $solarSystemID = $result_split[1];
                            }
                            $systemName = systemName($solarSystemID);
                            $msg = "Citadel in **{$systemName}** has run out of fuel.";
                            if ($this->fuelSkip === 'true' || $this->allianceOnly === 'true') {
                                $msg = 'skip';
                            }
                            break;
                        case 199: // citadel delivery
                            $msg = 'skip';
                            break;
                        case 1030: // ??
                            $msg = 'skip';
                            break;
                        case 1031: // plex delivery
                            $msg = 'skip';
                            break;
                        default: // Unknown typeID
                            $string = implode(' ', $notificationArray);
                            $msg = "typeID {$typeID} is an unmapped notification, please create a Github issue with this entire message and please include what the in-game notification is. {$string}";
                            break;
                    }

                    if ($msg === 'skip') {
                        return null;
                    }
                    $this->logger->addInfo('Notifications: Notification queued');
                    priorityQueueMessage($msg, $channelID, $this->guild);
                    // Find the maxID so we don't output this message again in the future
                    $this->maxID = max($notificationID, $this->maxID);
                    $this->newestNotificationID = $this->maxID;
                    setPermCache('newestNotificationID', $this->maxID);
                }
            }
            $this->logger->addInfo("Notifications: Next Notification Check At: {$nextKeyTime} EVE Time");
        } catch (Exception $e) {
            $this->logger->addInfo('Notifications: Notification Error: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * @param $keyID
     * @param $vCode
     * @param $characterID
     * @param $notificationID
     * @return string
     */
    private function getNotificationText($keyID, $vCode, $characterID, $notificationID)
    {
        $url = "https://api.eveonline.com/char/NotificationTexts.xml.aspx?keyID={$keyID}&vCode={$vCode}&characterID={$characterID}&IDs={$notificationID}";
        $data = json_decode(json_encode(simplexml_load_string(downloadData($url),
            'SimpleXMLElement', LIBXML_NOCDATA)), true);
        $data = $data['result']['rowset']['row'];
        return $data;
    }
}
