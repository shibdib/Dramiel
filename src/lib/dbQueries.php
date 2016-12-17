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
 * @param $message
 * @param $channel
 * @param $guild
 * @return array|bool
 */

//MESSAGE QUEUE
function queueMessage($message, $channel, $guild)
{
    dbExecute('REPLACE INTO messageQueue (`message`, `channel`, `guild`) VALUES (:message,:channel,:guild)', array(':message' => $message, ':channel' => $channel, ':guild' => $guild));
    return null;
}

function getQueuedMessage($id)
{
    return dbQueryRow('SELECT * FROM messageQueue WHERE `id` = :id', array(':id' => $id));
}

function clearQueuedMessages($id)
{
    dbQueryRow('DELETE from messageQueue where id = :id', array(':id' => $id));
    return null;
}

function getOldestMessage()
{
    return dbQueryRow('SELECT MIN(id) from messageQueue');
}

function priorityQueueMessage($message, $channel, $guild)
{
    $currentOldest = getOldestMessage();
    $id = $currentOldest['MIN(id)'] - 1;
    dbExecute('REPLACE INTO messageQueue (`id`, `message`, `channel`, `guild`) VALUES (:id,:message,:channel,:guild)', array(':id' => $id, ':message' => $message, ':channel' => $channel, ':guild' => $guild));
}


//RENAME QUEUE
function queueRename($discordID, $nick, $guild)
{
    dbExecute('REPLACE INTO renameQueue (`discordID`, `nick`, `guild`) VALUES (:discordID,:nick,:guild)', array(':discordID' => $discordID, ':nick' => $nick, ':guild' => $guild));
}

function getQueuedRename($id)
{
    return dbQueryRow('SELECT * FROM renameQueue WHERE `id` = :id', array(':id' => $id));
}

function getOldestRename()
{
    return dbQueryRow('SELECT MIN(id) from renameQueue');
}

function clearQueuedRename($id)
{
    dbQueryRow('DELETE from renameQueue where id = :id', array(':id' => $id));
    return null;
}

//
function clearQueueCheck()
{
    $result = dbQueryRow('SELECT * FROM messageQueue');
    if (@$result->num_rows > 35) {
        clearAllMessageQueue();
    }
    return null;
}

//Clear Queue
function clearAllMessageQueue()
{
    dbQueryRow('DELETE from messageQueue');
    return null;
}

//CORP INFO
function addCorpInfo($corpID, $corpTicker, $corpName)
{
    dbExecute('REPLACE INTO corpCache (`corpID`, `corpTicker`, `corpName`) VALUES (:corpID,:corpTicker,:corpName)', array(':corpID' => $corpID, ':corpTicker' => $corpTicker, ':corpName' => $corpName));
}

function getCorpInfo($corpID)
{
    return dbQueryRow('SELECT * FROM corpCache WHERE `corpID` = :corpID', array(':corpID' => $corpID));
}

function deleteCorpInfo($corpID)
{
    return dbQueryRow('DELETE from corpCache WHERE `corpID` = :corpID', array(':corpID' => $corpID));
}

//Remove old DB's
function dbPrune()
{
    $oldDatabases = array('corpIDs', 'users', 'usersSeen');
    foreach ($oldDatabases as $db) {
        dbExecute("DROP TABLE IF EXISTS $db");
    }
}