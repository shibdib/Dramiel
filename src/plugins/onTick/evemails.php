<?php
/**
 * The MIT License (MIT).
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
use Discord\Discord;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;

/**
 * Class corporationmails.
 *
 * @property  keyID
 * @property  vCode
 * @property  characterID
 */
class evemails
{
    /*
     * @var
     */
    public $config;
    /*
     * @var
     */
    public $discord;
    /*
     * @var
     */
    public $logger;
    /*
     * @var
     */
    public $nextCheck;
    /*
     * @var
     */
    public $toIDs;
    /*
     * @var
     */
    public $toDiscordChannel;

    /*
     * @var
     */
    public $newestMailID;
    /*
     * @var
     */
    public $maxID;
    /*
     * @var
     */
    public $keyCount;
    /*
     * @var
     */
    public $keys;

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
        $this->keyID = $config['eve']['apiKeys']['user1']['keyID'];
        $this->vCode = $config['eve']['apiKeys']['user1']['vCode'];
        $this->characterID = $config['eve']['apiKeys']['user1']['characterID'];
        $this->nextCheck = 0;
        $lastCheck = getPermCache("mailLastChecked{$this->keyID}");
        if ($lastCheck == null) {
            // Schedule it for right now if first run
            setPermCache("mailLastChecked{$this->keyID}", time() - 5);
        }
    }


    public function tick()
    {
        $lastChecked = getPermCache("mailLastChecked{$this->keyID}");
        $keyID = $this->keyID;
        $vCode = $this->vCode;
        $characterID = $this->characterID;

        if ($lastChecked <= time()) {
            $this->logger->addInfo("Checking API Key {$keyID} for new mail..");
            $this->checkMails($keyID, $vCode, $characterID);
        }
    }

    public function checkMails($keyID, $vCode, $characterID)
    {
        $updateMaxID = false;
        $url = "https://api.eveonline.com/char/MailMessages.xml.aspx?keyID={$keyID}&vCode={$vCode}&characterID={$characterID}";
        $data = json_decode(json_encode(simplexml_load_string(downloadData($url), 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        $data = $data['result']['rowset']['row'];
        $xml = makeApiRequest($url);
        $cached = $xml->cachedUntil[0];
        $baseUnix = strtotime($cached);
        $cacheClr = $baseUnix - 13500;
        if ($cacheClr <= time()) {
            $weirdTime = time() + 1830;
            $cacheTimer = gmdate('Y-m-d H:i:s', $weirdTime);
            setPermCache("mailLastChecked{$keyID}", $weirdTime);
        } else {
            $cacheTimer = gmdate('Y-m-d H:i:s', $cacheClr);
            setPermCache("mailLastChecked{$keyID}", $cacheClr);
        }

        $mails = [];
        if (isset($data['@attributes'])) {
            $mails[] = $data['@attributes'];
        }
        // Sometimes there is only ONE notification, so.. yeah..
        if (count($data) > 1) {
            foreach ($data as $multiMail) {
                $mails[] = $multiMail['@attributes'];
            }
        }

        usort($mails, [$this, 'sortByDate']);

        foreach ($mails as $mail) {
            if (in_array($mail['toCorpOrAllianceID'], $this->toIDs) && $mail['messageID'] > $this->newestMailID) {
                $sentBy = $mail['senderName'];
                $title = $mail['title'];
                $sentDate = $mail['sentDate'];
                $url = "https://api.eveonline.com/char/MailBodies.xml.aspx?keyID={$keyID}&vCode={$vCode}&characterID={$characterID}&ids=".$mail['messageID'];
                $content = strip_tags(str_replace('<br>', "\n", json_decode(json_encode(simplexml_load_string(downloadData($url), 'SimpleXMLElement', LIBXML_NOCDATA)))->result->rowset->row));

                // Blank Content Check
                if ($content == '') {
                    return;
                }

                $messageSplit = str_split($content, 1850);

                // Stitch the mail together
                $msg = "**Mail By: **{$sentBy}\n";
                $msg .= "**Sent Date: **{$sentDate}\n";
                $msg .= "**Title: ** {$title}\n";
                $msg .= "**Content: **\n";
                $msg .= htmlspecialchars_decode(trim($messageSplit[0]));
                $msgLong = htmlspecialchars_decode(trim($messageSplit[1]));

                // Send the mails to the channel
                $channelID = $this->toDiscordChannel;
                $channel = Channel::find($channelID);
                $channel->sendMessage($msg, false);
                sleep(1); // Lets sleep for a second, so we don't rage spam
                if (strlen($content) > 1850) {
                    $channel->sendMessage($msgLong, false);
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
        $this->logger->addInfo("Next Mail Check At: {$cacheTimer} EVE Time");
    }

    /**
     * @param $alpha
     * @param $bravo
     *
     * @return int
     */
    public function sortByDate($alpha, $bravo)
    {
        return strcmp($alpha['sentDate'], $bravo['sentDate']);
    }


    public function onMessage()
    {
    }

    /**
     * @return array
     */
    public function information()
    {
        return [
            'name'        => '',
            'trigger'     => [''],
            'information' => '',
        ];
    }
}
