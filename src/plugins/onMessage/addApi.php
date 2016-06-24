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

class addapi
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
        $this->seatToken = $config["seat"]["token"];
        $this->seatBase = $config["seat"]["url"];
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
            // Bind a few things to vars for the plugins
            $message = $msgData["message"]["message"];
            $channelID = $msgData["message"]["channelID"];
            $data = command($message, $this->information()["trigger"]);
            if (isset($data["trigger"])) {
                $field = explode(" ", $data["messageString"]);
                $post = [
                'key_id' => $field[0],
                'v_code' => $field[1],
                ];

                // Basic check on entry validity. Need to make this more robust.
                if (strlen($field[0]) <> 7) {
                                    return $this->discord->api("channel")->messages()->create($channelID, "**Invalid KeyID**");
                }
                if (strlen($field[1]) <> 64) {
                                    return $this->discord->api("channel")->messages()->create($channelID, "**Invalid vCode**");
                }

                $url = $this->seatBase . 'api/v1/key';

                seatPost($url, $post, $this->seatToken);

                $msg = "API Successfully Submitted.";
                $this->logger->info("API key submitted");
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
            "name" => "addapi",
            "trigger" => array("!addapi"),
            "information" => "To add an API private message this bot in the following format. !addapi KeyID vCode"
        );
    }
    /**
     * @param $msgData
     */
    function onMessageAdmin($msgData)
    {
    }
}
