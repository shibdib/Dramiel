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
            $message = $channel->sendMessage($queuedMessage['message'], false, null);
            while ($message === FALSE){
                $message = $channel->sendMessage($queuedMessage['message'], false, null);
            }
            clearQueuedMessages($id);
        } else {
            $x = 99;
        }
        $x++;
    }
}

// Rename queue
function renameQueue($discord, $logger)
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
            if (null === $queuedRename['guild'] || null === $queuedRename['discordID'] || strlen($queuedRename['nick']) > 32) {
                clearQueuedRename($id);
                continue;
            }
            $guild = $discord->guilds->get('id', $queuedRename['guild']);
            $member = $guild->members->get('id', $queuedRename['discordID']);
            $setNick = $member->setNickname($queuedRename['nick']);
            $y = 0;
            while ($setNick === FALSE && $y < 2){
                sleep(3);
                $setNick = $member->setNickname($queuedRename['nick']);
                $y++;
            }
            if ($setNick = TRUE) {
                $guild->members->save($member);
                $nickName = $member->nick;
                $logger->addInfo("QueueProcessing - New name set for $nickName");
                clearQueuedRename($id);
            }
        } else {
            $x = 99;
        }
        $x++;
    }
}

// Auth queue
function authQueue($discord, $logger)
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
            $guild = $discord->guilds->get('id', $queuedAuth['guildID']);
            $member = $guild->members->get('id', $queuedAuth['discordID']);
            $role = $guild->roles->get('id', $queuedAuth['roleID']);
            dbExecute('DELETE from authUsers WHERE `discordID` = :discordID', array(':discordID' => (string)$queuedAuth['discordID']), 'auth');
            $auth = $member->addRole($role);
            $y = 0;
            while ($auth === FALSE && $y < 2){
                sleep(3);
                $auth = $member->addRole($role);
                $y++;
            }
            if ($auth = TRUE) {
                $guild->members->save($member);
                $eveName = $queuedAuth['eveName'];
                $logger->addInfo("QueueProcessing - Role added successfully for $eveName");
                clearQueuedAuth($id);
            }
        } else {
            $x = 99;
        }
        $x++;
    }
}