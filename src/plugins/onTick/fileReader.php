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
 * Class fileReaderJabber
 */
class fileReader
{
    public $config;
    public $discord;
    public $logger;
    public $guild;
    private $db;
    private $channelConfig;
    private $lastCheck = 0;

    /**
     * @param $config
     * @param $primary
     * @param $discord
     * @param $logger
     */
    public function init($config, $primary, $discord, $logger)
    {
        $this->config = $config;
        $this->discord = $discord;
        $this->logger = $logger;
        $this->guild = $config['bot']['guild'];
        $this->channelConfig = $config['plugins']['fileReader']['channelConfig'];
        $this->db = $config['plugins']['fileReader']['db'];
        if (!is_file($this->db)) {
            touch($this->db);
        }
    }

    /**
     *
     */
    public function tick()
    {
        if (filemtime($this->db) >= $this->lastCheck) {
            $data = file($this->db);
            if ($data) {
                $ping = '';
                foreach ($data as $row) {
                    $row = str_replace('^@', '', $row);
                    if ($row == '' || $row == ' ') {
                        continue;
                    }

                    $ping .= $row . '  ';
                }

                // Remove |  from the line or whatever else is at the last two characters in the string
                $message = trim(substr($ping, 0, -2));
                foreach ($this->channelConfig as $chanName => $chanConfig) {
                    if ($chanConfig['searchString'] == false) { // If no match was found, and searchString is false, just use that
                        $message = $chanConfig['textStringPrepend'] . " \n " . $message . '  ' . $chanConfig['textStringAppend'];
                        $channelID = $chanConfig['channelID'];
                    } elseif (stristr($message, $chanConfig['searchString'])) {
                        $message = $chanConfig['textStringPrepend'] . " \n " . $message . '  ' . $chanConfig['textStringAppend'];
                        $channelID = $chanConfig['channelID'];
                    }
                }

                if (!isset($channelID)) { // Make sure it's always set.
                    $channelID = null;
                }
                $begin = mb_substr($message, 0, 15);
                if (strstr($begin, '#')) {
                    $message = 'skip';
                }
                if ($channelID == '' || $channelID == null) {
                    $message = 'skip';
                }
                if ($message != 'skip') {
                    $this->logger->addInfo("fileReader: Ping sent to front of queue for {$channelID}");
                    priorityQueueMessage($message, $channelID, $this->guild);
                }
            }
            $h = fopen($this->db, 'wb+');
            fclose($h);
            chmod($this->db, 0777);
        }
        clearstatcache();
        $this->lastCheck = time();
    }
}
