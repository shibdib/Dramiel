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

$config = array();

$config["bot"] = array(
    "name" => "TWINKIE NUMBA UN", // Discord name for your bot (Not yet implemented)
    "game" => "USA #1", // Shows the bot "playing" this
    "trigger" => "!", // what trigger is used for commands
    "guild" => 152677265635803136, // guildID 
    "token" => "", //enter the token for your app (https://discordapp.com/developers/applications/me)
    "adminRoles" => array("Admin",""),//enter the roles that you'd like to have access to admin commands
    "silentMode" => "false"//set this to true if you want to disable all the chat commands
);

$config["database"] = array(
    "host" => "localhost",
    "user" => "",
    "pass" => "",
    "database" => "discord"
);

// Twitter
$config["twitter"] = array(
    "consumerKey" => "",
    "consumerSecret" => "",
    "accessToken" => "",
    "accessTokenSecret" => ""
);

$config["eve"] = array(
    "apiKeys" => array(
        "user1" => array(
            "keyID" => "",
            "vCode" => "",
            "characterID" => 0
        ),
    )
);

$config["enabledPlugins"] = array( // remove the slashes for the plugins you want
    "about", //info on the bot
    "auth", //sso based auth system
    "authCheck", // checks if users have left corp or alliance
    "charInfo", // eve character info using eve-kill
    "corpInfo", // eve corp info
    "eveStatus", // tq status message command
    //"periodicStatusCheck", // ....YOU MUST SET A CHANNEL IN THE NOTIFICATIONS SECTION NEAR THE BOTTOM OF THIS FILE.... Bot routinely checks if TQ status changes (reports server downtimes to the notifications channel)
    "help", // bot help program, will list active addons
    "price", // price check tool, works for all items and ships. Can either !pc <itemname> for general, or !<systemname> <item> for more specific
    "time", // global clock with eve time
    //"evemails", // evemail updater, will post corp and alliance mails to a channel.
    //"fileReader", // Read advanced plugin config section of the wiki
    //"notifications", // eve notifications to a channel, good for warning users of an attack
    //"twitterOutput", // twitter input to stay up to date on eve happenings
    "getKillmails", // show corp killmails in a chat channel
    //"siphons", // report possible siphons, see wiki for more info
    //"siloFull", // report any silos nearing max capacity. Currently only works for silo bonus (amarr) towers
    //"fleetUpOperations", // integrate with fleet up and post any new operations and then ping them when they get close
);


$config["plugins"] = array(
    //uses the provided api's to post evemails to a channel
    "evemails" => array(
        "fromIDs" => array(0, 0), // fill in with corp/alliance id's you want info from (have to be accessible with the api)
        "channelID" => 0 // what channel id like these to post too
    ),
    "fileReader" => array(
        "db" => "/tmp/discord.db",
        "channelConfig" => array(
            "pings" => array(
                "default" => true,
                "searchString" => "broadcast", // The plugin will search for this string and post any messages that contain it. To have the bot share everything change it to false without any quotes.
                "textStringPrepend" => "@everyone |", // this prepend will ping all discord users with access to the channel
                "textStringAppend" => "", // anything ud like to add to the tail end of the bots message
                "channelID" => "" // channel it posts too
            ),
            "supers" => array(
                "default" => false,
                "searchString" => "",
                "textStringPrepend" => "@everyone |",
                "textStringAppend" => "",
                "channelID" => 0
            ),
            "blackops" => array(
                "default" => false,
                "searchString" => "",
                "textStringPrepend" => "@everyone |",
                "textStringAppend" => "",
                "channelID" => 0
            )
        ),
    ),
    // what channel for eve notifications/also the channel for tq status alerts
    "notifications" => array(
        "channelID" => 0
    ),
    //Spam twitter messages from people you follow to this channel
    "twitterOutput" => array(
        "channelID" => 0 // twitter output channel
    ),
    //Pricecheck tool
    "priceChecker" => array(
        "channelID" => 0 //If you want to restrict price checker from working in a channel, put that channel's ID here.
    ),
    //SSO Auth
    "auth" => array(
        "corpID" => 0,
        "allianceID" => 0, // If you'd like to auth base on alliance put the alliance ID here.. also works to set blues..
        "corpMemberRole" => "", // The name of the role your CORP members will be assigned too if the auth plugin is active.
        "allyMemberRole" => "", // The name of the role your ALLY members will be assigned too if the auth plugin is active.
        "alertChannel" => 0, // if using periodic check put the channel you'd like the bot to log removing users in. (Recommended you don't use an active chat channel)
        "nameEnforce" => "false", // if "true" bot will automatically change nicknames so that they match player names.
        "url" => "http://.....", // put a url here if using sso auth for ur sso page.
        "exempt" => array("", "") // role names that are exempt from auth checks (wont be removed by the bot)
    ),
    //Killmail posting
    "getKillmails" => array(
        "channel" => 0, //killmails post to this channel
        "corpID" => 0, //corpid for killmails
        "allianceID" => 0, //allianceid for killmails (Leave as 0 if using it for a corp)
        "lossMails" => "true", //set as true to post both kills and losses, false to post only kills.
        "spamAmount" => 10, //Max amount of kills the bot will post every 10 minutes. Default is 15 and won't get the bot kicked for spamming.
        "startMail" => 1, //Put the zkill killID of your latest killmail. Otherwise it will pull from the beginning of time.
    ),
    //Siphon detection works by looking for multiples of 100 inside standard silos. So if you take out a weird number it will trigger false positives.
    "siphons" => array(
        "channelID" => 0, //killmails post to this channel
        "keyID" => "", //corpid for killmails
        "vCode" => "", //allianceid for killmails (Leave as 0 if using it for a corp)
        "prefix" => "", //put @everyone if you'd like everyone to be pinged when a siphon is detected
    ),
    //If you'd like low fuel warnings to go to a different channel set this here. Otherwise leave it as null
    "fuel" => array(
        "channelID" => null, //fuel alerts post to this channel
        "skip" => "false", //if you want fuel notifications to be skipped change this to true
    ),
    //Reports silos nearing max capacity.
    "siloFull" => array(
        "channelID" => 0, //silo alerts post to this channel
        "keyID" => "", //corp api keyID (Must have assets)
        "vCode" => "", //corp api vCode
        "towerRace" => 0, //The race of your moon goo towers (to determine silo bonus.) Amarr/Amarr Faction Variants = 1, Gal/Gal Faction Variants = 2, Everyone else = 0
    ),
    //Fleet up linking will share operations to a specific channel and then reping them when it gets within 30 minutes of form up
    "fleetUp" => array(
        "channelID" => 0, //channel id to ping about operations
        "userID" => 0, //fleet up user id
        "groupID" => 0, //fleet up group id
        "apiKey" => "xxxxx", //fleet up api code, link to application Dramiel Bot
    ),
);
