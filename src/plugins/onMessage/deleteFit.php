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
 * Class deleteFit
 */
class deleteFit
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
     * @return null
     */
    function onMessage($msgData)
    {
        $message = $msgData["message"]["message"];
        $channelID = $msgData["message"]["channelID"];

        $data = command($message, $this->information()["trigger"]);
        if (isset($data["trigger"])) {
            $fitChoice = stristr($data["messageString"], "@") ? str_replace("<@", "", str_replace(">", "", $data["messageString"])) : $data["messageString"];

            if ($channelID != $this->fitChannel) {
                $this->discord->api("channel")->messages()->create($channelID, "Not allowed to delete fits from this channel.");
                Return Null;
            }

            dbExecute("DELETE FROM shipFits WHERE fit='$fitChoice'", array());

            $message = "``` {$fitChoice} Deleted from saved ship fittings. ```";
            $this->logger->info("Fit Deleted - {$fitChoice}");
            $this->discord->api("channel")->messages()->create($channelID, $message);
        }
    }

    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "deletefit",
            "trigger" => array("!deletefit"),
            "information" => "Delete a saved fit. **!deletefit <fit name>**"
        );
    }
}