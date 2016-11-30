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
 * Class siloFull
 * @property  towerRace
 */
class siloFull
{
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
    public $guild;
    public $towerRace;
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
        $this->guild = $config["bot"]["guild"];
        $this->toDiscordChannel = $config["plugins"]["siloFull"]["channelID"];
        $this->keyID = $config["plugins"]["siloFull"]["keyID"];
        $this->vCode = $config["plugins"]["siloFull"]["vCode"];
        $this->towerRace = $config["plugins"]["siloFull"]["towerRace"];
        $lastCheck = getPermCache("siloLastChecked{$this->keyID}");
        if ($lastCheck == NULL) {
            // Schedule it for right now if first run
            setPermCache("siloLastChecked{$this->keyID}", time() - 5);
        }
    }

    /**
     *
     */
    function tick()
    {
        // What was the servers last reported state
        $lastStatus = getPermCache("serverState");
        if ($lastStatus == "online") {
            $lastChecked = getPermCache("siloLastChecked{$this->keyID}");
            $keyID = $this->keyID;
            $vCode = $this->vCode;
            if ($lastChecked <= time()) {
                $this->logger->addInfo("siloFull: Checking API Key {$keyID} for full silos");
                $this->checkTowers($keyID, $vCode);
            }
        }
    }

    function checkTowers($keyID, $vCode)
    {

        $url = "https://api.eveonline.com/corp/AssetList.xml.aspx?keyID={$keyID}&vCode={$vCode}";
        $xml = makeApiRequest($url);
        $siloCount = 0;
        $towerMulti = 0;
        $towerFull = 20000;
        $cleanFull = number_format($towerFull);
        if ($this->towerRace == 1) {
            $towerMulti = 0.50;
            $towerFull = 30000;
            $cleanFull = number_format($towerFull);
        }
        if ($this->towerRace == 2) {
            $towerMulti = 1;
            $towerFull = 40000;
            $cleanFull = number_format($towerFull);
        }
        foreach ($xml->result->rowset->row as $structures) {
            //Check silos
            if ($structures->attributes()->typeID == 14343) {
                if (isset($structures->rowset->row)) {
                    foreach ($structures->rowset->row as $silo) {
                        $moonGoo = $silo->attributes()->typeID;
                        switch ($moonGoo) {
                            case 16634:
                                $typeName = apiTypeName(16634);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 180000 + (180000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 0.1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16643:
                                $typeName = apiTypeName(16643);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 45000 + (45000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 0.4;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16647:
                                $typeName = apiTypeName(16647);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 22500 + (22500 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 0.8;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16641:
                                $typeName = apiTypeName(16641);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 30000 + (30000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 0.6;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16640:
                                $typeName = apiTypeName(16640);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 45000 + (45000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 0.4;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16635:
                                $typeName = apiTypeName(16635);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 180000 + (180000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 0.1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16648:
                                $typeName = apiTypeName(16648);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 22500 + (22500 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 0.8;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16633:
                                $typeName = apiTypeName(16633);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 180000 + (180000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 0.1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16646:
                                $typeName = apiTypeName(16646);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 22500 + (22500 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 0.8;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16651:
                                $typeName = apiTypeName(16651);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 18000 + (18000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16650:
                                $typeName = apiTypeName(16650);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 18000 + (18000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16644:
                                $typeName = apiTypeName(16644);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 18000 + (18000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16652:
                                $typeName = apiTypeName(16652);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 18000 + (18000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16639:
                                $typeName = apiTypeName(16639);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 45000 + (45000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 0.4;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16636:
                                $typeName = apiTypeName(16636);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 180000 + (180000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 0.1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16649:
                                $typeName = apiTypeName(16649);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 18000 + (18000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16653:
                                $typeName = apiTypeName(16653);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 18000 + (18000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16638:
                                $typeName = apiTypeName(16638);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 45000 + (45000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 0.4;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16637:
                                $typeName = apiTypeName(16637);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 45000 + (45000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 0.4;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
                            case 16642:
                                $typeName = apiTypeName(16642);
                                $systemName = apiCharacterName($structures->attributes()->locationID);
                                $towerWarn = 18000 + (18000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before, and if it's an input silo ignore
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    $gooVolume = 1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent == $towerFull && $lastAmount == $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent == $towerFull && $lastAmount != $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo("siloFull: Silo Alert queued");
                                    $siloCount++;
                                }
                                break;
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
            setPermCache("siloLastChecked{$keyID}", $weirdTime);
        } else {
            $cacheTimer = gmdate("Y-m-d H:i:s", $cacheClr);
            setPermCache("siloLastChecked{$keyID}", $cacheClr);
        }
        $this->logger->addInfo("siloFull: Silo Check Complete Next Check At {$cacheTimer}");
        return null;

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
}