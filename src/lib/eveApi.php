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
 * @param $url
 * @return SimpleXMLElement|null
 */
function makeApiRequest($url)
{
    $logger = new Logger('eveApi');
    $logger->pushHandler(new StreamHandler(__DIR__ . '/../../log/libraryError.log', Logger::DEBUG));
    try {
        // Initialize a new request for this URL
        $ch = curl_init($url);
        // Set the options for this request
        curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
            CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
            CURLOPT_SSL_VERIFYPEER => true, // Do not verify the SSL certificate
            CURLOPT_USERAGENT => 'Dramiel Discord Bot - https://github.com/shibdib/Dramiel', // Useragent
            CURLOPT_TIMEOUT => 15,
        ));
        // Fetch the data from the URL
        $data = curl_exec($ch);
        // Close the connection
        curl_close($ch);
        // Return a new SimpleXMLElement based upon the received data
        return new SimpleXMLElement($data);
    } catch (Exception $e) {
        $logger->error('EVE API Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * @return mixed|null
 */

function serverStatus()
{
    $logger = new Logger('eveApi');
    $logger->pushHandler(new StreamHandler(__DIR__ . '../../log/libraryError.log', Logger::DEBUG));
    try {
        // Initialize a new request for this URL
        $ch = curl_init('https://api.eveonline.com/server/ServerStatus.xml.aspx');
        // Set the options for this request
        curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
            CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true, // Do not verify the SSL certificate
        ));
        // Fetch the data from the URL
        $data = curl_exec($ch);
        // Close the connection
        curl_close($ch);

        $true = 'true';
        //If server is down return false
        if ($data->serverOpen !== 'True') {
            return FALSE;
        }
        //If server is up return true
        return $true;
    } catch (Exception $e) {
        $logger->error('EVE API Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * @param string $characterID
 * @return mixed
 */
////Char ID to name via CCP
function characterName($characterID)
{
    $character = characterDetails($characterID);
    $name = (string) $character['name'];
    if (null === $name || '' === $name) { // Make sure it's always set.
        $url = "https://api.eveonline.com/eve/CharacterName.xml.aspx?ids={$characterID}";
        $xml = makeApiRequest($url);
        foreach ($xml->result->rowset->row as $entity) {
            $name = $entity->attributes()->name;
        }
    }
    return $name;
}

/**
 * @param string $characterName
 * @return mixed
 */
////Character name to ID
/**
 * @param string $characterName
 *
 * @return string
 */
function characterID($characterName)
{
    $logger = new Logger('eveESI');
    $logger->pushHandler(new StreamHandler(__DIR__ . '../../log/libraryError.log', Logger::DEBUG));
    $characterName = urlencode($characterName);

    try {
        // Initialize a new request for this URL
        $ch = curl_init("https://esi.tech.ccp.is/latest/search/?search={$characterName}&categories=character&language=en-us&strict=true&datasource=tranquility");
        // Set the options for this request
        curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
            CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true, // Do not verify the SSL certificate
        ));
        // Fetch the data from the URL
        $data = curl_exec($ch);
        // Close the connection
        curl_close($ch);
        $data = json_decode($data, TRUE);
        $id = (int) $data['character'][0];

    } catch (Exception $e) {
        $logger->error('EVE ESI Error: ' . $e->getMessage());
        $url = "https://api.eveonline.com/eve/CharacterID.xml.aspx?names={$characterName}";
        $xml = makeApiRequest($url);
        foreach ($xml->result->rowset->row as $entity) {
            $id = $entity->attributes()->characterID;
        }
    }

    return $id;
}

/**
 * @param string $characterID
 * @return mixed
 */
////Char ID to char data via CCP
function characterDetails($characterID)
{
    $logger = new Logger('eveESI');
    $logger->pushHandler(new StreamHandler(__DIR__ . '../../log/libraryError.log', Logger::DEBUG));

    try {
        // Initialize a new request for this URL
        $ch = curl_init("https://esi.tech.ccp.is/latest/characters/{$characterID}/");
        // Set the options for this request
        curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
            CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true, // Do not verify the SSL certificate
        ));
        // Fetch the data from the URL
        $data = curl_exec($ch);
        // Close the connection
        curl_close($ch);
        $data = json_decode($data, TRUE);

    } catch (Exception $e) {
        $logger->error('EVE ESI Error: ' . $e->getMessage());
        return null;
    }

    return $data;
}

/**
 * @param string $systemID
 * @return mixed
 */
////System ID to name via CCP
function systemName($systemID)
{
    $logger = new Logger('eveESI');
    $logger->pushHandler(new StreamHandler(__DIR__ . '../../log/libraryError.log', Logger::DEBUG));

    try {
        // Initialize a new request for this URL
        $ch = curl_init("https://esi.tech.ccp.is/latest/universe/systems/{$systemID}/");
        // Set the options for this request
        curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
            CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true, // Do not verify the SSL certificate
        ));
        // Fetch the data from the URL
        $data = curl_exec($ch);
        // Close the connection
        curl_close($ch);
        $data = json_decode($data, TRUE);
        $name = (string) $data['name'];

    } catch (Exception $e) {
        $logger->error('EVE ESI Error: ' . $e->getMessage());
        $url = "https://api.eveonline.com/eve/CharacterName.xml.aspx?ids={$systemID}";
        $xml = makeApiRequest($url);
        foreach ($xml->result->rowset->row as $entity) {
            $name = $entity->attributes()->name;
        }
    }

    return $name;
}

/**
 * @param string $corpName
 * @return mixed
 */
////Corp name to ID
/**
 * @param string $corpName
 */
function corpID($corpName)
{
    $logger = new Logger('eveESI');
    $logger->pushHandler(new StreamHandler(__DIR__ . '../../log/libraryError.log', Logger::DEBUG));
    $corpName = urlencode($corpName);

    try {
        // Initialize a new request for this URL
        $ch = curl_init("https://esi.tech.ccp.is/latest/search/?search={$corpName}&categories=corporation&language=en-us&strict=true&datasource=tranquility");
        // Set the options for this request
        curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
            CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true, // Do not verify the SSL certificate
        ));
        // Fetch the data from the URL
        $data = curl_exec($ch);
        // Close the connection
        curl_close($ch);
        $data = json_decode($data, TRUE);
        $id = (int) $data['corporation'][0];

    } catch (Exception $e) {
        $logger->error('EVE ESI Error: ' . $e->getMessage());
        $url = "https://api.eveonline.com/eve/CharacterID.xml.aspx?names={$corpName}";
        $xml = makeApiRequest($url);
        foreach ($xml->result->rowset->row as $entity) {
            $id = $entity->attributes()->characterID;
        }
    }

    return $id;
}


/**
 * @param string $corpID
 * @return mixed
 */
////Corp ID to name via CCP
function corpName($corpID)
{
    $corporation = corpDetails($corpID);
    $name = (string) $corporation['corporation_name'];
    if (null === $name || '' === $name) { // Make sure it's always set.
        $url = "https://api.eveonline.com/eve/CharacterName.xml.aspx?ids={$corpID}";
        $xml = makeApiRequest($url);
        foreach ($xml->result->rowset->row as $entity) {
            $name = $entity->attributes()->name;
        }
    }

    return $name;
}

/**
 * @param string $corpID
 * @return mixed
 */
////Corp ID to corp data via CCP
function corpDetails($corpID)
{
    $logger = new Logger('eveESI');
    $logger->pushHandler(new StreamHandler(__DIR__ . '../../log/libraryError.log', Logger::DEBUG));

    try {
        // Initialize a new request for this URL
        $ch = curl_init("https://esi.tech.ccp.is/latest/corporations/{$corpID}/");
        // Set the options for this request
        curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
            CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true, // Do not verify the SSL certificate
        ));
        // Fetch the data from the URL
        $data = curl_exec($ch);
        // Close the connection
        curl_close($ch);
        $data = json_decode($data, TRUE);

    } catch (Exception $e) {
        $logger->error('EVE ESI Error: ' . $e->getMessage());
        return null;
    }

    return $data;
}

/**
 * @param string $allianceID
 * @return mixed
 */
////Alliance ID to name via CCP
function allianceName($allianceID)
{
    $logger = new Logger('eveESI');
    $logger->pushHandler(new StreamHandler(__DIR__ . '../../log/libraryError.log', Logger::DEBUG));

    try {
        // Initialize a new request for this URL
        $ch = curl_init("https://esi.tech.ccp.is/latest/alliances/{$allianceID}/");
        // Set the options for this request
        curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
            CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true, // Do not verify the SSL certificate
        ));
        // Fetch the data from the URL
        $data = curl_exec($ch);
        // Close the connection
        curl_close($ch);
        $data = json_decode($data, TRUE);
        $name = (string) $data['alliance_name'];

    } catch (Exception $e) {
        $logger->error('EVE ESI Error: ' . $e->getMessage());
        $url = "https://api.eveonline.com/eve/CharacterName.xml.aspx?ids={$allianceID}";
        $xml = makeApiRequest($url);
        foreach ($xml->result->rowset->row as $entity) {
            $name = $entity->attributes()->name;
        }
    }

    return $name;
}

/**
 * @param string $systemName
 * @return mixed
 */
////System name to ID
function systemID($systemName)
{
    $logger = new Logger('eveESI');
    $logger->pushHandler(new StreamHandler(__DIR__ . '../../log/libraryError.log', Logger::DEBUG));
    $systemName = urlencode($systemName);

    try {
        // Initialize a new request for this URL
        $ch = curl_init("https://esi.tech.ccp.is/latest/search/?search={$systemName}&categories=solarsystem&language=en-us&strict=true&datasource=tranquility");
        // Set the options for this request
        curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
            CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true, // Do not verify the SSL certificate
        ));
        // Fetch the data from the URL
        $data = curl_exec($ch);
        // Close the connection
        curl_close($ch);
        $data = json_decode($data, TRUE);
        $id = (int) $data['solarsystem'][0];

    } catch (Exception $e) {
        $logger->error('EVE ESI Error: ' . $e->getMessage());
        $url = "https://api.eveonline.com/eve/CharacterID.xml.aspx?names={$systemName}";
        $xml = makeApiRequest($url);
        foreach ($xml->result->rowset->row as $entity) {
            $id = $entity->attributes()->characterID;
        }
    }

    return $id;
}

/**
 * @param string $typeID
 * @return mixed
 */
////TypeID to TypeName via CCP
function apiTypeName($typeID)
{
    $logger = new Logger('eveESI');
    $logger->pushHandler(new StreamHandler(__DIR__ . '../../log/libraryError.log', Logger::DEBUG));

    try {
        // Initialize a new request for this URL
        $ch = curl_init("https://esi.tech.ccp.is/latest/universe/types/{$typeID}/");
        // Set the options for this request
        curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
            CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true, // Do not verify the SSL certificate
        ));
        // Fetch the data from the URL
        $data = curl_exec($ch);
        // Close the connection
        curl_close($ch);
        $data = json_decode($data, TRUE);
        $name = (string) $data['name'];

    } catch (Exception $e) {
        $logger->error('EVE ESI Error: ' . $e->getMessage());
        $url = "https://api.eveonline.com/eve/TypeName.xml.aspx?ids={$typeID}";
        $xml = makeApiRequest($url);
        foreach ($xml->result->rowset->row as $entity) {
            $name = $entity->attributes()->typeName;
        }
    }

    return $name;
}

/**
 * @param string $typeName
 * @return mixed
 */
////TypeName to TypeID via fuzz
function apiTypeID($typeName)
{
    $logger = new Logger('eveESI');
    $logger->pushHandler(new StreamHandler(__DIR__ . '../../log/libraryError.log', Logger::DEBUG));
    $typeName = urlencode($typeName);
    try {
        // Initialize a new request for this URL
        $ch = curl_init("https://www.fuzzwork.co.uk/api/typeid.php?typename={$typeName}");
        // Set the options for this request
        curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
            CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true, // Do not verify the SSL certificate
        ));
        // Fetch the data from the URL
        $data = curl_exec($ch);
        // Close the connection
        curl_close($ch);
        $data = json_decode($data, TRUE);

    } catch (Exception $e) {
        $logger->error('Fuzzwork Error: ' . $e->getMessage());
        return null;
    }
    $id = (int) $data['typeID'];

    if (null === $id) { // Make sure it's always set.
        $id = 'Unknown';
    }

    return $id;
}

////Char/Object ID to name via CCP
/**
 * @param string $moonID
 */
function apiMoonName($moonID)
{
    $url = "https://api.eveonline.com/eve/CharacterName.xml.aspx?IDs={$moonID}";
    $xml = makeApiRequest($url);
    $name = null;
    foreach ($xml->result->rowset->row as $entity) {
        $name = $entity->attributes()->name;
    }
    if (null === $name) { // Make sure it's always set.
        $name = 'Unknown';
    }
    return $name;
}