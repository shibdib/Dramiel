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
 * Class siphons
 */
class siphons
{
    public $guild;
    /**
     * @var
     */
    private $config;
    /**
     * @var
     */
    private $discord;
    /**
     * @var
     */
    private $logger;
    /**
     * @var
     */
    private $groupConfig;

    /**
     * @param $config
     * @param $primary
     * @param $discord
     * @param $logger
     */
    public function init($config, $primary, $discord, $logger)
    {
        $this->config = $config;
        $this->discord = $discord;
        $this->logger = $logger;
        $this->guild = $config['bot']['guild'];
        $this->groupConfig = $config['plugins']['siphons']['groupConfig'];
    }

    /**
     *
     */
    public function tick()
    {
        // If config is outdated
        if (null === $this->groupConfig) {
            $this->logger->addInfo('Siphons: Update your config file to the latest version.');
            $msg = '**Siphon Failure:** Please update the bots config to the latest version.';
            queueMessage($msg, $this->config['notifications']['channelID'], $this->guild);
        }

        // What was the servers last reported state
        $lastStatus = getPermCache('serverState');
        if ($lastStatus === 'online') {
            foreach ($this->groupConfig as $siphonCorp) {
                //If group channel is set to 0 skip
                if ($siphonCorp['channelID'] === 0) {
                    continue;
                }
                $lastChecked = getPermCache("siphonLastChecked{$siphonCorp['keyID']}");
                if ($lastChecked === NULL) {
                    // Schedule it for right now if first run
                    $lastChecked = 1;
                }

                if ($lastChecked <= time()) {
                    if (strlen($siphonCorp['keyID']) < 5) {
                        $weirdTime = time() + 21700;
                        setPermCache("siphonLastChecked{$siphonCorp['keyID']}", $weirdTime);
                        continue;
                    }
                    $this->logger->addInfo("Siphons: Checking keyID - {$siphonCorp['keyID']} for siphons");
                    $this->checkTowers();
                }
            }
        }
    }

    private function checkTowers()
    {
        foreach ($this->groupConfig as $siphonCorp) {
            //If group channel is set to 0 skip
            if ($siphonCorp['channelID'] === 0) {
                continue;
            }
            $url = "https://api.eveonline.com/corp/AssetList.xml.aspx?keyID={$siphonCorp['keyID']}&vCode={$siphonCorp['vCode']}";
            $xml = makeApiRequest($url);
            $rawGoo = array(16634, 16643, 16647, 16641, 16640, 16635, 16648, 16633, 16646, 16651, 16650, 16644, 16652, 16639, 16636, 16649, 16653, 16638, 16637, 16642);
            foreach (@$xml->result->rowset->row as $structures) {
                //Check silos
                if ((int) @$structures->attributes()->typeID === 14343) {
                    if (isset($structures->rowset->row)) {
                        foreach (@$structures->rowset->row as $silo) {
                            //Avoid reporting empty silos
                            if ((int) @$silo->attributes()->quantity !== 0 && in_array(@$silo->attributes()->typeID, $rawGoo, false)) {
                                $siloID = $structures->attributes()->itemID;
                                $lastAmount = getPermCache("silo{$siloID}Amount");
                                $gooAmount = $silo->attributes()->quantity;
                                $gooDifference = (int) $gooAmount - (int) $lastAmount;
                                //Check if silo has been checked before
                                if (null === $lastAmount || $gooDifference < 0) {
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    continue;
                                }
                                //Check for a multiple of 50 in the difference
                                if ($gooDifference % 50 !== 0) {
                                    $gooType = getTypeName($silo->attributes()->typeID);
                                    $systemName = getSystemName($structures->attributes()->locationID);
                                    $msg = "{$siphonCorp['prefix']}";
                                    $msg .= "**POSSIBLE SIPHON**\n";
                                    $msg .= "**System: **{$systemName} has a possible siphon stealing {$gooType} from a silo.\n";
                                    // Queue the message
                                    priorityQueueMessage($msg, $siphonCorp['channelID'], $this->guild);
                                    $this->logger->addInfo("Siphons: {$msg}");
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                } else {
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                }
                            }
                        }
                    }
                }
                if ((int) @$structures->attributes()->typeID === 17982) {
                    if (isset($structures->rowset->row)) {
                        foreach (@$structures->rowset->row as $coupling) {
                            //Avoid reporting empty coupling arrays
                            if ((int) @$coupling->attributes()->quantity !== 0 && in_array(@$coupling->attributes()->typeID, $rawGoo, false)) {
                                $couplingID = $structures->attributes()->itemID;
                                $lastAmount = getPermCache("couplingArray{$couplingID}Amount");
                                $gooAmount = $coupling->attributes()->quantity;
                                $gooDifference = (int) $gooAmount - (int) $lastAmount;
                                //Check if silo has been checked before
                                if (null === $lastAmount || $gooDifference < 0) {
                                    setPermCache("couplingArray{$couplingID}Amount", $gooAmount);
                                    continue;
                                }
                                //Check for a multiple of 50 in the difference
                                if ($gooDifference % 50 !== 0) {
                                    $gooType = getTypeName($coupling->attributes()->typeID);
                                    $systemName = getSystemName($structures->attributes()->locationID);
                                    $msg = "{$siphonCorp['prefix']}";
                                    $msg .= "**POSSIBLE SIPHON**\n";
                                    $msg .= "**System: **{$systemName} has a possible siphon stealing {$gooType} from a coupling array.\n";
                                    // Queue the message
                                    priorityQueueMessage($msg, $siphonCorp['channelID'], $this->guild);
                                    $this->logger->addInfo("Siphons: {$msg}");
                                    setPermCache("couplingArray{$couplingID}Amount", $gooAmount);
                                } else {
                                    setPermCache("couplingArray{$couplingID}Amount", $gooAmount);
                                }
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
                $cacheTimer = gmdate('Y-m-d H:i:s', $weirdTime);
                setPermCache("siphonLastChecked{$siphonCorp['keyID']}", $weirdTime);
            } else {
                $cacheTimer = gmdate('Y-m-d H:i:s', $cacheClr);
                setPermCache("siphonLastChecked{$siphonCorp['keyID']}", $cacheClr);
            }
            $this->logger->addInfo("Siphons: Siphon Check Complete Next Check At {$cacheTimer}");
            return null;
        }
    }
}
