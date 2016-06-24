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
 * Class auth
 */
class auth
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
    var $solarSystems;
    var $triggers = array();
    public $guildID;
    public $roleName;
    public $corpID;
    public $db;
    public $dbUser;
    public $dbPass;
    public $dbName;
    public $forceName;
    public $ssoUrl;
    public $nameEnforce;
    public $allianceID;
    public $allyroleName;

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
        $this->corpID = $config["plugins"]["auth"]["corpid"];
        $this->allianceID = $config["plugins"]["auth"]["allianceID"];
        $this->guildID = $config["plugins"]["auth"]["guildID"];
        $this->roleName = $config["plugins"]["auth"]["corpmemberRole"];
        $this->allyroleName = $config["plugins"]["auth"]["allymemberRole"];
        $this->nameEnforce = $config["plugins"]["auth"]["nameEnforce"];
        $this->ssoUrl = $config["plugins"]["auth"]["url"];
    }
    /**
     *
     */
    function tick()
    {
    }
    /**
     * @param $msgData
     * @return null
     */
    function onMessage($msgData)
    {
        $userID = $msgData["message"]["fromID"];
        $userName = $msgData["message"]["from"];
        $message = $msgData["message"]["message"];
        $channelID = $msgData["message"]["channelID"];
        $data = command($message, $this->information()["trigger"]);
        if (isset($data["trigger"])) {
            $code = $data["messageString"];
            $result = selectPending($this->db, $this->dbUser, $this->dbPass, $this->dbName, $code);
            while ($rows = $result->fetch_assoc()) {
                $charid = $rows['characterID'];
                $corpid = $rows['corporationID'];
                $allianceid = $rows['allianceID'];
                $url = "https://api.eveonline.com/eve/CharacterName.xml.aspx?ids=$charid";
                $xml = makeApiRequest($url);

                // We have an error, show it it
                if ($xml->error) {
                    $this->discord->api("channel")->messages()->create($channelID, "**Failure:** Eve API is down, please try again in a little while.");
                    return null;
                }
                elseif ($this->nameEnforce == 'true') {
                    foreach ($xml->result->rowset->row as $character) {
                        if ($character->attributes()->name != $userName) {
                            $this->discord->api("channel")->messages()->create($channelID, "**Failure:** Your discord name must match your character name.");
                            $this->logger->info("User was denied due to not having the correct name " . $character->attributes()->name);
                            return null;

                        }
                    }
                }
                $url = "https://api.eveonline.com/eve/CharacterName.xml.aspx?ids=$charid";
                $xml = makeApiRequest($url);
                foreach ($xml->result->rowset->row as $character) {
                    $eveName = $character->attributes()->name;
                    if ($corpid == $this->corpID) {
                        $guildData = $this->discord->api("guild")->show($this->guildID);
                        foreach ($guildData["roles"] as $role) {
                            $roleID = $role["id"];
                            if ($role["name"] == $this->roleName) {
                                $this->discord->api("guild")->members()->redeploy($this->guildID, $userID, array($roleID));
                                insertUser($this->db, $this->dbUser, $this->dbPass, $this->dbName, $userID, $charid, $eveName, 'corp');
                                disableReg($this->db, $this->dbUser, $this->dbPass, $this->dbName, $code);
                                $this->discord->api("channel")->messages()->create($channelID, "**Success:** You have now been added to the " . $this->roleName . " group. To get more roles, talk to the CEO / Directors");
                                $this->logger->info("User authed and added to corp group " . $eveName);
                                return null;
                            }
                        }
                    }
                }
                foreach ($xml->result->rowset->row as $character) {
                    $eveName = $character->attributes()->name;
                    if ($allianceid == $this->allianceID) {
                        $guildData = $this->discord->api("guild")->show($this->guildID);
                        foreach ($guildData["roles"] as $role) {
                            $roleID = $role["id"];
                            if ($role["name"] == $this->allyroleName) {
                                $this->discord->api("guild")->members()->redeploy($this->guildID, $userID, array($roleID));
                                insertUser($this->db, $this->dbUser, $this->dbPass, $this->dbName, $userID, $charid, $eveName, 'ally');
                                disableReg($this->db, $this->dbUser, $this->dbPass, $this->dbName, $code);
                                $this->discord->api("channel")->messages()->create($channelID, "**Success:** You have now been added to the " . $this->allyroleName . " group. To get more roles, talk to the CEO / Directors");
                                $this->logger->info("User authed and added to alliance group " . $eveName);
                                return null;
                            }
                        }
                    }
                }

            }
            $this->discord->api("channel")->messages()->create($channelID, "**Failure:** No roles available for your corp or alliance.");
            $this->logger->info("User was denied due to not being in the correct corp or alliance " . $userName);
            return null;
        }
        return null;
    }
    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "auth",
            "trigger" => array("!auth"),
            "information" => "SSO based auth system. " . $this->ssoUrl . " Visit the link and login with your main EVE account, select the correct character, and put the !auth <string> you receive in chat."
        );
    }
    /**
     * @param $msgData
     */
    function onMessageAdmin($msgData)
    {
    }
}