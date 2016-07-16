<?php
/**
 * The MIT License (MIT).
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
use Discord\Discord;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;

/**
 * Class fileReaderJabber.
 */
class fileReader
{
    /*
     * @var
     */
    public $config;
    /*
     * @var
     */
    public $db;
    /*
     * @var
     */
    public $discord;
    /*
     * @var
     */
    public $channelConfig;
    /*
     * @var int
     */
    public $lastCheck = 0;
    /*
     * @var
     */
    public $logger;

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
        $this->channelConfig = $config['plugins']['fileReader']['channelConfig'];
        $this->db = $config['plugins']['fileReader']['db'];
        if (!is_file($this->db)) {
            touch($this->db);
        }
    }

    /**
     * @return array
     */
    public function information()
    {
        return [
            'name'        => '',
            'trigger'     => [],
            'information' => '',
        ];
    }


    public function tick()
    {
        if (filemtime($this->db) >= $this->lastCheck) {
            $data = file($this->db);
            if ($data) {
                $message = '';
                foreach ($data as $row) {
                    $row = str_replace("\n", '', str_replace("\r", '', str_replace('^@', '', $row)));
                    if ($row == '' || $row == ' ') {
                        continue;
                    }

                    $message .= $row.' | ';
                    usleep(300000);
                }

                // Remove |  from the line or whatever else is at the last two characters in the string
                $message = trim(substr($message, 0, -2));

                foreach ($this->channelConfig as $chanName => $chanConfig) {
                    if ($chanConfig['searchString'] == false) { // If no match was found, and searchString is false, just use that
                        $message = $chanConfig['textStringPrepend'].' '.$message.' '.$chanConfig['textStringAppend'];
                        $channelID = $chanConfig['channelID'];
                    } elseif (stristr($message, $chanConfig['searchString'])) {
                        $message = $chanConfig['textStringPrepend'].' '.$message.' '.$chanConfig['textStringAppend'];
                        $channelID = $chanConfig['channelID'];
                    }
                }
                if ($channelID == '' || $channelID == null) {
                    $message = 'skip';
                }
                if ($message != 'skip') {
                    $this->logger->addInfo("Ping sent to channel {$channelID}, Message - {$message}");
                    // Send the pings to the channel
                    $channel = Channel::find($channelID);
                    $channel->sendMessage($message, false);
                }
            }
            $h = fopen($this->db, 'w+');
            fclose($h);
            chmod($this->db, 0777);
            $data = null;
            $h = null;
        }
        clearstatcache();
        $this->lastCheck = time();
    }

    /**
     * @param $msgData
     */
    public function onMessage($msgData)
    {
    }
}
