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

use discord\discord;

/**
 * Class corporationmails
 * @property  keyID
 * @property  vCode
 * @property  characterID
 */
class evemails
{
    public $config;
    public $discord;
    public $logger;
    private $nextCheck;
    private $toIDs;
    private $toDiscordChannel;
    private $newestMailID;
    private $maxID;
    private $apiKey;
    private $numberOfKeys;
    private $guild;

    /**
     * @param $config
     * @param $discord
     * @param $logger
     */
    public function init($config, $discord, $logger)
    {
        $this->config = $config;
        $this->discord = $discord;
        $this->logger = $logger;
        $this->toIDs = $config['plugins']['evemails']['fromIDs'];
        $this->toDiscordChannel = $config['plugins']['evemails']['channelID'];
        $this->newestMailID = getPermCache('newestCorpMailID');
        $this->maxID = 0;
        $this->apiKey = $config['eve']['apiKeys'];
        $this->guild = $config['bot']['guild'];
        $this->nextCheck = 0;

        //Get number of keys
        $x = 0;
        foreach ($this->apiKey as $apiKey) {
            //Check if api is set
            if ($apiKey['keyID'] === '' || $apiKey['vCode'] === '' || $apiKey['characterID'] === null) {
                continue;
            }
            $x++;
        }
        $this->numberOfKeys = $x;
    }

    /**
     *
     */
    public function tick()
    {
        // What was the servers last reported state
        $lastStatus = getPermCache('serverState');
        if ($lastStatus == 'online') {
            foreach ($this->apiKey as $apiKey) {
                //Check if api is set
                if ($apiKey['keyID'] === '' || $apiKey['vCode'] === '' || $apiKey['characterID'] === null) {
                    continue;
                }
                //Get
                $lastChecked = getPermCache('mailLastChecked');
                if ($lastChecked <= time()) {
                    $lastCheckedAPI = getPermCache("mailLastChecked{$apiKey['keyID']}");
                    if ($lastCheckedAPI <= time()) {
                        $this->logger->addInfo("Mails: Checking API Key {$apiKey['keyID']} for new mail...");
                        $this->checkMails($apiKey['keyID'], $apiKey['vCode'], $apiKey['characterID']);
                    }
                }
            }
        }
    }

    private function checkMails($keyID, $vCode, $characterID)
    {

        $url = "https://api.eveonline.com/char/MailMessages.xml.aspx?keyID={$keyID}&vCode={$vCode}&characterID={$characterID}";
        $data = json_decode(json_encode(simplexml_load_string(downloadData($url), 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        $xml = makeApiRequest($url);
        $cached = $xml->cachedUntil[0];
        $baseUnix = strtotime($cached);
        $cacheClr = $baseUnix - 13500;

        //Set timer for next key based on number of keys
        $nextKey = (1900 / (int)$this->numberOfKeys) + time();
        $nextKeyTime = gmdate('Y-m-d H:i:s', $nextKey);
        setPermCache('mailLastChecked', $nextKey);

        //Set cache timer for api key
        if ($cacheClr <= time()) {
            $weirdTime = time() + 1830;
            setPermCache("mailLastChecked{$keyID}", $weirdTime);
        } else {
            setPermCache("mailLastChecked{$keyID}", $cacheClr);
        }

        // If there is no data, just quit..
        if (empty($data['result']['rowset']['row'])) {
            return null;
        }
        $data = $data['result']['rowset']['row'];

        $mails = array();
        if (isset($data['@attributes'])) {
            $mails[] = $data['@attributes'];
        }
        // Sometimes there is only ONE notification, so.. yeah..
        if (count($data) > 1) {
            foreach ($data as $multiMail) {
                $mails[] = $multiMail['@attributes'];
            }
        }

        usort($mails, array($this, 'sortByDate'));

        foreach ($mails as $mail) {
            if (in_array($mail['toCorpOrAllianceID'], $this->toIDs) && $mail['messageID'] > $this->newestMailID) {
                $sentBy = $mail['senderName'];
                $title = $mail['title'];
                $sentDate = $mail['sentDate'];
                $url = "https://api.eveonline.com/char/MailBodies.xml.aspx?keyID={$keyID}&vCode={$vCode}&characterID={$characterID}&ids=" . $mail['messageID'];
                $content = strip_tags(str_replace('<br>', "\n", json_decode(json_encode(simplexml_load_string(downloadData($url), 'SimpleXMLElement', LIBXML_NOCDATA)))->result->rowset->row));

                // Blank Content Check
                if ($content === '') {
                    return null;
                }

                $messageSplit = str_split($content, 1850);

                // Stitch the mail together
                $msg = "**Mail By: **{$sentBy}\n";
                $msg .= "**Sent Date: **{$sentDate}\n";
                $msg .= "**Title: ** {$title}\n";
                $msg .= "**Content: **\n";
                $msg .= htmlspecialchars_decode(trim($messageSplit[0]), null);
                $msgLong = htmlspecialchars_decode(trim($messageSplit[1]), null);

                // Send the mails to the channel
                $channelID = $this->toDiscordChannel;
                queueMessage($msg, $channelID, $this->guild);
                $this->logger->addInfo('Mails: New mail queued');
                if (strlen($content) > 1850) {
                    queueMessage($msgLong, $channelID, $this->guild);
                }

                // Find the maxID so we don't spit this message out ever again
                $this->maxID = max($mail['messageID'], $this->maxID);
                $this->newestMailID = $this->maxID; //$mail["messageID"];
                $updateMaxID = true;

                // set the maxID
                if ($updateMaxID) {
                    setPermCache('newestCorpMailID', $this->maxID);
                }
            }
        }
        $this->logger->addInfo("Mails: Next Mail Check At: {$nextKeyTime} EVE Time");
    }

    /**
     * @param $alpha
     * @param $bravo
     * @return int
     */
    private function sortByDate($alpha, $bravo)
    {
        return strcmp($alpha['sentDate'], $bravo['sentDate']);
    }
}
