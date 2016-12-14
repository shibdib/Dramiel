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
 * @property  message
 */
use discord\discord;

/**
 * Class ops
 * @property  userID
 * @property  apiKey
 * @property  groupID
 * @property  excludeChannel
 */
class fleetUpOps
{
    public $config;
    public $discord;
    public $logger;
    protected $keyID;
    protected $vCode;
    protected $prefix;
    private $userID;
    private $groupID;
    private $apiKey;
    private $guild;
    private $excludeChannel;
    private $message;

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
        $this->userID = $config['plugins']['fleetUpOps']['userID'];
        $this->groupID = $config['plugins']['fleetUpOps']['groupID'];
        $this->apiKey = $config['plugins']['fleetUpOps']['apiKey'];
        $this->guild = $config['bot']['guild'];
        $this->excludeChannel = $this->config['bot']['restrictedChannels'];
    }

    /**
     * @param $msgData
     * @param $message
     * @return null
     */
    public function onMessage($msgData, $message)
    {
        $channelID = (int) $msgData['message']['channelID'];

        if (in_array($channelID, $this->excludeChannel, true))
        {
            return null;
        }

        $this->message = $message;
        $user = $msgData['message']['from'];
        $channelID = $msgData['message']['channelID'];
        $message = $msgData['message']['message'];
        date_default_timezone_set('UTC');

        $data = command($message, $this->information()['trigger'], $this->config['bot']['trigger']);
        if (isset($data['trigger'])) {

            // Check if the channel is restricted
            if ($channelID == $this->excludeChannel) {
                return $this->message->reply('**Upcoming Ops not allowed in this channel**');
            }
            //fleetUp post upcoming operations
            $ops = json_decode(downloadData("http://api.fleet-up.com/Api.svc/tlYgBRjmuXj2Yl1lEOyMhlDId/{$this->userID}/{$this->apiKey}/Operations/{$this->groupID}"), true);
            foreach ($ops['Data'] as $operation) {
                $name = $operation['Subject'];
                $startTime = $operation['StartString'];
                preg_match_all('!\d+!', $operation['Start'], $epochStart);
                $desto = $operation['Location'];
                $formUp = $operation['LocationInfo'];
                $info = $operation['Details'];
                $id = $operation['Id'];
                $link = "https://fleet-up.com/Operation#{$id}\
	    ";
                $this->logger->addInfo("Sending ops info to {$user}");
                $this->message->reply("
**Upcoming Operation**.
Title - {$name}.
Form Up Time - {$startTime}. EVE time
Form Up System - {$formUp}.
Target System - {$desto}.
Details - {$info}.
Link - {$link}");
            }
        }
        return null;
    }

    /**
     * @return array
     */
    public function information()
    {
        return array(
            'name' => 'ops',
            'trigger' => array($this->config['bot']['trigger'] . 'ops'),
            'information' => 'This shows the upcoming operations. To use simply type <!ops>'
        );
    }
}
