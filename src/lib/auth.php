<?php

// Add role
function addRole($discordWeb, $guildID, $eveName, $userID, $roleID, $logger)
{
    $logger->addInfo("QueueProcessing- $eveName has had roles added");
    $discordWeb->guild->addGuildMemberRole(['guild.id' => (int)$guildID, 'user.id' => (int)$userID, 'role.id' => (int)$roleID]);
}

// Rename
function renameUser($discordWeb, $guildID, $eveName, $userID, $nick, $logger)
{
    $nick = preg_replace('/\s+/', ' ', $nick);
    $logger->addInfo("QueueProcessing- $eveName has been renamed to $nick");
    $discordWeb->guild->modifyGuildMember(['guild.id' => (int)$guildID, 'user.id' => (int)$userID, 'nick' => (string)$nick]);
}