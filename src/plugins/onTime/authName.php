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

// Auth Name Check
$loop->addPeriodicTimer(850, function() use ($logger, $discord, $config) {
    if ($config["plugins"]["auth"]["nameEnforce"] == "true") {
        $logger->info("Initiating Name Check");
        $db = $config["database"]["host"];
        $dbUser = $config["database"]["user"];
        $dbPass = $config["database"]["pass"];
        $dbName = $config["database"]["database"];
        $guildID = $config["plugins"]["auth"]["guildID"];
        $toDiscordChannel = $config["plugins"]["auth"]["alertChannel"];
        $conn = new mysqli($db, $dbUser, $dbPass, $dbName);

        $sql = "SELECT characterID, discordID, eveName FROM authUsers WHERE active='yes'";

        $result = $conn->query($sql);
        $num_rows = $result->num_rows;

        if ($num_rows >= 1) {
            while ($rows = $result->fetch_assoc()) {
                $discordid = $rows['discordID'];
                $eveName = $rows['eveName'];
                $userData = $discord->api('user')->show($discordid);
                $discordname = $userData['username'];
                if ($discordname != $eveName) {
                    $discord->api("guild")->members()->redeploy($guildID, $discordid, "");
                    $discord->api("channel")->messages()->create($toDiscordChannel, "Discord user " . $discordname . " roles removed via auth because their discord name does not match their in-game name " . $eveName);
                    $logger->info("Removing user due to name being incorrect " . $discordid);

                    $sql2 = "UPDATE authUsers SET active='no' WHERE discordID='$discordid'";
                    $result2 = $conn->query($sql2);
                }



            }
            $logger->info("All users names are correct.");
            return null;

        }
        $logger->info("No users found in database.");
        return null;


    }
    return null;
});