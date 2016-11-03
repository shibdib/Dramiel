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
 * @return SimpleXMLElement|null|string
 */
function makeApiRequest($url)
{
    $logger = new Logger('eveApi');
    $logger->pushHandler(new StreamHandler(__DIR__ . '../../log/libraryError.log', Logger::DEBUG));
    try {
        // Initialize a new request for this URL
        $ch = curl_init($url);
        // Set the options for this request
        curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
            CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
            CURLOPT_SSL_VERIFYPEER => false, // Do not verify the SSL certificate
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
        $logger->error("EVE API Error: " . $e->getMessage());
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
        $ch = curl_init("https://api.eveonline.com/server/ServerStatus.xml.aspx");
        // Set the options for this request
        curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
            CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false, // Do not verify the SSL certificate
        ));
        // Fetch the data from the URL
        $data = curl_exec($ch);
        // Close the connection
        curl_close($ch);

        $true = "true";
        //If server is down return false
        if ($data->serverOpen != "True") {
            return FALSE;
        }
        //If server is up return true
        return $true;
    } catch (Exception $e) {
        $logger->error("EVE API Error: " . $e->getMessage());
        return null;
    }
}

/**
 * @param string $typeID
 * @return mixed
 */
////Char/Object ID to name via CCP
function apiCharacterName($typeID)
{
    $url = "https://api.eveonline.com/eve/CharacterName.xml.aspx?IDs={$typeID}";
    $xml = makeApiRequest($url);
    foreach ($xml->result->rowset->row as $entity) {
        $name = $entity->attributes()->name;
    }

    if (!isset($name)) { // Make sure it's always set.
        $name = "Unknown";
    }

    return $name;
}

/**
 * @param string $typeName
 * @return mixed
 */
////Char/object name to ID via CCP
function apiCharacterID($typeName)
{
    $url = "https://api.eveonline.com/eve/CharacterID.xml.aspx?names={$typeName}";
    $xml = makeApiRequest($url);
    foreach ($xml->result->rowset->row as $entity) {
        $ID = $entity->attributes()->characterID;
    }

    if (!isset($ID)) { // Make sure it's always set.
        $ID = "Unknown";
    }

    return $ID;
}

/**
 * @param string $typeID
 * @return mixed
 */
////TypeID to TypeName via CCP
function apiTypeName($typeID)
{
    $url = "https://api.eveonline.com/eve/TypeName.xml.aspx?IDs={$typeID}";
    $xml = makeApiRequest($url);
    foreach ($xml->result->rowset->row as $entity) {
        $name = $entity->attributes()->typeName;
    }

    if (!isset($name)) { // Make sure it's always set.
        $name = "Unknown";
    }

    return $name;
}

/**
 * @param string $typeName
 * @return mixed
 */
////TypeID to TypeName via fuzz
function apiTypeID($typeName)
{
    $url = "https://www.fuzzwork.co.uk/api/typeid.php?typename={$typeName}&format=xml";
    $xml = makeApiRequest($url);
    foreach ($xml->result->rowset->row as $entity) {
        $ID = $entity->attributes()->typeID;
    }

    if (!isset($ID)) { // Make sure it's always set.
        $ID = "Unknown";
    }

    return $ID;
}