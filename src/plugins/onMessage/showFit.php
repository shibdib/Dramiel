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
 * Class showFit
 */
class showFit
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
        $this->fitChannel = $config["plugins"]["saveFits"]["channel"];
    }

    /**
     *
     */
    function tick()
    {
    }

    /**
     * @param $msgData
     */
    function onMessage($msgData)
    {
        $message = $msgData["message"]["message"];
        $channelID = $msgData["message"]["channelID"];

        $data = command($message, $this->information()["trigger"]);
        if (isset($data["trigger"])) {
            $fitChoice = stristr($data["messageString"], "@") ? str_replace("<@", "", str_replace(">", "", $data["messageString"])) : $data["messageString"];
            if ($fitChoice == "list"){
                $list = dbQuery("SELECT fit FROM shipFits", array());
                if ($list) {
                    $msg = "Currently Saved Fits: ";
                    foreach ($list as $fit) {
                        $msg .= "**" . ucwords($fit["fit"]) . "**, ";
                    }
                    $msg .= " ";
                    $this->discord->api("channel")->messages()->create($channelID, $msg);
                    return null;
                }
            }

            $fit = dbQueryRow("SELECT * FROM shipFits WHERE (fit = :fit COLLATE NOCASE)", array(":fit" => $fitChoice));

            if ($fit) {
                $message = "``` Fit Submitted By: {$fit["submitter"]}```\n{$fit["fitLink"]}";
                $this->logger->info("Sending fit to {$channelID}");
                $this->discord->api("channel")->messages()->create($channelID, $message);
            } else {
                $this->discord->api("channel")->messages()->create($channelID, "**Error:** no fit found. Try **!fit list** for a list of saved fits.");
            }
        }
    }

    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "fit",
            "trigger" => array("!fit"),
            "information" => "Show a saved fitting. Use **!fit list** to see a list of currently saved fittings and then **!fit <fit_name>** for details."
        );
    }
}