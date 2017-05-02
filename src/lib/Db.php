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

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * @param int $db
 * @return null|PDO
 * @internal param null|string $db
 */
function openDB($db = null)
{
    $logger = new Logger('Db');
    $logger->pushHandler(new StreamHandler(__DIR__ . '/../../log/libraryError.log', Logger::DEBUG));
    if ($db === 'auth') {
        $db = __DIR__ . '/../../database/authDB.sqlite';
    } elseif ($db === 'config') {
        $db = __DIR__ . '/../../../config/botConfig.sqlite';
    } elseif ($db === 'eve') {
        $db = __DIR__ . '/../../database/eveData.db';
    } else {
        $db = __DIR__ . '/../../database/dramiel.sqlite';
    }

    $dsn = "sqlite:$db";
    try {
        $pdo = new PDO($dsn, '', '', array(
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            )
        );
    } catch (Exception $e) {
        $logger->error($e->getMessage());
        $pdo = null;
        return $pdo;
    }

    return $pdo;
}

/**
 * @param string $query
 * @param string $field
 * @param array $params
 * @param null $db
 * @return string
 * @internal param string $db
 */
function dbQueryField($query, $field, array $params = array(), $db = null)
{
    $pdo = openDB($db);
    if ($pdo === NULL) {
        return null;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (count($result) == 0) {
        return null;
    }

    $resultRow = $result[0];
    return $resultRow[$field];
}

/**
 * @param string $query
 * @param array $params
 * @param string $db
 * @return null|void
 * @internal param string $db
 */
function dbQueryRow($query, array $params = array(), $db = null)
{
    $pdo = openDB($db);
    if ($pdo === NULL) {
        return null;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (count($result) >= 1) {
        return $result[0];
    }

    if (count($result) === 0) {
        return null;
    }

    return null;
}

/**
 * @param string $query
 * @param array $params
 * @param string $db
 * @return array|void
 * @internal param string $db
 */
function dbQuery($query, array $params = array(), $db = null)
{
    $pdo = openDB($db);
    if ($pdo === NULL) {
        return null;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    return $result;
}

/**
 * @param string $query
 * @param array $params
 * @param string $db
 * @internal param string $db
 */
function dbExecute($query, array $params = array(), $db = null)
{
    $pdo = openDB($db);
    if ($pdo === NULL) {
        return;
    }

    // This is ugly, but, yeah..
    if (strstr($query, ';')) {
        $explodedQuery = explode(';', $query);
        $stmt = null;
        foreach ($explodedQuery as $newQry) {
            $stmt = $pdo->prepare($newQry);
            $stmt->execute($params);
        }
        $stmt->closeCursor();
    } else {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $stmt->closeCursor();
    }
}

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
/**
 * @param $corpID
 * @param $corpTicker
 * @param string $corpName
 */
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

//Add Contacts
function addContactInfo($contactID, $contactName, $standing)
{
    dbExecute('REPLACE INTO contactList (`contactID`, `contactName`, `standing`) VALUES (:contactID,:contactName,:standing)', array(':contactID' => $contactID, ':contactName' => $contactName, ':standing' => $standing));
}

//Get Contacts
function getContacts($contactID)
{
    return dbQueryRow('SELECT * FROM contactList WHERE `contactID` = :contactID', array(':contactID' => $contactID));
}

//AUTH
function queueAuth($discordID, $charID, $eveName, $pendingID, $roleID, $groupName, $guildID)
{
    dbExecute('REPLACE INTO authQueue (`discordID`, `charID`, `eveName`, `pendingID`, `roleID`, `groupName`, `guildID`) VALUES (:discordID,:charID,:eveName,:pendingID,:roleID,:groupName,:guildID)', array(':discordID' => $discordID, ':charID' => $charID, ':eveName' => $eveName, ':pendingID' => $pendingID, ':roleID' => $roleID, ':groupName' => $groupName, ':guildID' => $guildID));
}

function getQueuedAuth($id)
{
    return dbQueryRow('SELECT * FROM authQueue WHERE `id` = :id', array(':id' => $id));
}

function getOldestQueuedAuth()
{
    return dbQueryRow('SELECT MIN(id) from authQueue');
}

function clearQueuedAuth($id)
{
    dbQueryRow('DELETE from authQueue where id = :id', array(':id' => $id));
    return null;
}

function getAuthUsers()
{
    $active = 'yes';
    $db = 'auth';
    return dbQuery('SELECT * FROM authUsers WHERE `active` = :active', array(':active' => $active), $db);
}

function getAuthUser($discordID)
{
    $active = 'yes';
    $db = 'auth';
    return dbQueryRow('SELECT * FROM authUsers WHERE `discordID` = :discordID AND `active` = :active', array(':discordID' => $discordID, ':active' => $active), $db);
}

function getPendingUser($code)
{
    $active = '1';
    $db = 'auth';
    return dbQueryRow('SELECT * FROM pendingUsers WHERE `authString` = :authString AND `active` = :active', array(':authString' => $code, ':active' => $active), $db);
}

/**
 * @param $userID
 * @param integer $charID
 * @param $eveName
 * @param integer $id
 * @param string $role
 */
function insertNewUser($userID, $charID, $eveName, $id, $role = 'corp')
{
    $active = 'yes';
    $db = 'auth';
    dbExecute('REPLACE into authUsers (`characterID`, `discordID`, `eveName`, `active`, `role`) VALUES (:characterID, :discordID, :eveName, :active, :role)', array(':characterID' => $charID, ':discordID' => $userID, ':eveName' => (string) $eveName, ':active' => $active, ':role' => (string) $role), $db);
    dbExecute('DELETE from pendingUsers WHERE `id` = :id', array(':id' => $id), $db);
}

function disableUser($discordID)
{
    $active = 'no';
    $db = 'auth';
    dbExecute('UPDATE authUsers SET `active`=:active WHERE `discordID` = :discordID', array(':active' => $active, ':discordID' => $discordID), $db);
}

function fixRole($discordID, $role)
{
    $db = 'auth';
    dbExecute('UPDATE authUsers SET `role`=:role WHERE `discordID` = :discordID', array(':role' => $role, ':discordID' => $discordID), $db);
}

//eveDb interaction
function getTypeID($typeName)
{
    $query = dbQueryRow('SELECT * FROM invTypes WHERE lower(`typeName`) = :typeName', array(':typeName' => $typeName), 'eve');
    return $query['typeID'];
}

function getTypeName($typeID)
{
    $query = dbQueryRow('SELECT * FROM invTypes WHERE `typeID` = :typeID', array(':typeID' => $typeID), 'eve');
    return $query['typeName'];
}

function getSystemID($solarSystemName)
{
    $query = dbQueryRow('SELECT * FROM mapSolarSystems WHERE lower(`solarSystemName`) = :solarSystemName', array(':solarSystemName' => $solarSystemName), 'eve');
    return $query['solarSystemID'];
}

function getSystemName($systemID)
{
    $query = dbQueryRow('SELECT * FROM mapSolarSystems WHERE `solarSystemID` = :systemID', array(':systemID' => $systemID), 'eve');
    return $query['solarSystemName'];
}

function getRegionID($regionName)
{
    $query = dbQueryRow('SELECT * FROM mapRegions WHERE lower(`regionName`) = :regionName', array(':regionName' => $regionName), 'eve');
    return $query['regionName'];
}

function getRegionName($regionID)
{
    $query = dbQueryRow('SELECT * FROM mapRegions WHERE `regionID` = :regionID', array(':regionID' => $regionID), 'eve');
    return $query['regionName'];
}