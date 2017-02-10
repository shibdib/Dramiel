<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

function zKillRedis()
{
    try {
        // Initialize a new request for this URL
        $ch = curl_init("http://redisq.zkillboard.com/listen.php");
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
        return null;
    }
    return $data['package'];
}

function getStartMail($kmGroup)
{

    if ($kmGroup['allianceID'] === '0' && $kmGroup['lossMails'] === 'true' && $kmGroup['corpID'] !== '0') {
        $url = "https://zkillboard.com/api/no-attackers/no-items/corporationID/{$kmGroup['corpID']}/limit/1/";
    }
    if ($kmGroup['allianceID'] === '0' && $kmGroup['lossMails'] === 'false' && $kmGroup['corpID'] !== '0') {
        $url = "https://zkillboard.com/api/no-attackers/no-items/kills/corporationID/{$kmGroup['corpID']}/limit/1/";
    }
    if ($kmGroup['allianceID'] !== '0' && $kmGroup['lossMails'] === 'true' && $kmGroup['allianceID'] !== '0') {
        $url = "https://zkillboard.com/api/no-attackers/no-items/allianceID/{$kmGroup['allianceID']}/limit/1/";
    }
    if ($kmGroup['allianceID'] !== '0' && $kmGroup['lossMails'] === 'false' && $kmGroup['allianceID'] !== '0') {
        $url = "https://zkillboard.com/api/no-attackers/no-items/kills/allianceID/{$kmGroup['allianceID']}/limit/1/";
    }

    if (!isset($url)) { // Make sure it's always set.
        return null;
    }

    $kill = json_decode(downloadData($url), true);
    if (null !== $kill) {
        foreach ($kill as $mail) {
            $killID = $mail['killID'];
            setPermCache("{$kmGroup['corpID']}-{$kmGroup['allianceID']}-newestKillmailID", $killID);
        }
    }
}

function getStartBigMail()
{
    $url = 'https://zkillboard.com/api/kills/orderDirection/desc/iskValue/10000000000/';
    if (!isset($url)) { // Make sure it's always set.
        return null;
    }
    $kill = json_decode(downloadData($url), true);
    if (null !== $kill) {
        foreach ($kill as $mail) {
            $killID = $mail['killID'];
            setPermCache('bigKillNewestKillmailID', $killID);
        }
    }
}