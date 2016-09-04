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
        $this->nextCheck = 0;
        $lastCheck = getPermCache("authLastChecked");
        if ($lastCheck == NULL) {
            // Schedule it for right now if first run
            setPermCache("authLastChecked", time() - 5);
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
        $lastChecked = getPermCache("authLastChecked");
        $discord = $this->discord;

        if ($lastChecked <= time()) {
            $this->logger->addInfo("Checking authed users for changes....");
            $this->checkAuth($discord);
        }

    }

    /**
     *
     */
    function checkAuth($discord)
    {
        if (2 > 1) {
            $db = $this->config["database"]["host"];
            $dbUser = $this->config["database"]["user"];
            $dbPass = $this->config["database"]["pass"];
            $dbName = $this->config["database"]["database"];
            $id = $this->config["bot"]["guild"];
            $allyID = $this->config["plugins"]["auth"]["allianceID"];
            $corpID = $this->config["plugins"]["auth"]["corpID"];
            $exempt = $this->config["plugins"]["auth"]["exempt"];
            if(is_null($exempt)){
                $exempt = "0";
            }
            $toDiscordChannel = $this->config["plugins"]["auth"]["alertChannel"];
            $conn = new mysqli($db, $dbUser, $dbPass, $dbName);

            //get bot ID so we don't remove out own roles
            $botID = $this->discord->id;

            //Remove members who have roles but never authed
            $guild = $discord->guilds->get('id', $id);
            foreach($guild->members as $member) {
                $notifier = null;
                $id = $member->id;
                $username = $member->username;
                $roles = $member->roles;

                $sql = "SELECT * FROM authUsers WHERE discordID='$id' AND active='yes'";

                $result = $conn->query($sql);
                if($result->num_rows == 0) {
                    foreach ($roles as $role) {
                        if(!isset($role->name)){
                            if($id != $botID && !in_array($role->name, $exempt, true)){
                                $member->removeRole($role);
                                $guild->members->save($member);
                                // Send the info to the channel
                                $msg = "{$username} has been removed from the {$role->name} role as they never authed (Someone manually assigned them roles).";
                                $channelID = $toDiscordChannel;
                                $channel = $guild->channels->get('id', $channelID);
                                $channel->sendMessage($msg, false);
                                $this->logger->addInfo("{$username} has been removed from the {$role->name} role as they never authed.");
                            }
                        }
                    }
                }
            }

            $sql = "SELECT characterID, discordID, eveName FROM authUsers WHERE active='yes'";

            $result = $conn->query($sql);
            $num_rows = $result->num_rows;

            if ($num_rows >= 2) {
                while ($rows = $result->fetch_assoc()) {
                    $charID = $rows['characterID'];
                    $discordID = $rows['discordID'];
                    $member = $guild->members->get("id", $discordID);
                    $eveName = $rows['eveName'];
                    $roles = $member->roles;
                    $url = "https://api.eveonline.com/eve/CharacterAffiliation.xml.aspx?ids=$charID";
                    $xml = makeApiRequest($url);
                    if ($xml->result->rowset->row[0]) {
                        foreach ($xml->result->rowset->row as $character) {
                            if ($character->attributes()->allianceID != $allyID && $character->attributes()->corporationID != $corpID) {
                                foreach ($roles as $role) {
                                    $member->removeRole($role);
                                    $guild->members->save($member);
                                }

                                $statsURL = "https://beta.eve-kill.net/api/corpInfo/corporationID/" . urlencode($character->attributes()->corporationID) . "/";
                                $stats = json_decode(downloadData($statsURL), true);

                                if (empty($stats)) {
                                    $stats["corporationName"] = "Unknown";
                                }

                                $corporationName = @$stats["corporationName"];

                                // Send the info to the channel
                                $msg = "{$eveName} roles have been removed, user is now a member of **{$corporationName}**.";
                                $channelID = $toDiscordChannel;
                                $channel = $guild->channels->get('id', $channelID);
                                $channel->sendMessage($msg, false);
                                $this->logger->addInfo("{$eveName} roles ({$role}) have been removed, user is now a member of **{$corporationName}**.");

                                $sql = "UPDATE authUsers SET active='no' WHERE discordID='$discordID'";
                                $conn->query($sql);

                            }
                        }
                    }
                }
                $this->logger->addInfo("All users successfully authed.");
                $nextCheck = time() + 7200;
                setPermCache("authLastChecked", $nextCheck);
                if ($this->config["plugins"]["auth"]["nameEnforce"] == "true") {
                    while ($rows = $result->fetch_assoc()) {
                        $discordID = $rows['discordID'];
                        $eveName = $rows['eveName'];
                        $member = $guild->members->get("id", $discordID);
                        $discordName = $member->user->username;
                        if ($discordName != $eveName) {
                            foreach ($roles as $role) {
                                $member->removeRole($role);
                            }

                            // Send the info to the channel
                            $msg = $discordName . " roles have been removed because their name no longer matches their ingame name.";
                            $channelID = $toDiscordChannel;
                            $channel = $guild->channels->get('id', $channelID);
                            $channel->sendMessage($msg, false);
                            $this->logger->addInfo($discordName . " roles have been removed because their name no longer matches their ingame name.");

                            $sql = "UPDATE authUsers SET active='no' WHERE discordID='$discordID'";
                            $conn->query($sql);
                        }
                    }
                    $this->logger->addInfo("All users names have been checked.");
                    $cacheTimer = gmdate("Y-m-d H:i:s", $nextCheck);
                    $this->logger->addInfo("Next auth and name check at {$cacheTimer} EVE");
                    return null;
                }
                $cacheTimer = gmdate("Y-m-d H:i:s", $nextCheck);
                $this->logger->addInfo("Next auth and name check at {$cacheTimer} EVE");
                return null;
            }
            $this->logger->addInfo("No users found in database.");
            $nextCheck = time() + 7200;
            setPermCache("authLastChecked", $nextCheck);
            $cacheTimer = gmdate("Y-m-d H:i:s", $nextCheck);
            $this->logger->addInfo("Next auth and name check at {$cacheTimer} EVE");
            return null;
        }
        return null;
    }

    /**
     * @param $msgData
     */
    function onMessage($msgData)
    {
    }
}
 