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

class wolframAlpha
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
    var $wolf;

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
        require_once(__DIR__ . "/../../library/wolframAlpha/WolframAlphaEngine.php");
        $this->wolf = new WolframAlphaEngine($config["plugins"]["wolframAlpha"]["appID"]);
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
            $messageString = $data["messageString"];

            $response = $this->wolf->getResults($messageString);

            // There was an error
            if ($response->isError()) {
                            var_dump($response->error);
            }

            $guess = $response->getPods();
            if (isset($guess[1])) {
                $guess = $guess[1]->getSubpods();
                $text = $guess[0]->plaintext;
                $image = $guess[0]->image->attributes["src"];

                if (stristr($text, "\n")) {
                                    $text = str_replace("\n", " | ", $text);
                }

                if (!empty($text)) {
                                    $this->discord->api("channel")->messages()->create($channelID, $text);
                }
                if (!empty($image)) {
                                    $this->discord->api("channel")->messages()->create($channelID, $image);
                }
            }
        }
    }

    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "wolf",
            "trigger" => array("!wolf"),
            "information" => "Ask various questions and get interesting answers. Example being !wolf how many world series has the yankees won?"
        );
    }

        /**
         * @param $msgData
         */
        function onMessageAdmin($msgData)
        {
        }

}
