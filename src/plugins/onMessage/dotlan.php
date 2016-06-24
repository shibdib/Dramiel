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
 * Class dotlan
 */
class dotlan
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
     * @param $config
     * @param $discord
     * @param $logger
     */
    function init($config, $discord, $logger)
    {
        $this->config = $config;
        $this->discord = $discord;
        $this->logger = $logger;
    }

    /**
     *
     */
    function tick()
    {
    }

    /**
     * @param $msgData
     * @return null
     */
    function onMessage($msgData)
    {
        $message = $msgData["message"]["message"];
        $channelID = $msgData["message"]["channelID"];

        $data = command($message, $this->information()["trigger"]);
        if (isset($data["trigger"])) {
            $input = explode(' ', $data["messageString"], 2);
            if ($input[0] == "system") {
                $system = dbQueryField("SELECT solarSystemID FROM mapSolarSystems WHERE (solarSystemName = :id COLLATE NOCASE)", "solarSystemID", array(":id" => $input[1]), "ccp");
                if ($system == NULL) {
                    $this->discord->api("channel")->messages()->create($channelID, "System Not Found.");
                    return null;
                }
                $clean = str_replace(' ', '_', $input[1]);
                $url = "http://evemaps.dotlan.net/system/{$clean}";
                $msg = "DOTLAN map for **{$input[1]}** - {$url}";
                $this->discord->api("channel")->messages()->create($channelID, $msg);
                return null;
            }

            if ($input[0] == "region") {
                $system = dbQueryField("SELECT regionID FROM mapRegions WHERE (regionName = :id COLLATE NOCASE)", "regionID", array(":id" => $input[1]), "ccp");
                if ($system == NULL) {
                    $this->discord->api("channel")->messages()->create($channelID, "Region Not Found.");
                    return null;
                }
                $clean = str_replace(' ', '_', $input[1]);
                $url = "http://evemaps.dotlan.net/map/{$clean}";
                $msg = "DOTLAN map for **{$input[1]}** - {$url}";
                $this->discord->api("channel")->messages()->create($channelID, $msg);
                return null;
            }

            if ($input[0] == "range") {
                $range = explode(' ', $input[1]);
                $system = dbQueryField("SELECT solarSystemID FROM mapSolarSystems WHERE (solarSystemName = :id COLLATE NOCASE)", "solarSystemID", array(":id" => $range[1]), "ccp");
                if ($system == NULL) {
                    $this->discord->api("channel")->messages()->create($channelID, "System Not Found.");
                    return null;
                }
                $cleanSystem = str_replace(' ', '_', $range[1]);
                $cleanShip = ucfirst($range[0]);
                $url = "http://evemaps.dotlan.net/range/{$cleanShip}/{$cleanSystem}";
                $msg = "Jump Range for a {$cleanShip} from **{$range[1]}** - {$url}";
                $this->discord->api("channel")->messages()->create($channelID, $msg);
                return null;
            }

            if ($input[0] == "plan") {
                $plan = explode(' ', $input[1]);
                $system1 = dbQueryField("SELECT solarSystemID FROM mapSolarSystems WHERE (solarSystemName = :id COLLATE NOCASE)", "solarSystemID", array(":id" => $plan[1]), "ccp");
                if ($system1 == NULL) {
                    $this->discord->api("channel")->messages()->create($channelID, "System Not Found.");
                    return null;
                }
                $system2 = dbQueryField("SELECT solarSystemID FROM mapSolarSystems WHERE (solarSystemName = :id COLLATE NOCASE)", "solarSystemID", array(":id" => $plan[2]), "ccp");
                if ($system2 == NULL) {
                    $this->discord->api("channel")->messages()->create($channelID, "System Not Found.");
                    return null;
                }
                $cleanSystem1 = str_replace(' ', '_', $plan[1]);
                $cleanSystem2 = str_replace(' ', '_', $plan[2]);
                $cleanShip = ucfirst($plan[0]);
                $url = "http://evemaps.dotlan.net/jump/{$cleanShip}55,S/{$cleanSystem1}:{$cleanSystem2}";
                $msg = "Jump Plan for a {$cleanShip} from **{$plan[1]}** to **{$plan[2]}** - {$url}";
                $this->discord->api("channel")->messages()->create($channelID, $msg);
                return null;
            }
        }
    }

    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "dotlan",
            "trigger" => array("!dotlan"),
            "information" => "Get quick links to Dotlan.\n For System Info Use **!dotlan system** *system_name* \n For Region Info Use **!dotlan region** *region_name* \n To Get Jump Range Details Use **!dotlan range** *ship,jc_lvl system_name* \n To Figure Out a Jump Route Use **!dotlan plan** *ship,jc_lvl start_system end_system* \n\n *!!jc_lvl is Jump Cal. in number form 1-5!!*"
        );
    }
}