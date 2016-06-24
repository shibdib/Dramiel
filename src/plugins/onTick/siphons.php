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
 * Class siphons
 */
class siphons {
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
    var $toDiscordChannel;
    protected $keyID;
    protected $vCode;
    protected $prefix;

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
        $this->toDiscordChannel = $config["plugins"]["siphons"]["channelID"];
        $this->keyID = $config["plugins"]["siphons"]["keyID"];
        $this->vCode = $config["plugins"]["siphons"]["vCode"];
        $this->prefix = $config["plugins"]["siphons"]["prefix"];
        $lastCheck = getPermCache("siphonLastChecked{$this->keyID}");
        if ($lastCheck == NULL) {
            // Schedule it for right now if first run
            setPermCache("siphonLastChecked{$this->keyID}", time() - 5);
        }
    }

    /**
     *
     */
    function tick()
    {
        $lastChecked = getPermCache("siphonLastChecked{$this->keyID}");
        $keyID = $this->keyID;
        $vCode = $this->vCode;

        if ($lastChecked <= time()) {
            $this->logger->info("Checking API Key {$keyID} for siphons");
            $this->checkTowers($keyID, $vCode);
        }
    }

    function checkTowers($keyID, $vCode)
    {
        $url = "https://api.eveonline.com/corp/AssetList.xml.aspx?keyID={$keyID}&vCode={$vCode}";
        $xml = makeApiRequest($url);
        $siphonCount = 0;
        foreach ($xml->result->rowset->row as $structures) {
            //Check silos
            if ($structures->attributes()->typeID == 14343) {
                foreach ($structures->rowset->row as $silo) {
                    //Avoid reporting empty silos
                    if ($silo->attributes()->quantity != 0) {
                        //Check for a multiple of 50
                        if ($silo->attributes()->quantity % 50 != 0) {
                            $gooType = $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => $silo->attributes()->typeID), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $msg = "{$this->prefix}";
                            $msg .= "**POSSIBLE SIPHON**\n";
                            $msg .= "**System: **{$systemName} has a possible siphon stealing {$gooType} from a silo.\n";
                            // Send the mails to the channel;
                            $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                            $this->logger->info($msg);
                            $siphonCount++;
                            sleep(2); // Lets sleep for a second, so we don't rage spam
                        }
                    }
                }
            }
            if ($structures->attributes()->typeID == 17982) {
                foreach ($structures->rowset->row as $coupling) {
                    //Avoid reporting empty coupling arrays
                    if ($coupling->attributes()->quantity != 0) {
                        //Check for a multiple of 50
                        if ($coupling->attributes()->quantity % 50 != 0) {
                            $gooType = $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => $coupling->attributes()->typeID), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $msg = "{$this->prefix}";
                            $msg .= "**POSSIBLE SIPHON**\n";
                            $msg .= "**System: **{$systemName} has a possible siphon stealing {$gooType} from a coupling array.\n";
                            // Send the mails to the channel;
                            $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                            $this->logger->info($msg);
                            $siphonCount++;
                            sleep(2); // Lets sleep for a second, so we don't rage spam
                        }
                    }
                }
            }
        }
        $cached = $xml->cachedUntil[0];
        $baseUnix = strtotime($cached);
        $cacheClr = $baseUnix - 13500;
        if ($cacheClr <= time()) {
            $weirdTime = time() + 21700;
            $cacheTimer = gmdate("Y-m-d H:i:s", $weirdTime);
            setPermCache("siphonLastChecked{$keyID}", $weirdTime);
        } else {
            $cacheTimer = gmdate("Y-m-d H:i:s", $cacheClr);
            setPermCache("siphonLastChecked{$keyID}", $cacheClr);
        }
        if ($siphonCount > 0){
            $this->discord->api("channel")->messages()->create($this->toDiscordChannel, "Next Siphon Check At: {$cacheTimer} EVE Time");
        }
        $this->logger->info("Siphon Check Complete Next Check At {$cacheTimer}");
        return null;
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
