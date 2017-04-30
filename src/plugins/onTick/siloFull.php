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
    public $config;
    public $discord;
    public $logger;
    protected $keyID;
    protected $vCode;
    protected $prefix;
    private $toDiscordChannel;
    private $guild;
    private $towerRace;

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
        $this->guild = $config['bot']['guild'];
        $this->toDiscordChannel = $config['plugins']['siloFull']['channelID'];
        $this->keyID = $config['plugins']['siloFull']['keyID'];
        $this->vCode = $config['plugins']['siloFull']['vCode'];
        $this->towerRace = $config['plugins']['siloFull']['towerRace'];
        $lastCheck = getPermCache("siloLastChecked{$this->keyID}");
        if ($lastCheck === NULL) {
            // Schedule it for right now if first run
            setPermCache("siloLastChecked{$this->keyID}", time() - 5);
        }
    }

    /**
     *
     */
    public function tick()
    {
        // What was the servers last reported state
        $lastStatus = getPermCache('serverState');
        if ($lastStatus === 'online') {
            $lastChecked = getPermCache("siloLastChecked{$this->keyID}");
            $keyID = $this->keyID;
            $vCode = $this->vCode;
            if ($lastChecked <= time()) {
                $this->logger->addInfo("siloFull: Checking API Key {$keyID} for full silos");
                $this->checkTowers($keyID, $vCode);
            }
        }
    }

    private function checkTowers($keyID, $vCode)
    {

        $url = "https://api.eveonline.com/corp/AssetList.xml.aspx?keyID={$keyID}&vCode={$vCode}";
        $xml = makeApiRequest($url);
        $siloCount = 0;
        foreach ($xml->result->rowset->row as $structures) {
            //Check silos
            if ($structures->attributes()->typeID == 14343) {
                if (isset($structures->rowset->row)) {
                    $locationID = $structures->attributes()->locationID;
                    $towerRace = $this->getTowerRace($keyID, $vCode, $locationID);
                    $towerMulti = 0;
                    $towerFull = 20000;
                    $cleanFull = number_format($towerFull);
                    if ($towerRace === '1') {
                        $towerMulti = 0.50;
                        $towerFull = 30000;
                        $cleanFull = number_format($towerFull);
                    }
                    if ($towerRace === '2') {
                        $towerMulti = 1;
                        $towerFull = 40000;
                        $cleanFull = number_format($towerFull);
                    }
                    if ($towerRace === '3') {
                        $towerMulti = 0;
                        $towerFull = 20000;
                        $cleanFull = number_format($towerFull);
                    }
                    foreach ($structures->rowset->row as $silo) {
                        $moonGoo = $silo->attributes()->typeID;
                        switch ($moonGoo) {
                            case 16634:
                                $typeName = getTypeName(16634);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 180000 + (180000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 0.1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16643:
                                $typeName = getTypeName(16643);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 45000 + (45000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 0.4;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16647:
                                $typeName = getTypeName(16647);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 22500 + (22500 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 0.8;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16641:
                                $typeName = getTypeName(16641);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 30000 + (30000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 0.6;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16640:
                                $typeName = getTypeName(16640);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 45000 + (45000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 0.4;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16635:
                                $typeName = getTypeName(16635);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 180000 + (180000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 0.1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16648:
                                $typeName = getTypeName(16648);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 22500 + (22500 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 0.8;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16633:
                                $typeName = getTypeName(16633);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 180000 + (180000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 0.1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16646:
                                $typeName = getTypeName(16646);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 22500 + (22500 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 0.8;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16651:
                                $typeName = getTypeName(16651);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 18000 + (18000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16650:
                                $typeName = getTypeName(16650);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 18000 + (18000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16644:
                                $typeName = getTypeName(16644);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 18000 + (18000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16652:
                                $typeName = getTypeName(16652);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 18000 + (18000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16639:
                                $typeName = getTypeName(16639);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 45000 + (45000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 0.4;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16636:
                                $typeName = getTypeName(16636);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 180000 + (180000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 0.1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16649:
                                $typeName = getTypeName(16649);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 18000 + (18000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16653:
                                $typeName = getTypeName(16653);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 18000 + (18000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16638:
                                $typeName = getTypeName(16638);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 45000 + (45000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 0.4;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16637:
                                $typeName = getTypeName(16637);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 45000 + (45000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 0.4;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
                                    $siloCount++;
                                }
                                break;
                            case 16642:
                                $typeName = getTypeName(16642);
                                $systemName = getSystemName($structures->attributes()->locationID);
                                $towerWarn = 18000 + (18000 * $towerMulti);
                                if ($silo->attributes()->quantity >= $towerWarn) {
                                    $siloID = $structures->attributes()->itemID;
                                    $lastAmount = getPermCache("silo{$siloID}Amount");
                                    $gooAmount = $silo->attributes()->quantity;
                                    $gooDifference = $gooAmount - $lastAmount;
                                    //Check if silo has been checked before, and if it's an input silo ignore
                                    if (!isset($lastAmount) || $gooDifference < 0) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    $gooVolume = 1;
                                    $gooCurrent = $gooAmount * $gooVolume;
                                    //double check tower race
                                    if ((int)$towerFull === 20000 && (int)$gooCurrent > 20000) {
                                        $towerFull = 30000;
                                    }
                                    if ((int)$towerFull === 20000 || 30000 && (int)$gooCurrent > 30000) {
                                        $towerFull = 40000;
                                    }
                                    $cleanNumber = number_format($gooCurrent);
                                    $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                    if ($gooCurrent === $towerFull && $lastAmount === $gooCurrent) {
                                        $msg = "**{$typeName} Silo Full**\n";
                                    } elseif ($gooCurrent === $towerFull && $lastAmount !== $gooCurrent) {
                                        setPermCache("silo{$siloID}Amount", $gooAmount);
                                        continue 2;
                                    }
                                    setPermCache("silo{$siloID}Amount", $gooAmount);
                                    $msg .= "**System: **{$systemName}\n";
                                    $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                    $this->logger->addInfo("siloFull: {$typeName} Silo nearing capacity in {$systemName}");
                                    // Send the msg to the channel;
                                    $channelID = $this->toDiscordChannel;
                                    queueMessage($msg, $channelID, $this->guild);
                                    $this->logger->addInfo('siloFull: Silo Alert queued');
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
            $cacheTimer = gmdate('Y-m-d H:i:s', $weirdTime);
            setPermCache("siloLastChecked{$keyID}", $weirdTime);
        } else {
            $cacheTimer = gmdate('Y-m-d H:i:s', $cacheClr);
            setPermCache("siloLastChecked{$keyID}", $cacheClr);
        }
        $this->logger->addInfo("siloFull: Silo Check Complete Next Check At {$cacheTimer}");
        return null;
    }

    private function getTowerRace($keyID, $vCode, $systemID)
    {
        $url = "https://api.eveonline.com/corp/StarbaseList.xml.aspx?keyID={$keyID}&vCode={$vCode}";
        $xml = makeApiRequest($url);
        foreach ($xml->result->rowset->row as $tower) {
            $typeID = (int)$tower->attributes()->typeID;
            $locationID = (int)$tower->attributes()->locationID;
            if ($locationID === (int)$systemID) {
                if ($typeID === 12235 || $typeID === 20059 || $typeID === 20060 || $typeID === 27532 || $typeID === 27591 || $typeID === 27594 || $typeID === 27530 || $typeID === 27589 || $typeID === 27592 || $typeID === 27780 || $typeID === 27782 || $typeID === 27784 || $typeID === 27786 || $typeID === 27788 || $typeID === 27790) {
                    return '1';
                }
                if ($typeID === 12236 || $typeID === 20063 || $typeID === 20064 || $typeID === 27538 || $typeID === 27603 || $typeID === 27606 || $typeID === 27536 || $typeID === 27601 || $typeID === 27604) {
                    return '2';
                }
            }
            continue;
        }
        return '3';
    }
}