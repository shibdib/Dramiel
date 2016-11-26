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
    var $config;
    /**
     * @var
     */
    var $db;
    var $dbUser;
    var $dbPass;
    var $dbName;
    var $id;
    var $corpTickers;
    var $authGroups;
    var $exempt;
    var $alertChannel;
    var $guild;
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
        $this->db = $config["database"]["host"];
        $this->dbUser = $config["database"]["user"];
        $this->dbPass = $config["database"]["pass"];
        $this->dbName = $config["database"]["database"];
        $this->id = $config["bot"]["guild"];
        $this->exempt = $config["plugins"]["auth"]["exempt"];
        $this->corpTickers = $config["plugins"]["auth"]["corpTickers"];
        $this->authGroups = $config["plugins"]["auth"]["authGroups"];
        $this->alertChannel = $config["plugins"]["auth"]["alertChannel"];
        $this->guild = $config["bot"]["guild"];
        $this->nextCheck = 0;

        //check if cache has been set
        $permsChecked = getPermCache("permsLastChecked");

        //if not set set for now (30 minutes from now for role removal)
        if ($permsChecked == NULL) {
            setPermCache("permsLastChecked", time() - 5);
            setPermCache("authStateLastChecked", time() + 7200);
        }
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

    function tick()
    {
        // What was the servers last reported state
        $lastStatus = getPermCache("serverState");
        if ($lastStatus == "online") {
            $permsChecked = getPermCache("permsLastChecked");
            $stateChecked = getPermCache("authStateLastChecked");

            if ($permsChecked <= time()) {
                $this->logger->addInfo("AuthCheck: Checking for users who have left corp/alliance....");
                $this->checkPermissions();
                $this->logger->addInfo("AuthCheck: Corp/alliance check complete.");
            }

            if ($stateChecked <= time()) {
                $this->logger->addInfo("AuthCheck: Checking for users who have been wrongly given roles....");
                $this->checkAuthState();
                $this->logger->addInfo("AuthCheck: Role check complete.");
            }
        }
    }

    /**
     * @return null
     */

    //Remove members who have roles but never authed
    function checkPermissions()
    {
        //Get guild object
        $guild = $this->discord->guilds->get('id', $this->id);

        //Establish connection to mysql
        $conn = new mysqli($this->db, $this->dbUser, $this->dbPass, $this->dbName);

        $sql = "SELECT characterID, discordID, eveName FROM authUsers WHERE active='yes'";

        $result = $conn->query($sql);

        //Set empty arrays
        $corpArray = array();
        $allianceArray = array();

        // If config is outdated
        if (is_null($this->authGroups)) {
            $msg = "**Auth Failure:** Please update the bots config to the latest version.";
            queueMessage($msg, $this->alertChannel, $this->guild);
            $nextCheck = time() + 10800;
            setPermCache("permsLastChecked", $nextCheck);
            return null;
        }

        //Set corp/ally id arrays
        foreach ($this->authGroups as $authGroup) {
            if ($authGroup["corpID"] != 0) {
                array_push($corpArray, (int)$authGroup["corpID"]);
            }
            if ($authGroup["allianceID"] != 0) {
                array_push($allianceArray, (int)$authGroup["allianceID"]);
            }
        }

        if ($result->num_rows >= 1) {
            while ($rows = $result->fetch_assoc()) {
                $charID = $rows['characterID'];
                $discordID = $rows['discordID'];
                $member = $guild->members->get("id", $discordID);
                $eveName = $rows['eveName'];

                //Check if member has roles
                if (is_null($member->roles[0])) {
                    continue;
                }

                //Get ingame affiliations
                $url = "https://api.eveonline.com/eve/CharacterAffiliation.xml.aspx?ids=$charID";
                $xml = makeApiRequest($url);
                // Stop the process if the api is throwing an error
                if (is_null($xml)) {
                    $this->logger->addInfo("{$eveName} cannot be authed, API issues detected.");
                    return null;
                }
                //Auth things
                if ($xml->result->rowset->row[0]) {
                    foreach ($xml->result->rowset->row as $character) {
                        if (!in_array((int)$character->attributes()->allianceID, $allianceArray) && !in_array((int)$character->attributes()->corporationID, $corpArray)) {
                            // Deactivate user in database
                            $sql = "UPDATE authUsers SET active='no' WHERE discordID='$discordID'";
                            $this->logger->addInfo("AuthCheck: {$eveName} account has been deactivated as they are no longer in a correct corp/alliance.");
                            $conn->query($sql);
                        }
                    }
                }
            }
            $nextCheck = time() + 10800;
            setPermCache("permsLastChecked", $nextCheck);
            return null;
        }
        $nextCheck = time() + 10800;
        setPermCache("permsLastChecked", $nextCheck);
        return null;
    }

    //Check user corp/alliance affiliation


    function checkAuthState()
    {

        //Check if exempt roles are set
        if (is_null($this->exempt)) {
            $this->exempt = "0";
        }

        // If config is outdated
        if (is_null($this->authGroups)) {
            $msg = "**Auth Failure:** Please update the bots config to the latest version.";
            queueMessage($msg, $this->alertChannel, $this->guild);
            //queue up next check
            $nextCheck = time() + 1800;
            setPermCache("authStateLastChecked", $nextCheck);
            return null;
        }

        //Establish connection to mysql
        $conn = new mysqli($this->db, $this->dbUser, $this->dbPass, $this->dbName);

        //get bot ID so we don't remove out own roles
        $botID = $this->discord->id;

        //Get guild object
        $guild = $this->discord->guilds->get('id', $this->id);

        //Check to make sure guildID is set correctly
        if (is_null($guild)) {
            $this->logger->addError("Config Error: Ensure the guild entry in the config is the guildID (aka serverID) for the main server that the bot is in.");
            $nextCheck = time() + 7200;
            setPermCache("authLastChecked", $nextCheck);
            return null;
        }

        //create empty array to store names
        $removedRoles = array();
        $userCount = 0;

        //Perform check if roles were added without permission
        foreach ($guild->members as $member) {
            $id = $member->id;
            $username = $member->username;
            $roles = $member->roles;

            //Skip to next member if this user has no roles
            if (is_null($roles)) {
                continue;
            }
            $sql = "SELECT * FROM authUsers WHERE discordID='$id' AND active='yes'";
            $result = $conn->query($sql);

            //If they are NOT active in the db, check for roles to remove
            if ($result->num_rows == 0) {
                $userCount++;
                foreach ($roles as $role) {
                    if (!isset($role->name)) {
                        if ($id != $botID && !in_array($role->name, $this->exempt, true)) {
                            $member->removeRole($role);
                            $guild->members->save($member);
                            // Add users name to array
                            array_push($removedRoles, $username);
                        }
                    }
                }
            }
        }
        //Report removed users to log and channel
        $nameList = implode(", ", $removedRoles);
        if ($userCount > 0 && strlen($nameList) > 3 && !is_null($nameList)) {
            $msg = "Following users roles have been removed - {$nameList}";
            queueMessage($msg, $this->alertChannel, $this->guild);
            $this->logger->addInfo("AuthCheck: Roles removed from {$nameList}");
        }
        //queue up next check
        $nextCheck = time() + 1800;
        setPermCache("authStateLastChecked", $nextCheck);
        return null;
    }
}
