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

class saveFit
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
    public $seatBase;

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
     * @return null
     */
    function onMessage($msgData)
    {
        // Bind a few things to vars for the plugins
        $message = $msgData["message"]["message"];
        $channelID = $msgData["message"]["channelID"];
        $userName = $msgData["message"]["from"];
        $data = command($message, $this->information()["trigger"]);
        if (isset($data["trigger"])) {
            if ($channelID != $this->fitChannel) {
                $this->discord->api("channel")->messages()->create($channelID, "Not allowed to add fits from this channel.");
                Return Null;
            }
            if (substr_count($data["messageString"], " ") > 2) {
                $this->discord->api("channel")->messages()->create($channelID, "Submission format incorrect. Should be !savefit <fit_name> <https://o.smium.org/ link>");
                Return Null;
            }
            $field = explode(" ", $data["messageString"], 2);
            $post = [
                'fitName' => $field[0],
                'fit' => $field[1],
            ];

            $fitName = str_replace("_", " ", $field[0]);
            $cleanApo = str_replace("'", "''", $field[1]);

            $fit = addslashes($cleanApo);
            dbExecute("INSERT INTO shipFits (submitter,fit,fitLink) VALUES ('$userName', '$fitName', '$fit')", array());

            $msg = $fitName . " Successfully Submitted.";
            $this->logger->info("Fit - " . $fitName . " submitted by " . $userName);
            $this->discord->api("channel")->messages()->create($channelID, $msg);

        }
        return null;
    }
    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "savefit",
            "trigger" => array("!savefit"),
            "information" => "Use **!savefit <fit_name> <https://o.smium.org link to fit>** to save a fit to be called using the !fit command. Please include underscores in place of spaces for the fit name. They will be automatically removed once the fit is submitted."
        );
    }
    /**
     * @param $msgData
     */
    function onMessageAdmin($msgData)
    {
    }
}
