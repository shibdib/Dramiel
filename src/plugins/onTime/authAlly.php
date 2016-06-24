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

// Auth Alliance Check
$loop->addPeriodicTimer(850, function() use ($logger, $discord, $config) {
    if ($config["plugins"]["auth"]["periodicCheck"] == "true") {
        $logger->info("Initiating Alliance Auth Check");
        $db = $config["database"]["host"];
        $dbUser = $config["database"]["user"];
        $dbPass = $config["database"]["pass"];
        $dbName = $config["database"]["database"];
        $allyID = $config["plugins"]["auth"]["allianceID"];
        $guildID = $config["plugins"]["auth"]["guildID"];
        $toDiscordChannel = $config["plugins"]["auth"]["alertChannel"];
        $conn = new mysqli($db, $dbUser, $dbPass, $dbName);

        $sql3 = "SELECT characterID, discordID FROM authUsers WHERE role = 'ally' AND active='yes'";

        $result3 = $conn->query($sql3);
        $num_rows2 = $result3->num_rows;

        if ($num_rows2 >= 1) {
            while ($rows = $result3->fetch_assoc()) {
                $charID = $rows['characterID'];
                $discordID = $rows['discordID'];
                $userData = $discord->api('user')->show($discordID);
                $discordName = $userData['username'];
                $url = "https://api.eveonline.com/eve/CharacterAffiliation.xml.aspx?ids=$charID";
                $xml = makeApiRequest($url);
                if ($xml->result->rowset->row[0]) {
                    foreach ($xml->result->rowset->row as $character) {
                        if ($character->attributes()->allianceID != $allyID) {
                            $discord->api("guild")->members()->redeploy($guildID, $discordID, "");
                            $discord->api("channel")->messages()->create($toDiscordChannel, "Discord user " . $discordName . " alliance roles have been removed due to no longer being a member of the alliance.");
                            $logger->info("Removing user " . $discordName);

                            $sql4 = "UPDATE authUsers SET active='no' WHERE discordID='$discordID'";
                            $result4 = $conn->query($sql4);
                        }
                    }
                }
            }
        $logger->info("All alliance users successfully authed.");
        return null;

        }
    $logger->info("No alliance users found in database.");
    return null;


    }return null;
});