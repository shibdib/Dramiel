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
class evemails {
    /**
     * @var
     */
    var $config;
    /**
     * @var
     */
    var $discord;
    /**
     * @var
     */
    var $logger;
    /**
     * @var
     */
    var $nextCheck;
    /**
     * @var
     */
    var $toIDs;
    /**
     * @var
     */
    var $toDiscordChannel;

    /**
     * @var
     */
    var $newestMailID;
    /**
     * @var
     */
    var $maxID;
    /**
     * @var
     */
    var $keyCount;
    /**
     * @var
     */
    var $keys;
    /**
     * @var
     */
    var $evemailsConf;

    public $keyID;
    public $vCode;
    public $guild;
    public $characterID;

    /**
     * @param $config
     * @param $discord
     * @param $logger
     */
    function init($config, $discord, $logger)
    {
        $this->config = $config;
        $this->discord = $discord;
        $this->logger = $logger;
		$this->evemailsConf = $config["plugins"]["evemails"];
        $this->maxID = 0;
        $this->guild = $config["bot"]["guild"];
        $this->nextCheck = 0;
		
		foreach ($this->evemailsConf as $selected){
			foreach ( $selected as $key => $value){
				switch ($key) {
					case "fromID":
						break;
					case "channelID":
						break;
					case "keyID":
						$this->keyID = $value;
						$lastCheck = getPermCache("mailLastChecked{$this->keyID}");
						if ($lastCheck == NULL) {
							// Schedule it for right now if first run
							setPermCache("mailLastChecked{$this->keyID}", time() - 5);
						}
						break;
					case "vCode":
						break;
					case "characterID":
						break;
				}
			}
		}
		
        //$lastCheck = getPermCache("mailLastChecked{$this->keyID}");
        //if ($lastCheck == NULL) {
            // Schedule it for right now if first run
        //    setPermCache("mailLastChecked{$this->keyID}", time() - 5);
        //}
    }

    /**
     *
     */
    function tick()
    {
        
		foreach ($this->evemailsConf as $selected){
			foreach ( $selected as $key => $value){
				switch ($key) {
					case "fromID":
						$this->toIDs = $value;
						break;
					case "channelID":
						$this->toDiscordChannel = $value;
						break;
					case "keyID":
						$this->keyID = $value;
						break;
					case "vCode":
						$this->vCode = $value;
						break;
					case "characterID":
						$this->characterID = $value;
						break;
				}
				
			}
			$lastChecked = getPermCache("mailLastChecked{$this->keyID}");
			$this->newestMailID = getPermCache("newestCorpMailID{$this->keyID}");
			$keyID = $this->keyID;
			$vCode = $this->vCode;
			$characterID = $this->characterID;
			$discord = $this->discord;
			if (DEBUG) {$temptoIDs = array(); $temptoIDs = $this->toIDs;}
			if ($lastChecked <= time()) {
				$this->logger->addInfo("Checking API Key {$keyID} for new mail..");
				if (DEBUG) {$this->logger->addInfo("Debug MSG fTick-01-{$keyID}: {$temptoIDs[0]}, {$this->keyID}, {$this->vCode}, {$this->characterID}, {$this->newestMailID}");}
				$this->checkMails($keyID, $vCode, $characterID, $discord);
			}
		}
		
		//$lastChecked = getPermCache("mailLastChecked{$this->keyID}");
        //$keyID = $this->keyID;
        //$vCode = $this->vCode;
        //$characterID = $this->characterID;
        //$discord = $this->discord;

        //if ($lastChecked <= time()) {
        //    $this->logger->addInfo("Checking API Key {$keyID} for new mail..");
        //    $this->checkMails($keyID, $vCode, $characterID, $discord);
        //}

    }

    function checkMails($keyID, $vCode, $characterID, $discord)
    {
		if (DEBUG) {$this->logger->addInfo("Debug MSG fCheckMails-01-{$keyID}: {$temptoIDs[0]}, {$keyID}, {$vCode}, {$characterID}, ");}
		$url = "https://api.eveonline.com/char/MailMessages.xml.aspx?keyID={$keyID}&vCode={$vCode}&characterID={$characterID}";
		if (DEBUG) {$this->logger->addInfo("Debug MSG fCheckMails-02-{$keyID}: {$url}");}
        $data = json_decode(json_encode(simplexml_load_string(downloadData($url), "SimpleXMLElement", LIBXML_NOCDATA)), true);
		if (DEBUG) {$this->logger->addInfo("Debug MSG fCheckMails-03-{$keyID}: {$data}");}
        $data = $data["result"]["rowset"]["row"];
        $xml = makeApiRequest($url);
        $cached = $xml->cachedUntil[0];
        $baseUnix = strtotime($cached);
        $cacheClr = $baseUnix - 13500;
		if (DEBUG) {$this->logger->addInfo("Debug MSG fCheckMails-04-{$keyID}: {$cached}, {$cacheClr}");}
        if ($cacheClr <= time()) {
            //$weirdTime = time() + 1830;
			$weirdTime = time() + 130;
            $cacheTimer = gmdate("Y-m-d H:i:s", $weirdTime);
            setPermCache("mailLastChecked{$keyID}", $weirdTime);
        } else {
            $cacheTimer = gmdate("Y-m-d H:i:s", $cacheClr);
            setPermCache("mailLastChecked{$keyID}", $cacheClr);
        }

        $mails = array();
        if (isset($data["@attributes"])) { $mails[] = $data["@attributes"]; }
        // Sometimes there is only ONE notification, so.. yeah..
        if (count($data) > 1) {
            foreach ($data as $multiMail) {
                $mails[] = $multiMail["@attributes"];
            }
        }

        usort($mails, array($this, "sortByDate"));

        foreach ($mails as $mail) {
            if (in_array($mail["toCorpOrAllianceID"], $this->toIDs) && $mail["messageID"] > $this->newestMailID) {
                $sentBy = $mail["senderName"];
                $title = $mail["title"];
                $sentDate = $mail["sentDate"];
                $url = "https://api.eveonline.com/char/MailBodies.xml.aspx?keyID={$keyID}&vCode={$vCode}&characterID={$characterID}&ids=" . $mail["messageID"];
				$content = strip_tags(str_replace("<br>", "\n", json_decode(json_encode(simplexml_load_string(downloadData($url), "SimpleXMLElement", LIBXML_NOCDATA)))->result->rowset->row));
				if (DEBUG) {$this->logger->addInfo("Debug MSG fCheckMails-05-{$keyID}: {$url}");}
				if (DEBUG) {$this->logger->addInfo("Debug MSG fCheckMails-06-{$keyID}: {$content}");}
				
                // Blank Content Check
                if ($content == "") {
                    return null;
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
                $guild = $discord->guilds->get('id', $this->guild);
                $channel = $guild->channels->get('id', $channelID);
                $channel->sendMessage($msg, false);
				if (DEBUG) {$this->logger->addInfo("Debug MSG fCheckMails-07-{$keyID}: {$channelID}, {$guild}, {$channel}");}
                sleep(1); // Lets sleep for a second, so we don't rage spam
                if (strlen($content) > 1850) {
                    $channel->sendMessage($msgLong, false);
                }

                // Find the maxID so we don't spit this message out ever again
                $this->maxID = max($mail["messageID"], $this->maxID);
                $this->newestMailID = $this->maxID; //$mail["messageID"];
				if (DEBUG) {$this->logger->addInfo("Debug MSG fCheckMails-08-{$keyID}: {$this->newestMailID}, {$this->maxID}");}
                $updateMaxID = true;

                // set the maxID
                if ($updateMaxID) {
                    setPermCache("newestCorpMailID{$this->keyID}", $this->maxID);
                }
            }
        }
        $this->logger->addInfo("Next Mail Check At: {$cacheTimer} EVE Time");
		if (DEBUG) {$this->logger->addInfo("Debug MSG fCheckMails-09-{$keyID}: Done..");}
    }

    /**
     * @param $alpha
     * @param $bravo
     * @return int
     */
    function sortByDate($alpha, $bravo)
    {
        return strcmp($alpha["sentDate"], $bravo["sentDate"]);
    }

    /**
     *
     */
    function onMessage()
    {
    }

    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "",
            "trigger" => array(""),
            "information" => ""
        );
    }
}
