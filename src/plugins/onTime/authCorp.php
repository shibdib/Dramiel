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

// Auth Corp Check
$loop->addPeriodicTimer(850, function() use ($logger, $discord, $config) {
    if ($config["plugins"]["auth"]["periodicCheck"] == "true") {
        $logger->info("Initiating Corp Auth Check");
        $db = $config["database"]["host"];
        $dbUser = $config["database"]["user"];
        $dbPass = $config["database"]["pass"];
        $dbName = $config["database"]["database"];
        $corpID = $config["plugins"]["auth"]["corpid"];
        $guildID = $config["plugins"]["auth"]["guildID"];
        $toDiscordChannel = $config["plugins"]["auth"]["alertChannel"];
        $conn = new mysqli($db, $dbUser, $dbPass, $dbName);

        $sql = "SELECT characterID, discordID FROM authUsers WHERE role = 'corp' AND active='yes'";

        $result = $conn->query($sql);
        $num_rows = $result->num_rows;

        if ($num_rows >= 1) {
            while ($rows = $result->fetch_assoc()) {
                $charID = $rows['characterID'];
                $discordID = $rows['discordID'];
                $userData = $discord->api('user')->show($discordID);
                $discordName = $userData['username'];
                $url = "https://api.eveonline.com/eve/CharacterAffiliation.xml.aspx?ids=$charID";
                $xml = makeApiRequest($url);
                if ($xml->result->rowset->row[0]) {
                    foreach ($xml->result->rowset->row as $character) {
                        if ($character->attributes()->corporationID != $corpID) {
                            $discord->api("guild")->members()->redeploy($guildID, $discordID, "");
                            $discord->api("channel")->messages()->create($toDiscordChannel, "Discord user {$discordName} corp roles have been removed due to no longer being a member of the corp.");
                            $logger->info("Removing user " . $discordName);

                            $sql2 = "UPDATE authUsers SET active='no' WHERE discordID='$discordID'";
                            $result2 = $conn->query($sql2);
                        }
                    }
                }
            }
            $logger->info("All corp users successfully authed.");
            return null;

        }
        $logger->info("No corp users found in database.");
        return null;
    }
});