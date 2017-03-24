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
 * Class fileAuthCheck
 * @property int nextCheck
 */
class authCheck
{

    /**
     * @var
     */
    private $active;
    private $config;
    private $discord;
    private $logger;
    private $db;
    private $dbUser;
    private $dbPass;
    private $dbName;
    private $guildID;
    private $exempt;
    private $corpTickers;
    private $nameEnforce;
    private $standingsBased;
    private $apiKey;
    private $authGroups;
    private $alertChannel;
    private $nameCheck;
    private $dbusers;
    private $characterCache;
    private $corpCache;

    /**
     * @param $config
     * @param $discord
     * @param $logger
     */
    public function init($config, $discord, $logger)
    {
        $this->active = true;
        $this->config = $config;
        $this->discord = $discord;
        $this->logger = $logger;
        $this->db = $config['database']['host'];
        $this->dbUser = $config['database']['user'];
        $this->dbPass = $config['database']['pass'];
        $this->dbName = $config['database']['database'];
        $this->guildID = $config['bot']['guild'];
        $this->exempt = $config['plugins']['auth']['exempt'];
        $this->corpTickers = $config['plugins']['auth']['corpTickers'];
        $this->nameEnforce = $config['plugins']['auth']['nameEnforce'];
        $this->standingsBased = $config['plugins']['auth']['standings']['enabled'];
        $this->apiKey = $config['eve']['apiKeys'];
        $this->authGroups = $config['plugins']['auth']['authGroups'];
        $this->alertChannel = $config['plugins']['auth']['alertChannel'];
        $this->nextCheck = 0;
        $this->characterCache = array();
        $this->corpCache = array();

        //Set name check to happen if corpTicker or nameEnforce is set
        if ($this->nameEnforce === 'true' || $this->corpTickers === 'true') {
            $this->nameCheck = true;
        }

        //check if cache has been set
        $permsChecked = getPermCache('permsLastChecked');
        $namesChecked = getPermCache('nextRename');
        if ($namesChecked === NULL) {
            setPermCache('nextRename', time());
        }

        //if not set set for now (30 minutes from now for role removal)
        if ($permsChecked === NULL) {
            setPermCache('permsLastChecked', time() - 1);
        }

        // If config is outdated
        if (null === $this->authGroups) {
            $msg = '**Auth Failure:** Please update the bots config to the latest version.';
            queueMessage($msg, $this->alertChannel, $this->guildID);
            $this->logger->addInfo($msg);
            $this->active = false;
        }
    }

    public function tick()
    {
        // What was the servers last reported state
        $lastStatus = getPermCache('serverState');
        if ($this->active && $lastStatus === 'online') {
            $permsChecked = getPermCache('permsLastChecked');
            $standingsChecked = getPermCache('nextStandingsCheck');

            if ($permsChecked <= time()) {
                $this->logger->addInfo('AuthCheck: Checking for users who have left corp/alliance....');
                $this->checkPermissions();
                $this->logger->addInfo('AuthCheck: Corp/alliance check complete.');
            }

            if ($this->standingsBased === 'true' && $standingsChecked <= time()) {
                $this->logger->addInfo('AuthCheck: Updating Standings');
                $this->standingsUpdate();
                $this->logger->addInfo('AuthCheck: Standings Updated');
            }
        }
    }

    // remove user roles


    private function checkPermissions()
    {
        $this->characterCache = array();
        $this->corpCache = array();
        // Load database users
        $dbh = new PDO("mysql:host={$this->db};dbname={$this->dbName}", $this->dbUser, $this->dbPass);
        $this->dbusers = array_column($dbh->query("SELECT discordID, characterID, eveName, role FROM authUsers where active='yes'")->fetchAll(), null, 'discordID');
        if (!$this->dbusers) {
            return;
        }
        foreach ($this->discord->guilds->get('id', $this->guildID)->members as $member) {
            $discordNick = $member->nick ?: $member->user->username;
            if ($this->discord->id == $member->id || $member->getRolesAttribute()->isEmpty()) {
                continue;
            }
            $this->logger->addDebug("AuthCheck: Username: $discordNick");
            try {
                if (!($this->isMemberInValidCorp($member) || $this->isMemberInValidAlliance($member) || $this->isMemberCorpOrAllianceContact($member))) {
                    $this->deactivateMember($member);
                }
                if ($this->nameCheck) {
                    $this->resetMemberNick($member);
                }
            } catch (Exception $e) {
                $this->logger->addError("AuthCheck: " . $e->getMessage());
                if ($e->getMessage() == 'The datasource tranquility is temporarily unavailable') {
                    return;
                }
            }
        }
        setPermCache('permsLastChecked', time() + 1800);
    }

    private function isMemberInValidCorp($member)
    {
        $corpArray = array_column($this->authGroups, 'corpID');
        $discordID = $member->id;
        $return = false;
        if (isset($this->dbusers[$discordID])) {
            $character = $this->getCharacterDetails($this->dbusers[$discordID]['characterID']);
            $return = in_array($character['corporation_id'], $corpArray);
        }
        return $return;
    }

    private function getCharacterDetails($charID, $retries = 3)
    {
        if (isset($this->characterCache[$charID])) {
            return $this->characterCache[$charID];
        }
        $character = null;
        for ($i = 0; $i < $retries; $i++) {
            $character = characterDetails($charID);
            if (!is_null($character)) {
                break;
            }
        }
        if (!$character || isset($character['error'])) {
            $this->logger->addInfo('AuthCheck: characterDetails lookup failed.');
            $msg = isset($character['error']) ? $character['error'] : 'characterDetails lookup failed';
            throw new Exception($msg);
        }
        $this->characterCache[$charID] = $character;
        return $character;
    }

    private function isMemberInValidAlliance($member)
    {
        $allianceArray = array_column($this->authGroups, 'allianceID');
        $discordID = $member->id;
        $return = false;
        // get charID
        if (isset($this->dbusers[$discordID])) {
            $character = $this->getCharacterDetails($this->dbusers[$discordID]['characterID']);
            $corp = $this->getCorpDetails($character['corporation_id']);
            $return = isset($corp['alliance_id']) && in_array($corp['alliance_id'], $allianceArray);
        }
        return $return;
    }

    private function getCorpDetails($corpID, $retries = 3)
    {
        if (isset($this->corpCache[$corpID])) {
            return $this->corpCache[$corpID];
        }
        $corporationDetails = null;
        for ($i = 0; $i < $retries; $i++) {
            $corporationDetails = corpDetails($corpID);
            if (!is_null($corporationDetails)) {
                break;
            }
        }
        if (!$corporationDetails || isset($corporationDetails['error'])) {
            $this->logger->addInfo('AuthCheck: corpDetails lookup failed.');
            $msg = isset($corporationDetails['error']) ? $corporationDetails['error'] : 'corpDetails lookup failed';
            throw new Exception($msg);
        }
        $this->corpCache[$corpID] = $corporationDetails;
        return $corporationDetails;
    }

    private function isMemberCorpOrAllianceContact($member)
    {
        $return = false;
        $discordID = $member->id;
        if (isset($this->dbusers[$discordID])) {
            $role = $this->dbusers[$discordID]['role'];
            if ($role === 'blue' || 'neut' || 'red') {
                $character = $this->getCharacterDetails($this->dbusers[$discordID]['characterID']);
                $corporationDetails = $this->getCorpDetails($character['corporation_id']);
                $allianceContacts = getContacts($corporationDetails['alliance_id']);
                $corpContacts = getContacts($character['corporation_id']);
                if ($role === 'blue' && ((int)$allianceContacts['standing'] === 5 || 10 || (int)$corpContacts['standing'] === 5 || 10)) {
                    $return = true;
                }
                if ($role === 'red' && ((int)$allianceContacts['standing'] === -5 || -10 || (int)$corpContacts['standing'] === -5 || -10)) {
                    $return = true;
                }
                if ($role === 'neut' && ((int)$allianceContacts['standing'] === 0 || (int)$corpContacts['standing'] === 0 || (@(int)$allianceContacts['standings'] === null || '' && @(int)$corpContacts['standings'] === null || ''))) {
                    $return = true;
                }
            }
        }
        return $return;
    }

    private function deactivateMember($member)
    {
        $discordID = $member->id;
        $discordNick = $member->nick ?: $member->user->username;
        $this->logger->addInfo("AuthCheck: User [$discordNick] not found in database.");
        $dbh = new PDO("mysql:host={$this->db};dbname={$this->dbName}", $this->dbUser, $this->dbPass);
        $sql = "UPDATE authUsers SET active='no' WHERE discordID='$discordID'";
        $dbh->query($sql);
        unset($this->dbusers[$member->id]);
        $this->removeRoles($member);
    }

    private function removeRoles($member)
    {
        $discordNick = $member->nick ?: $member->user->username;
        $roleRemoved = false;
        $guild = $this->discord->guilds->get('id', $this->guildID);
        if (!is_null($member->roles)) {
            if (!isset($this->dbusers[$member->id])) {
                foreach ($member->roles as $role) {
                    if (!in_array($role->name, $this->exempt, true)) {
                        $roleRemoved = true;
                        $member->removeRole($role);
                        $guild->members->save($member);
                        break; //temp to see if an issue lies in removing multiple roles from the same person too quickly
                    }
                }
            }
            if ($roleRemoved) {
                $this->logger->addInfo("AuthCheck: Roles removed from $discordNick");
                $msg = "Discord roles removed from $discordNick";
                queueMessage($msg, $this->alertChannel, $this->guildID);
            }
        }
    }

    /**
     * @return null
     */

    //Check user corp/alliance affiliation
    //Remove members who have roles but never authed
    private function resetMemberNick($member)
    {
        $discordID = $member->id;
        $discordNick = $member->nick ?: $member->user->username;
        if (!isset($this->dbusers[$discordID])) {
            return false;
        }
        $character = $this->getCharacterDetails($this->dbusers[$discordID]['characterID']);
        $corpTicker = '';
        if ($this->corpTickers === 'true') {
            $corporationDetails = $this->getCorpDetails($character['corporation_id']);
            if ($corporationDetails && isset($corporationDetails['ticker']) && $corporationDetails['ticker'] !== 'U') {
                $corpTicker = "[{$corporationDetails['ticker']}] ";
            }
        }
        if ($this->nameEnforce) {
            $newNick = $corpTicker . $this->dbusers[$discordID]['eveName'];
        } else {
            $newNick = $corpTicker . $discordNick;
        }
        if ($newNick !== $discordNick) {
            queueRename($discordID, $newNick, $this->guildID);
        }
    }

    private function standingsUpdate()
    {
        foreach ($this->apiKey as $apiKey) {
            if ((string)$apiKey['keyID'] === (string)$this->config['plugins']['auth']['standings']['apiKey']) {
                $url = "https://api.eveonline.com/char/ContactList.xml.aspx?keyID={$apiKey['keyID']}&vCode={$apiKey['vCode']}&characterID={$apiKey['characterID']}";
                $xml = makeApiRequest($url);
                if (empty($xml)) {
                    return null;
                }
                foreach ($xml->result->rowset as $contactType) {
                    if ((string)$contactType->attributes()->name === 'corporateContactList' || 'allianceContactList') {
                        foreach ($contactType->row as $contact) {
                            if (null !== $contact['contactID'] && $contact['contactName'] && $contact['standing']) {
                                addContactInfo($contact['contactID'], $contact['contactName'], $contact['standing']);
                            }
                        }
                    }
                }
            }
        }
        $nextCheck = time() + 86400;
        setPermCache('nextStandingsCheck', $nextCheck);
    }
}