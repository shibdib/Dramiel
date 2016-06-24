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
 * Class item
 */
class item
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
     */
    function onMessage($msgData)
    {
        $message = $msgData["message"]["message"];
        $channelName = $msgData["channel"]["name"];
        $guildName = $msgData["guild"]["name"];
        $channelID = $msgData["message"]["channelID"];

        $data = command($message, $this->information()["trigger"]);
        if (isset($data["trigger"])) {
            $item = $data["messageString"];

            if (is_numeric($item)) {
                            $data = dbQueryRow("SELECT * FROM invTypes WHERE typeID = :item", array(":item" => $item), "ccp");
            } else {
                            $data = dbQueryRow("SELECT * FROM invTypes WHERE typeName = :item COLLATE NOCASE", array(":item" => $item), "ccp");
            }

            if ($data) {
                $msg = "```";
                foreach ($data as $key => $value) {
                                    $msg .= $key . ": " . $value . "\n";
                }
                $msg .= "```";
                $this->logger->info("Sending item information info to {$channelName} on {$guildName}");
                $this->discord->api("channel")->messages()->create($channelID, $msg);
            }
        }
    }

    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "item",
            "trigger" => array("!item"),
            "information" => "Shows information on an item by name or id. To use simply type !item <Item_Name> or !item <ItemID>"
        );
    }
}
