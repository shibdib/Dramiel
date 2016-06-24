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
 * Class apiauth
 */
class apiauth
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
    public $nameEnforce;

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
        $this->nameEnforce = $config["plugins"]["auth"]["nameEnforce"];
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
        $userID = @$msgData["channel"]["recipient"]["id"];
        $userName = $msgData["message"]["from"];
        $message = $msgData["message"]["message"];
        $channelID = $msgData["message"]["channelID"];
        $data = command($message, $this->information()["trigger"]);
        if (isset($data["trigger"])) {
            $field = explode(" ", $data["messageString"]);
            $post = [
                'key_id' => $field[0],
                'v_code' => $field[1],
            ];
            // Basic check on entry validity. Need to make this more robust.
            if (strlen($field[0]) != 7) {
                return $this->discord->api("channel")->messages()->create($channelID, "**Invalid KeyID**");
            }
            if (strlen($field[1]) != 64) {
                return $this->discord->api("channel")->messages()->create($channelID, "**Invalid vCode**");
            }
            $guildData = $this->discord->api("guild")->show($this->guildID);
            $url = "https://api.eveonline.com/account/Characters.xml.aspx?keyID=$field[0]&vCode=$field[1]";
            $xml = makeApiRequest($url);
            // We have an error, show it it
            if ($xml->error) {
                $this->discord->api("channel")->messages()->create($channelID, "**Failure:** Bad API Key.");
                return null;
            }
            // If we have the ID, show it
            elseif ($xml->result->rowset->row[0]) {
                foreach ($xml->result->rowset->row as $character) {
                    if ($character->attributes()->corporationID == $this->corpID) {
                        if ($this->nameEnforce == 'true') {
                            if ($character->attributes()->name != $userName) {
                                $this->discord->api("channel")->messages()->create($channelID, "**Failure:** Your discord name must match your character name.");
                                return null;
                            }
                        }
                        foreach ($guildData["roles"] as $role)
                        {
                            $characterID = $character->attributes()->characterID;
                            $characterName = $character->attributes()->name;
                            $roleID = $role["id"];
                            if ($role["name"] == $this->roleName) {
                                $this->discord->api("guild")->members()->redeploy($this->guildID, $userID, array($roleID));
                                insertUser($this->db, $this->dbUser, $this->dbPass, $this->dbName, $userID, $characterID, $characterName, 'corp');
                                $this->discord->api("channel")->messages()->create($channelID, "**Success:** You have now been added to the " . $this->roleName . " group. To get more roles, talk to the CEO / Directors");
                                return null;
                            }
                        }
                    }
                    if ($character->attributes()->allianceID == $this->allianceID) {
                        if ($this->nameEnforce == 'true') {
                            if ($character->attributes()->name != $userName) {
                                $this->discord->api("channel")->messages()->create($channelID, "**Failure:** Your discord name must match your character name.");
                                return null;
                            }
                        }
                        foreach ($guildData["roles"] as $role)
                        {
                            $characterID = $character->attributes()->characterID;
                            $characterName = $character->attributes()->name;
                            $roleID = $role["id"];
                            if ($role["name"] == $this->roleName) {
                                $this->discord->api("guild")->members()->redeploy($this->guildID, $userID, array($roleID));
                                insertUser($this->db, $this->dbUser, $this->dbPass, $this->dbName, $userID, $characterID, $characterName, 'ally');
                                $this->discord->api("channel")->messages()->create($channelID, "**Success:** You have now been added to the " . $this->roleName . " group. To get more roles, talk to the CEO / Directors");
                                return null;
                            }
                        }
                    }
                }
                $this->discord->api("channel")->messages()->create($channelID, "**Failure:** No character found in the correct corp or alliance.");
                return null;
            }
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
            "name" => "apiauth",
            "trigger" => array("!apiauth"),
            "information" => "API based auth system. To get basic member roles create an api `https://community.eveonline.com/support/api-key/CreatePredefined?accessMask=8388608`. Then submit it by sending a __*private message to the bot*__ with the following '!auth Your_KeyID  Your_vCode'"
        );
    }
    /**
     * @param $msgData
     */
    function onMessageAdmin($msgData)
    {
    }
}
