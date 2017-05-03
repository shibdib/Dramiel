<?php

// Message queue
function messageQueue($discord, $logger)
{
    $x = 0;
    while ($x < 3) {
        $id = getOldestMessage();
        $id = $id['MIN(id)'];
        if (null === $id) {
            $id = 1;
        }
        $queuedMessage = getQueuedMessage($id);
        if (null !== $queuedMessage) {
            //Check if queued item is corrupt and delete it if it is
            if (null === $queuedMessage['guild'] || null === $queuedMessage['channel'] || null === $queuedMessage['message']) {
                $logger->addInfo("QueueProcessing Error- Item #{$id} : Queued item is badly formed, removing it from the queue");
                clearQueuedMessages($id);
                continue;
            }
            $guild = $discord->guilds->get('id', $queuedMessage['guild']);
            //Check if guild is bad
            if (null === $guild) {
                $logger->addInfo("QueueProcessing Error- Item #{$id} : Guild provided is incorrect, removing it from the queue");
                clearQueuedMessages($id);
                continue;
            }
            $channel = $guild->channels->get('id', (int)$queuedMessage['channel']);
            //Check if channel is bad
            if (null === $channel) {
                $logger->addInfo("QueueProcessing Error- Item #{$id} : Channel provided is incorrect, removing it from the queue");
                clearQueuedMessages($id);
                continue;
            }
            $logger->addInfo("QueueProcessing - Completing queued item #{$id}");
            $channel->sendMessage($queuedMessage['message'], false, null);
            clearQueuedMessages($id);
        }else{
            $x = 99;
        }
        $x++;
    }
}

// Rename queue
function renameQueue($discord, $discordWeb, $logger)
{
    $x = 0;
    while ($x < 4) {
        $id = getOldestRename();
        $id = $id['MIN(id)'];
        if (null === $id) {
            $id = 1;
            $x = 4;
        }
        $queuedRename = getQueuedRename($id);
        if (null !== $queuedRename) {
            //Check if queued item is corrupt and delete it if it is
            if (null === $queuedRename['guild'] || null === $queuedRename['discordID']) {
                clearQueuedRename($id);
                continue;
            }
            //make sure nick is short enough
            if (strlen($queuedRename['nick']) > 31) {
                clearQueuedRename($id);
                continue;
            }
            $guildID = $queuedRename['guild'];
            $userID = $queuedRename['discordID'];
            $nick = $queuedRename['nick'];
            $guild = $discord->guilds->get('id', $queuedRename['guild']);
            $member = $guild->members->get('id', $queuedRename['discordID']);
            $discordWeb->guild->modifyGuildMember(['guild.id' => (int)$guildID, 'user.id' => (int)$userID, 'nick' => (string)$nick]);
            $nickName = $member->nick;
            $success = null;
            if ($nickName === $queuedRename['nick']){
                $success = true;
            }
            if(is_null($success))
            {
                $y = 0;
                while ($y < 3 && is_null($success)) {
                    $guild = $discord->guilds->get('id', $queuedRename['guild']);
                    $member = $guild->members->get('id', $queuedRename['discordID']);
                    $discordWeb->guild->modifyGuildMember(['guild.id' => (int)$guildID, 'user.id' => (int)$userID, 'nick' => (string)$nick]);
                    $nickName = $member->nick;
                    if ($nickName === $queuedRename['nick']){
                        $success = true;
                    }
                    $y++;
                }
                //purge queue if fails 4 times
                if ($y > 3 && is_null($success)) {
                    clearQueuedRename($id);
                    continue;
                }
            }
            if(!is_null($success)){
                $logger->addInfo("QueueProcessing - New name set for $nickName");
                clearQueuedRename($id);
                continue;
            }
        }else{
            $x = 99;
        }
        $x++;
    }
}

// Auth queue
function authQueue($discord, $discordWeb, $logger)
{
    $x = 0;
    while ($x < 4) {
        $id = getOldestQueuedAuth();
        $id = $id['MIN(id)'];
        if (null === $id) {
            $id = 1;
            $x = 4;
        }
        $queuedAuth = getQueuedAuth($id);
        if (null !== $queuedAuth) {
            //Check if queued item is corrupt and delete it if it is
            if (null === $queuedAuth['roleID'] || null === $queuedAuth['discordID']) {
                clearQueuedAuth($id);
                continue;
            }
            $guildID = $queuedAuth['guildID'];
            $userID = $queuedAuth['discordID'];
            $roleID = $queuedAuth['roleID'];
            $discordWeb->guild->addGuildMemberRole(['guild.id' => (int)$guildID, 'user.id' => (int)$userID, 'role.id' => (int)$roleID]);
            $guild = $discord->guilds->get('id', $queuedAuth['guildID']);
            $member = $guild->members->get('id', $queuedAuth['discordID']);
            dbExecute('DELETE from authUsers WHERE `discordID` = :discordID', array(':discordID' => (string)$queuedAuth['discordID']), 'auth');
            $eveName = $queuedAuth['eveName'];
            $roles = $member->roles;
            $success = null;
            foreach ($roles as $role) {
                if ((string)$role->id === (string)$queuedAuth['roleID']) {
                    $logger->addInfo("QueueProcessing - Role added successfully for $eveName");
                    insertNewUser($queuedAuth['discordID'], $queuedAuth['charID'], $queuedAuth['eveName'], $queuedAuth['pendingID'], $queuedAuth['groupName']);
                    clearQueuedAuth($id);
                    $success = true;
                    break;
                }
            }
            if(is_null($success))
            {
                $y = 0;
                while ($y < 3 && is_null($success)) {
                    $guild = $discord->guilds->get('id', $queuedAuth['guildID']);
                    $member = $guild->members->get('id', $queuedAuth['discordID']);
                    $discordWeb->guild->addGuildMemberRole(['guild.id' => (int)$guildID, 'user.id' => (int)$userID, 'role.id' => (int)$roleID]);
                    $roles = $member->roles;
                    foreach ($roles as $role) {
                        if ((string)$role->id === (string)$queuedAuth['roleID']) {
                            $logger->addInfo("QueueProcessing - Role added successfully for $eveName");
                            insertNewUser($queuedAuth['discordID'], $queuedAuth['charID'], $queuedAuth['eveName'], $queuedAuth['pendingID'], $queuedAuth['groupName']);
                            clearQueuedAuth($id);
                            $success = true;
                            break;
                        }
                    }
                    $y++;
                }
                //purge queue if fails 4 times
                if ($y > 3 && is_null($success)) {
                    clearQueuedAuth($id);
                }
            }
        }else{
            $x = 99;
        }
        $x++;
    }
}