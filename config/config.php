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
$adminRoles = explode(',', str_replace(' ', '', dbQueryField("SELECT value FROM config WHERE variable = 'adminRoles'", 'value', array(), 'config')));
$restrictedChannels = explode(',', str_replace(' ', '', dbQueryField("SELECT value FROM config WHERE variable = 'restrictedChannels'", 'value', array(), 'config')));
$config['bot'] = array(
    'trigger' => dbQueryField("SELECT value FROM config WHERE variable = 'trigger'", 'value', array(), 'config'), // what trigger is used for commands
    'guild' => dbQueryField("SELECT value FROM config WHERE variable = 'guild'", 'value', array(), 'config'), // guildID
    'adminRoles' => $adminRoles, //enter the roles that you'd like to have access to admin commands
    'restrictedChannels' => $restrictedChannels, //bot will not respond in these channels
    'silentMode' => dbQueryField("SELECT value FROM config WHERE variable = 'silentMode'", 'value', array(), 'config')//set this to true if you want to disable all the chat commands
);

$config['eve'] = array(
    'apiKeys' => array( //Put at least one character API here with access to Mails and Notifications. The more keys added the more often the bot will check for new notifications and mails (The characters must all be in the same corp!)
        'user1' => array(
            'keyID' => dbQueryField("SELECT value FROM config WHERE variable = 'characterKeyID1'", 'value', array(), 'config'),
            'vCode' => dbQueryField("SELECT value FROM config WHERE variable = 'charactervCode1'", 'value', array(), 'config'),
            'characterID' => dbQueryField("SELECT value FROM config WHERE variable = 'characterCharacterID1'", 'value', array(), 'config')
        ),
        'user2' => array(
            'keyID' => dbQueryField("SELECT value FROM config WHERE variable = 'characterKeyID2'", 'value', array(), 'config'),
            'vCode' => dbQueryField("SELECT value FROM config WHERE variable = 'charactervCode2'", 'value', array(), 'config'),
            'characterID' => dbQueryField("SELECT value FROM config WHERE variable = 'characterCharacterID2'", 'value', array(), 'config')
        ),
        'user3' => array(
            'keyID' => dbQueryField("SELECT value FROM config WHERE variable = 'characterKeyID3'", 'value', array(), 'config'),
            'vCode' => dbQueryField("SELECT value FROM config WHERE variable = 'charactervCode3'", 'value', array(), 'config'),
            'characterID' => dbQueryField("SELECT value FROM config WHERE variable = 'characterCharacterID3'", 'value', array(), 'config')
        ),
        'user4' => array(
            'keyID' => dbQueryField("SELECT value FROM config WHERE variable = 'characterKeyID4'", 'value', array(), 'config'),
            'vCode' => dbQueryField("SELECT value FROM config WHERE variable = 'charactervCode4'", 'value', array(), 'config'),
            'characterID' => dbQueryField("SELECT value FROM config WHERE variable = 'characterCharacterID4'", 'value', array(), 'config')
        ),
        'user5' => array(
            'keyID' => dbQueryField("SELECT value FROM config WHERE variable = 'characterKeyID5'", 'value', array(), 'config'),
            'vCode' => dbQueryField("SELECT value FROM config WHERE variable = 'charactervCode5'", 'value', array(), 'config'),
            'characterID' => dbQueryField("SELECT value FROM config WHERE variable = 'characterCharacterID5'", 'value', array(), 'config')
        ),
    )
);

$enabled = dbQuery("SELECT * FROM config WHERE value = 'true' AND plugin = 'enabledPlugin'", array(), 'config');
foreach ($enabled as $plugin) {
    $enabledPlugins[] = $plugin['variable'];
}

$config['enabledPlugins'] = $enabledPlugins;


$mailIDs = explode(',', str_replace(' ', '', dbQueryField("SELECT value FROM config WHERE variable = 'fromIDs'", 'value', array(), 'config')));
$exemptAuth = explode(',', str_replace(' ', '', dbQueryField("SELECT value FROM config WHERE variable = 'exempt'", 'value', array(), 'config')));
$config['plugins'] = array(
    //uses the provided api's to post evemails to a channel
    'evemails' => array(
        'fromIDs' => $mailIDs, // fill in with corp/alliance id's you want info from (have to be accessible with the api)
        'channelID' => dbQueryField("SELECT value FROM config WHERE variable = 'eveMailsChannelID'", 'value', array(), 'config') // what channel id like these to post too
    ),
    'fileReader' => array(
        'db' => '/../../jabberPings.db',
        'channelConfig' => array(
            'pings' => array(
                'default' => true,
                'searchString' => dbQueryField("SELECT value FROM config WHERE variable = 'fileReaderSearch1'", 'value', array(), 'config'), // The plugin will search for this string and post any messages that contain it. To have the bot share everything change it to false without any quotes.
                'textStringPrepend' => dbQueryField("SELECT value FROM config WHERE variable = 'fileReaderPrepend1'", 'value', array(), 'config'), // this prepend will ping all discord users with access to the channel
                'textStringAppend' => dbQueryField("SELECT value FROM config WHERE variable = 'fileReaderAppend1'", 'value', array(), 'config'), // anything ud like to add to the tail end of the bots message
                'channelID' => dbQueryField("SELECT value FROM config WHERE variable = 'fileReaderChannelID1'", 'value', array(), 'config') // channel it posts too
            ),
            'supers' => array(
                'default' => false,
                'searchString' => dbQueryField("SELECT value FROM config WHERE variable = 'fileReaderSearch2'", 'value', array(), 'config'),
                'textStringPrepend' => dbQueryField("SELECT value FROM config WHERE variable = 'fileReaderPrepend2'", 'value', array(), 'config'),
                'textStringAppend' => dbQueryField("SELECT value FROM config WHERE variable = 'fileReaderAppend2'", 'value', array(), 'config'),
                'channelID' => dbQueryField("SELECT value FROM config WHERE variable = 'fileReaderChannelID2'", 'value', array(), 'config') // channel it posts too
            ),
            'blackops' => array(
                'default' => false,
                'searchString' => dbQueryField("SELECT value FROM config WHERE variable = 'fileReaderSearch3'", 'value', array(), 'config'),
                'textStringPrepend' => dbQueryField("SELECT value FROM config WHERE variable = 'fileReaderPrepend3'", 'value', array(), 'config'),
                'textStringAppend' => dbQueryField("SELECT value FROM config WHERE variable = 'fileReaderAppend3'", 'value', array(), 'config'),
                'channelID' => dbQueryField("SELECT value FROM config WHERE variable = 'fileReaderChannelID3'", 'value', array(), 'config') // channel it posts too
            )
        ),
    ),
    // what channel for eve notifications/also the channel for tq status alerts
    'notifications' => array(
        'channelID' => dbQueryField("SELECT value FROM config WHERE variable = 'notificationsChannelID'", 'value', array(), 'config'),
        'allianceOnly' => dbQueryField("SELECT value FROM config WHERE variable = 'allianceOnly'", 'value', array(), 'config') //change this to true if you'd like to filter out the corp specific notifications (tower spam, etc..)
    ),
    //SSO Auth
    'auth' => array(
        'exempt' => $exemptAuth, // role names that are exempt from auth checks (wont be removed by the bot)
        'alertChannel' => dbQueryField("SELECT value FROM config WHERE variable = 'alertChannel'", 'value', array(), 'config'), // if using periodic check put the channel you'd like the bot to log removing users in. (Recommended you don't use an active chat channel)
        'corpTickers' => dbQueryField("SELECT value FROM config WHERE variable = 'corpTickers'", 'value', array(), 'config'), // if "true" bot will automatically add corp tickers to the front of users names at auth.
        'nameEnforce' => dbQueryField("SELECT value FROM config WHERE variable = 'nameEnforce'", 'value', array(), 'config'), // if "true" bot will automatically rename users to match their ingame name, can be used in conjunction with corpTickers.
        'authGroups' => array(
            'group1' => array(
                'corpID' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpID1'", 'value', array(), 'config'), // If you'd like to auth based on CORP put the corp ID here otherwise leave it as 0
                'allianceID' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceID1'", 'value', array(), 'config'), // If you'd like to auth based on ALLIANCE put the alliance ID here otherwise leave it as 0 (Can be used in conjunction with corp)
                'corpMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpRole1'", 'value', array(), 'config'), // The name of the role your CORP members will be assigned too if the auth plugin is active.
                'allyMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceRole1'", 'value', array(), 'config'), // The name of the role your ALLIANCE members will be assigned too if the auth plugin is active.
            ),
            'group2' => array(
                'corpID' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpID2'", 'value', array(), 'config'), // If you'd like to auth based on CORP put the corp ID here otherwise leave it as 0
                'allianceID' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceID2'", 'value', array(), 'config'), // If you'd like to auth based on ALLIANCE put the alliance ID here otherwise leave it as 0 (Can be used in conjunction with corp)
                'corpMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpRole2'", 'value', array(), 'config'), // The name of the role your CORP members will be assigned too if the auth plugin is active.
                'allyMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceRole2'", 'value', array(), 'config'), // The name of the role your ALLIANCE members will be assigned too if the auth plugin is active.
            ),
            'group3' => array(
                'corpID' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpID3'", 'value', array(), 'config'), // If you'd like to auth based on CORP put the corp ID here otherwise leave it as 0
                'allianceID' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceID3'", 'value', array(), 'config'), // If you'd like to auth based on ALLIANCE put the alliance ID here otherwise leave it as 0 (Can be used in conjunction with corp)
                'corpMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpRole3'", 'value', array(), 'config'), // The name of the role your CORP members will be assigned too if the auth plugin is active.
                'allyMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceRole3'", 'value', array(), 'config'), // The name of the role your ALLIANCE members will be assigned too if the auth plugin is active.
            ),
            'group4' => array(
                'corpID' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpID4'", 'value', array(), 'config'), // If you'd like to auth based on CORP put the corp ID here otherwise leave it as 0
                'allianceID' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceID4'", 'value', array(), 'config'), // If you'd like to auth based on ALLIANCE put the alliance ID here otherwise leave it as 0 (Can be used in conjunction with corp)
                'corpMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpRole4'", 'value', array(), 'config'), // The name of the role your CORP members will be assigned too if the auth plugin is active.
                'allyMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceRole4'", 'value', array(), 'config'), // The name of the role your ALLIANCE members will be assigned too if the auth plugin is active.
            ),
            'group5' => array(
                'corpID' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpID5'", 'value', array(), 'config'), // If you'd like to auth based on CORP put the corp ID here otherwise leave it as 0
                'allianceID' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceID5'", 'value', array(), 'config'), // If you'd like to auth based on ALLIANCE put the alliance ID here otherwise leave it as 0 (Can be used in conjunction with corp)
                'corpMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpRole5'", 'value', array(), 'config'), // The name of the role your CORP members will be assigned too if the auth plugin is active.
                'allyMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceRole5'", 'value', array(), 'config'), // The name of the role your ALLIANCE members will be assigned too if the auth plugin is active.
            ),
            'group6' => array(
                'corpID' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpID6'", 'value', array(), 'config'), // If you'd like to auth based on CORP put the corp ID here otherwise leave it as 0
                'allianceID' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceID6'", 'value', array(), 'config'), // If you'd like to auth based on ALLIANCE put the alliance ID here otherwise leave it as 0 (Can be used in conjunction with corp)
                'corpMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpRole6'", 'value', array(), 'config'), // The name of the role your CORP members will be assigned too if the auth plugin is active.
                'allyMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceRole6'", 'value', array(), 'config'), // The name of the role your ALLIANCE members will be assigned too if the auth plugin is active.
            ),
            'group7' => array(
                'corpID' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpID7'", 'value', array(), 'config'), // If you'd like to auth based on CORP put the corp ID here otherwise leave it as 0
                'allianceID' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceID7'", 'value', array(), 'config'), // If you'd like to auth based on ALLIANCE put the alliance ID here otherwise leave it as 0 (Can be used in conjunction with corp)
                'corpMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpRole7'", 'value', array(), 'config'), // The name of the role your CORP members will be assigned too if the auth plugin is active.
                'allyMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceRole7'", 'value', array(), 'config'), // The name of the role your ALLIANCE members will be assigned too if the auth plugin is active.
            ),
            'group8' => array(
                'corpID' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpID8'", 'value', array(), 'config'), // If you'd like to auth based on CORP put the corp ID here otherwise leave it as 0
                'allianceID' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceID8'", 'value', array(), 'config'), // If you'd like to auth based on ALLIANCE put the alliance ID here otherwise leave it as 0 (Can be used in conjunction with corp)
                'corpMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpRole8'", 'value', array(), 'config'), // The name of the role your CORP members will be assigned too if the auth plugin is active.
                'allyMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceRole8'", 'value', array(), 'config'), // The name of the role your ALLIANCE members will be assigned too if the auth plugin is active.
            ),
            'group9' => array(
                'corpID' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpID9'", 'value', array(), 'config'), // If you'd like to auth based on CORP put the corp ID here otherwise leave it as 0
                'allianceID' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceID9'", 'value', array(), 'config'), // If you'd like to auth based on ALLIANCE put the alliance ID here otherwise leave it as 0 (Can be used in conjunction with corp)
                'corpMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpRole9'", 'value', array(), 'config'), // The name of the role your CORP members will be assigned too if the auth plugin is active.
                'allyMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceRole9'", 'value', array(), 'config'), // The name of the role your ALLIANCE members will be assigned too if the auth plugin is active.
            ),
            'group10' => array(
                'corpID' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpID10'", 'value', array(), 'config'), // If you'd like to auth based on CORP put the corp ID here otherwise leave it as 0
                'allianceID' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceID10'", 'value', array(), 'config'), // If you'd like to auth based on ALLIANCE put the alliance ID here otherwise leave it as 0 (Can be used in conjunction with corp)
                'corpMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpRole10'", 'value', array(), 'config'), // The name of the role your CORP members will be assigned too if the auth plugin is active.
                'allyMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceRole10'", 'value', array(), 'config'), // The name of the role your ALLIANCE members will be assigned too if the auth plugin is active.
            ),
            'group11' => array(
                'corpID' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpID11'", 'value', array(), 'config'), // If you'd like to auth based on CORP put the corp ID here otherwise leave it as 0
                'allianceID' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceID11'", 'value', array(), 'config'), // If you'd like to auth based on ALLIANCE put the alliance ID here otherwise leave it as 0 (Can be used in conjunction with corp)
                'corpMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpRole11'", 'value', array(), 'config'), // The name of the role your CORP members will be assigned too if the auth plugin is active.
                'allyMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceRole11'", 'value', array(), 'config'), // The name of the role your ALLIANCE members will be assigned too if the auth plugin is active.
            ),
            'group12' => array(
                'corpID' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpID12'", 'value', array(), 'config'), // If you'd like to auth based on CORP put the corp ID here otherwise leave it as 0
                'allianceID' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceID12'", 'value', array(), 'config'), // If you'd like to auth based on ALLIANCE put the alliance ID here otherwise leave it as 0 (Can be used in conjunction with corp)
                'corpMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authCorpRole12'", 'value', array(), 'config'), // The name of the role your CORP members will be assigned too if the auth plugin is active.
                'allyMemberRole' => dbQueryField("SELECT value FROM config WHERE variable = 'authAllianceRole12'", 'value', array(), 'config'), // The name of the role your ALLIANCE members will be assigned too if the auth plugin is active.
            )
        ),
        'standings' => array(
            'enabled' => dbQueryField("SELECT value FROM config WHERE variable = 'standingsEnabled'", 'value', array(), 'config'), //set to true if you want to allow people to auth based off of corp/alliance standings
            'apiKey' => dbQueryField("SELECT value FROM config WHERE variable = 'standingsApiKey'", 'value', array(), 'config'), //enter the KEYID for whatever above api you'd like to base standings off of
            'plus10Role' => dbQueryField("SELECT value FROM config WHERE variable = 'standingsPlus10'", 'value', array(), 'config'),
            'plus5Role' => dbQueryField("SELECT value FROM config WHERE variable = 'standingsPlus5'", 'value', array(), 'config'),
            'neutralRole' => dbQueryField("SELECT value FROM config WHERE variable = 'standingsNeutral'", 'value', array(), 'config'),
            'minus10Role' => dbQueryField("SELECT value FROM config WHERE variable = 'standingsMinus10'", 'value', array(), 'config'),
            'minus5Role' => dbQueryField("SELECT value FROM config WHERE variable = 'standingsMinus5'", 'value', array(), 'config'),
        ),
    ),
    //Killmail posting
    'getKillmails' => array(
        'bigKills' => array(
            'shareBigKills' => dbQueryField("SELECT value FROM config WHERE variable = 'bigKillsShareBigKills'", 'value', array(), 'config'), //If you'd like the bot to share eve wide big kills switch this to true
            'bigKillChannel' => dbQueryField("SELECT value FROM config WHERE variable = 'bigKillsBigKillChannel'", 'value', array(), 'config'), //Set the channel the eve wide big kills post too
            'bigKillStartID' => 58928324, //Recommend you set this to a recent killID to prevent it spamming from the beginning of time
        ),
        'groupConfig' => array(
            'group1' => array(
                'name' => dbQueryField("SELECT value FROM config WHERE variable = 'getKillmailsName1'", 'value', array(), 'config'), // insert a label (these must be unique)
                'channel' => dbQueryField("SELECT value FROM config WHERE variable = 'getKillmailsChannel1'", 'value', array(), 'config'), //killmails post to this channel
                'corpID' => dbQueryField("SELECT value FROM config WHERE variable = 'getKillmailsCorpID1'", 'value', array(), 'config'), //corpid for killmails
                'allianceID' => dbQueryField("SELECT value FROM config WHERE variable = 'getKillmailsAllianceID1'", 'value', array(), 'config'), //allianceid for killmails (Leave as 0 if using it for a corp)
                'lossMails' => dbQueryField("SELECT value FROM config WHERE variable = 'getKillmailsLossMails1'", 'value', array(), 'config'), //set as true to post both kills and losses, false to post only kills.
                'startMail' => dbQueryField("SELECT value FROM config WHERE variable = 'getKillmailsStartMail1'", 'value', array(), 'config'), //Put the zkill killID of your latest killmail. Otherwise it will pull from the beginning of time.
                'minimumValue' => dbQueryField("SELECT value FROM config WHERE variable = 'getKillmailsMinimumValue1'", 'value', array(), 'config'), //Put the minimum isk value for killmails here (Do not put any commas just numbers)
                'bigKill' => null, //Set an isk amount you'd like to consider a high value kill, will alert the channel if any kills/losses hit this amount. (Leave as null if you don't want this feature)
                'bigKillChannel' => 0, //what channel does the bot post big kills into (must be set, if ud like to use one channel just put the same u put above here)
            ),
            'group2' => array(
                'name' => dbQueryField("SELECT value FROM config WHERE variable = 'getKillmailsName2'", 'value', array(), 'config'), // insert a label (these must be unique)
                'channel' => dbQueryField("SELECT value FROM config WHERE variable = 'getKillmailsChannel2'", 'value', array(), 'config'), //killmails post to this channel
                'corpID' => dbQueryField("SELECT value FROM config WHERE variable = 'getKillmailsCorpID2'", 'value', array(), 'config'), //corpid for killmails
                'allianceID' => dbQueryField("SELECT value FROM config WHERE variable = 'getKillmailsAllianceID2'", 'value', array(), 'config'), //allianceid for killmails (Leave as 0 if using it for a corp)
                'lossMails' => dbQueryField("SELECT value FROM config WHERE variable = 'getKillmailsLossMails2'", 'value', array(), 'config'), //set as true to post both kills and losses, false to post only kills.
                'startMail' => dbQueryField("SELECT value FROM config WHERE variable = 'getKillmailsStartMail2'", 'value', array(), 'config'), //Put the zkill killID of your latest killmail. Otherwise it will pull from the beginning of time.
                'minimumValue' => dbQueryField("SELECT value FROM config WHERE variable = 'getKillmailsMinimumValue2'", 'value', array(), 'config'), //Put the minimum isk value for killmails here (Do not put any commas just numbers)
                'bigKill' => null, //Set an isk amount you'd like to consider a high value kill, will alert the channel if any kills/losses hit this amount. (Leave as null if you don't want this feature)
                'bigKillChannel' => 0, //what channel does the bot post big kills into (must be set, if ud like to use one channel just put the same u put above here)
            ),
        ),
    ),
    //Siphon detection works by looking for multiples of 100 inside standard silos. https://github.com/shibdib/Dramiel/wiki/1b.-Siphon-Detection for more info
    'siphons' => array(
        'channelID' => 0, //siphon alerts post to this channel
        'groupConfig' => array(
            'group1' => array(
                'channelID' => dbQueryField("SELECT value FROM config WHERE variable = 'siphonsChannelID1'", 'value', array(), 'config'), //siphon alerts post to this channel
                'keyID' => dbQueryField("SELECT value FROM config WHERE variable = 'corpKeyID1'", 'value', array(), 'config'), //keyID
                'vCode' => dbQueryField("SELECT value FROM config WHERE variable = 'corpvCode1'", 'value', array(), 'config'), //vCode
                'prefix' => dbQueryField("SELECT value FROM config WHERE variable = 'siphonsPrefix1'", 'value', array(), 'config'), //put @everyone if you'd like everyone to be pinged when a siphon is detected
            ),
            'group2' => array(
                'channelID' => dbQueryField("SELECT value FROM config WHERE variable = 'siphonsChannelID2'", 'value', array(), 'config'), //siphon alerts post to this channel
                'keyID' => dbQueryField("SELECT value FROM config WHERE variable = 'corpKeyID2'", 'value', array(), 'config'), //keyID
                'vCode' => dbQueryField("SELECT value FROM config WHERE variable = 'corpvCode2'", 'value', array(), 'config'), //vCode
                'prefix' => dbQueryField("SELECT value FROM config WHERE variable = 'siphonsPrefix2'", 'value', array(), 'config'), //put @everyone if you'd like everyone to be pinged when a siphon is detected
            ),
        ),
    ),
    //If you'd like low fuel warnings to go to a different channel set this here. Otherwise leave it as null
    'fuel' => array(
        'channelID' => dbQueryField("SELECT value FROM config WHERE variable = 'fuelChannelID'", 'value', array(), 'config'), //fuel alerts post to this channel
        'skip' => dbQueryField("SELECT value FROM config WHERE variable = 'fuelSkip'", 'value', array(), 'config'), //if you want fuel notifications to be skipped change this to true
    ),
    //Reports silos nearing max capacity.
    'siloFull' => array(
        'channelID' => dbQueryField("SELECT value FROM config WHERE variable = 'siloFullChannelID'", 'value', array(), 'config'), //silo alerts post to this channel
        'keyID' => dbQueryField("SELECT value FROM config WHERE variable = 'corpKeyID1'", 'value', array(), 'config'), //corp api keyID (Must have assets)
        'vCode' => dbQueryField("SELECT value FROM config WHERE variable = 'corpvCode1'", 'value', array(), 'config'), //corp api vCode
        'towerRace' => dbQueryField("SELECT value FROM config WHERE variable = 'towerRace'", 'value', array(), 'config'), //The race of your moon goo towers (to determine silo bonus.) Amarr/Amarr Faction Variants = 1, Gal/Gal Faction Variants = 2, Everyone else = 0
    ),
    //Fleet up linking will share operations to a specific channel and then reping them when it gets within 30 minutes of form up
    'fleetUp' => array(
        'channelID' => dbQueryField("SELECT value FROM config WHERE variable = 'fleetUpChannelID'", 'value', array(), 'config'), //channel id to ping about operations
        'userID' => dbQueryField("SELECT value FROM config WHERE variable = 'fleetUpUserID'", 'value', array(), 'config'), //fleet up user id
        'groupID' => dbQueryField("SELECT value FROM config WHERE variable = 'fleetUpGroupID'", 'value', array(), 'config'), //fleet up group id
        'apiKey' => dbQueryField("SELECT value FROM config WHERE variable = 'apiKey'", 'value', array(), 'config'), //fleet up api code, link to application Dramiel Bot
    ),
    //Post well formatted rss feed links to a channel
    'rssReader' => array(
        'channelID' => dbQueryField("SELECT value FROM config WHERE variable = 'rssReaderChannelID'", 'value', array(), 'config'), //channel id to post rss links
        'rssFeeds' => array( //feel free to add more url's if needed
            'url1' => dbQueryField("SELECT value FROM config WHERE variable = 'url1'", 'value', array(), 'config'),
            'url2' => dbQueryField("SELECT value FROM config WHERE variable = 'url2'", 'value', array(), 'config'),
            'url3' => dbQueryField("SELECT value FROM config WHERE variable = 'url3'", 'value', array(), 'config'),
            'url4' => '',
            'url5' => '',
        )
    ),
);
