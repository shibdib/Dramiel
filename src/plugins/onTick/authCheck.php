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

        //Set name check to happen if corpTicker or nameEnforce is set
        if ($this->nameEnforce === 'true' || $this->corpTickers === 'true') {
            $this->nameCheck = 'true';
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
            $namesChecked = getPermCache('nextRename');
            $standingsChecked = getPermCache('nextStandingsCheck');

            if ($permsChecked <= time()) {
                $this->logger->addInfo('AuthCheck: Checking for users who have left corp/alliance....');
                $this->checkPermissions();
                $this->logger->addInfo('AuthCheck: Corp/alliance check complete.');
            }

            if ($this->nameCheck === 'true' && $namesChecked <= time()) {
                $this->logger->addInfo('AuthCheck: Resetting player names....');
                $this->nameReset();
                $this->logger->addInfo('AuthCheck: Names reset.');
            }

            if ($this->standingsBased === 'true' && $standingsChecked <= time()) {
                $this->logger->addInfo('AuthCheck: Updating Standings');
                $this->standingsUpdate();
                $this->logger->addInfo('AuthCheck: Standings Updated');
            }
        }
    }

    // remove user roles
    private function removeRoles($member)
    {
        $discordNick = $member->nick ?: $member->username;
        $dbh = new PDO("mysql:host={$this->db};dbname={$this->dbName}", $this->dbUser, $this->dbPass);
        $rows = array_column($dbh->query('SELECT discordID, characterID, eveName, role FROM authUsers')->fetchAll(), null, 'discordID');
        $roleRemoved = false;
        if (null === $member->roles)
            continue;
        if (!isset($rows[$member->id])) {
            foreach ($member->roles as $role) {
                if (!in_array($role->name, $this->exempt, true)) {
                    $roleRemoved = true;
                    $member->removeRole($role);
                    $this->discord->guilds->get('id', $this->guildID)->members->save($member);
                }
            }
        }
        if ($roleRemoved) {
            $this->logger->addInfo("AuthCheck: Roles removed from $discordNick");
            $msg = "Discord roles removed from $discordNick";
            queueMessage($msg, $this->alertChannel, $this->guildID);
        }
    }
    /**
     * @return null
     */

    //Check user corp/alliance affiliation
    //Remove members who have roles but never authed
    private function checkPermissions()
    {
        $rows = array();
        $dbh = new PDO("mysql:host={$this->db};dbname={$this->dbName}", $this->dbUser, $this->dbPass);
        $rows = array_column($dbh->query('SELECT discordID, characterID, eveName, role FROM authUsers')->fetchAll(), null, 'discordID');

        //Set empty arrays
        $corpArray = array_column($this->authGroups, 'corpID');
        $allianceArray = array_column($this->authGroups, 'allianceID');

        foreach ($this->discord->guilds->get('id', $this->guildID)->members as $member) {
            $discordID = $member->getIdAttribute();
            $discordNick = $member->nick ?: $member->getUsernameAttribute();
            if ($member->getRolesAttribute()->isEmpty()) {
                continue;
            }
            if ($this->discord->id == $discordID) {
                continue;
            }
            $this->logger->addDebug("AuthCheck: Username: $discordNick");
            if (!isset($rows[$discordID])) {
                $this->logger->addInfo("AuthCheck: User [$discordNick] not found in database.");
                $this->removeRoles($member);
                continue;
            }
            $charID = $rows[$discordID]['characterID'];

            // corporation membership check
            for ($i=0; $i<3; $i++) {
                $character = characterDetails($charID);
                //if (isset($character['corporation_id']) && in_array($character['corporation_id'], $corpArray))
                if (!is_null($character))
                    break;
                //Postpone check if ESI is down to prevent timeouts
                if (isset($character['error']) && $character['error'] === 'The datasource tranquility is temporarily unavailable') {
                    $this->logger->addInfo('AuthCheck: The datasource tranquility is temporarily unavailable, check canceled.');
                    $nextCheck = time() + 10800;
                    setPermCache('permsLastChecked', $nextCheck);
                    return;
                }
            }
            if (is_null($character) || isset($character['error'])) {
                $this->logger->addInfo('AuthCheck: characterDetails lookup failed.');
                continue;
            } elseif (isset($character['corporation_id']) && in_array($character['corporation_id'], $corpArray)) {
                continue; // user is in a valid corporation, stop checking
            }

            // alliance membership check
            for ($i=0; $i<3; $i++) {
                $corporationDetails = corpDetails($corporationID);
                if (!is_null($corporationDetails))
                    break;
            }
            if (is_null($corporationDetails) || isset($corporationDetails['error'])) {
                $this->logger->addInfo('AuthCheck: corpDetails lookup failed.');
                continue;
            } else if (isset($corporationDetails['alliance_id']) && in_array($corporationDetails['alliance_id'], $allianceArray)) {
                continue; // user is in a valid alliance, stop checking
            }

            //check if user authed based on standings
            $role = $rows[$discordID]['role'];
            $standings = null;
            if ($role === 'blue' || 'neut' || 'red') {
                $allianceContacts = getContacts($allianceID);
                $corpContacts = getContacts($corporationID);
                if ($role === 'blue' && ((int) $allianceContacts['standing'] === 5 || 10 || (int) $corpContacts['standing'] === 5 || 10)) {
                    $standings = 1;
                }
                if ($role === 'red' && ((int) $allianceContacts['standing'] === -5 || -10 || (int) $corpContacts['standing'] === -5 || -10)) {
                    $standings = 1;
                }
                if ($role === 'neut' && ((int) $allianceContacts['standing'] === 0 || (int) $corpContacts['standing'] === 0 || (@(int) $allianceContacts['standings'] === null || '' && @(int) $corpContacts['standings'] === null || ''))) {
                    $standings = 1;
                }
            }
            if ($standings)
                continue; //keep the user based on standings

            // User failed all checks, deactivate user in database
            $sql = "UPDATE authUsers SET active='no' WHERE discordID='$discordID'";
            $this->logger->addInfo("AuthCheck: {$eveName} account has been deactivated as they are no longer in a correct corp/alliance.");
            $dbh->query($sql);
            $this->removeRoles($member);
        }
        setPermCache('permsLastChecked', time() + 1800);
    }

    private function nameReset()
    {
        //Get guild object
        $guild = $this->discord->guilds->get('id', $this->guildID);

        //Get name queue status
        $x = (int) getPermCache('nameQueueState');

        //Establish connection to mysql
        $conn = new mysqli($this->db, $this->dbUser, $this->dbPass, $this->dbName);
        $sql = "SELECT id FROM authUsers WHERE active='yes'";
        $count = $conn->query($sql);
        $rowAmount = round($count->num_rows / 2);
        if ($x === 1) {
            $sql = "SELECT characterID, discordID, eveName  FROM authUsers WHERE active='yes' ORDER BY id ASC LIMIT {$rowAmount} OFFSET {$rowAmount}";
            setPermCache('nameQueueState', 0);
        } else {
            $sql = "SELECT characterID, discordID, eveName  FROM authUsers WHERE active='yes' ORDER BY id ASC LIMIT {$rowAmount}";
            setPermCache('nameQueueState', 1);
        }
        $result = $conn->query($sql);

        if (@$result->num_rows >= 1) {
            while ($rows = $result->fetch_assoc()) {
                $charID = $rows['characterID'];
                $discordID = $rows['discordID'];
                $member = $guild->members->get('id', $discordID);
                $eveName = $rows['eveName'];
                //Check if member has roles
                if (null === @$member->roles) {
                    continue;
                }

                //Get current nickname
                $guild = $this->discord->guilds->get('id', $this->guildID);
                $member = $guild->members->get('id', $discordID);
                $nickName = $member->nick;
                $userName = $member->user->username;
                //If nick isn't set than make it username
                if ($nickName === '' || null === $nickName) {
                    $nickName = $userName;
                }

                //Check for bad tickers
                if (strpos($nickName, '[U]') !== false) {
                    $nickName = str_replace('[U]', '', $nickName);
                    queueRename($discordID, $nickName, $this->guildID);
                    continue;
                }

                //corp ticker
                if ($this->corpTickers === 'true') {
                    $timeout = 0;
                    $character = characterDetails($charID);
                    while (null === $character) { //try 10 times to pull characterDetails
                        if ($timeout > 9) {
                            continue;
                        }
                        else{
                            $character = characterDetails($charID);
                            $timeout++;
                        }
                    }
                    if (!array_key_exists('corporation_id', $character)) {
                        continue;
                    }
                    $corpInfo = getCorpInfo($character['corporation_id']);
                    //Clean bad entries
                    if (@$corpInfo['corpTicker'] === 'U') {
                        deleteCorpInfo(@$corpInfo['corpID']);
                    }
                    $nick = null;
                    if (null !== @$corpInfo['corpTicker']) {
                        $corpTicker = (string) $corpInfo['corpTicker'];
                        if ($this->nameEnforce === 'true') {
                            $nick = "[{$corpTicker}] {$eveName}";
                        } elseif ((string) $nickName === "[{$corpTicker}]") {
                            $nick = "[{$corpTicker}] {$userName}";
                        } elseif (strpos($nickName, $corpTicker) === false) {
                            $nick = "[{$corpTicker}] {$nickName}";
                        } elseif (strpos($nickName, $corpTicker) !== false) {
                            continue;
                        }
                        if ($nick !== $nickName) {
                            queueRename($discordID, $nick, $this->guildID);
                        }
                        continue;
                    }
                    $corporationDetails = corpDetails($character['corporation_id']);
                    if (null === $corporationDetails) {
                        continue;
                    }
                    $corpTicker = $corporationDetails['ticker'];
                    //Check for bad tickers (ESI ERROR?)
                    if (@$corpTicker === 'U') {
                        continue;
                    }
                    $corpName = (string) $corporationDetails['corporation_name'];
                    if (null !== $corpTicker) {
                        if ($this->nameEnforce === 'true') {
                            $nick = "[{$corpTicker}] {$eveName}";
                        } elseif ((string) $nickName === "[{$corpTicker}]") {
                            $nick = "[{$corpTicker}] {$userName}";
                        } elseif (strpos($nickName, $corpTicker) === false) {
                            $nick = "[{$corpTicker}] {$nickName}";
                        } elseif (strpos($nickName, $corpTicker) !== false) {
                            continue;
                        }
                        if ($nick !== $nickName) {
                            queueRename($discordID, $nick, $this->guildID);
                            addCorpInfo($character['corporation_id'], $corpTicker, $corpName);
                        }
                        continue;
                    }
                    continue;
                }
                $nick = "{$eveName}";
                if ($nick !== $nickName) {
                    queueRename($discordID, $nick, $this->guildID);
                }
                continue;
            }
            $nextCheck = time() + 1800;
            setPermCache('nextRename', $nextCheck);
            return null;
        }
        $nextCheck = time() + 1800;
        setPermCache('nextRename', $nextCheck);
        return null;

    }

    private function standingsUpdate()
    {
        foreach ($this->apiKey as $apiKey) {
            if ((string) $apiKey['keyID'] === (string) $this->config['plugins']['auth']['standings']['apiKey']) {
                $url = "https://api.eveonline.com/char/ContactList.xml.aspx?keyID={$apiKey['keyID']}&vCode={$apiKey['vCode']}&characterID={$apiKey['characterID']}";
                $xml = makeApiRequest($url);
                if (empty($xml)) {
                    return null;
                }
                foreach ($xml->result->rowset as $contactType) {
                    if ((string) $contactType->attributes()->name === 'corporateContactList' || 'allianceContactList') {
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
