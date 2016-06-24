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
 * Class siloFull
 */
class siloFull {
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
        $lastChecked = getPermCache("siloLastChecked{$this->keyID}");
        $keyID = $this->keyID;
        $vCode = $this->vCode;
        if ($lastChecked <= time()) {
            $this->logger->info("Checking API Key {$keyID} for full silos");
            $this->checkTowers($keyID, $vCode);
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
        if ($this->towerRace == 1){
            $towerMulti = 0.50;
            $towerFull = 30000;
            $cleanFull = number_format($towerFull);
        }
        if ($this->towerRace == 2){
            $towerMulti = 1;
            $towerFull = 40000;
            $cleanFull = number_format($towerFull);
        }
        foreach ($xml->result->rowset->row as $structures) {
            //Check silos
            if ($structures->attributes()->typeID == 14343) {
                foreach ($structures->rowset->row as $silo) {
                    $moonGoo = $silo->attributes()->typeID;
                    switch ($moonGoo) {
                        case 16634:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16634), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 180000+(180000*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 0.1;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16643:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16643), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 45000+(45000*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 0.4;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16647:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16647), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 22500+(22500*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 0.8;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16641:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16641), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 30000+(30000*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 0.6;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16640:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16640), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 45000+(45000*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 0.4;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16635:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16635), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 180000+(180000*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 0.1;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16648:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16648), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 22500+(22500*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 0.8;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16633:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16633), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 180000+(180000*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 0.1;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16646:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16646), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 22500+(22500*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 0.8;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16651:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16651), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 18000+(18000*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 1;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16650:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16650), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 18000+(18000*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 1;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16644:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16644), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 18000+(18000*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 1;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16652:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16652), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 18000+(18000*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 1;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16639:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16639), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 45000+(45000*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 0.4;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16636:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16636), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 180000+(180000*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 0.1;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16649:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16649), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 18000+(18000*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 1;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16653:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16653), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 18000+(18000*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 1;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16638:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16638), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 45000+(45000*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 0.4;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16637:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16637), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 45000+(45000*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 0.4;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
                        case 16642:
                            $typeName = dbQueryField("SELECT typeName FROM invTypes WHERE typeID = :id", "typeName", array(":id" => 16642), "ccp");
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $towerWarn = 18000+(18000*$towerMulti);
                            if ($silo->attributes()->quantity >= $towerWarn) {
                                $gooAmount = $silo->attributes()->quantity;
                                $gooVolume = 1;
                                $gooCurrent = $gooAmount * $gooVolume;
                                $cleanNumber = number_format($gooCurrent);
                                $msg = "**{$typeName} Silo Nearing Capacity**\n";
                                if ($gooCurrent == $towerFull){
                                    $msg = "**{$typeName} Silo Full**\n";
                                }
                                $msg .= "**System: **{$systemName}\n";
                                $msg .= "**Capacity: **{$cleanNumber}/{$cleanFull}m3\n";
                                $this->logger->info("{$typeName} Silo nearing capacity in {$systemName}");
                                // Send the mails to the channel
                                $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                                $siloCount++;
                                sleep(1);
                            }
                            break;
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

        if ($siloCount > 0){
            $this->discord->api("channel")->messages()->create($this->toDiscordChannel, "Next Silo Check At: {$cacheTimer} EVE Time");
        }
        $this->logger->info("Silo Check Complete Next Check At {$cacheTimer}");
        return null;


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
}