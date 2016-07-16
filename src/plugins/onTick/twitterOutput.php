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
 * Class twitterOutput.
 */
class twitterOutput
{
    /*
     * @var
     */
    public $config;
    /*
     * @var
     */
    public $discord;
    /*
     * @var
     */
    public $logger;

    /*
     * @var
     */
    public $twitter;
    /*
     * @var
     */
    public $lastCheck;
    /*
     * @var
     */
    public $lastID;
    /*
     * @var
     */
    public $channelID;
    /*
     * @var
     */
    public $maxID;

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
        $this->twitter = new Twitter($config['twitter']['consumerKey'], $config['twitter']['consumerSecret'], $config['twitter']['accessToken'], $config['twitter']['accessTokenSecret']);
        $this->lastCheck = time();
        $this->maxID = 0;
        $this->channelID = $config['plugins']['twitterOutput']['channelID']; // outputs to the news channel on the 4M server
    }


    public function tick()
    {
        $continue = false;
        $data = [];
        // If last check + 60 seconds is larger or equal to the current time(), we run
        if ($this->lastCheck <= time()) {
            // Fetch the last 25 twitter replies and/or searches
            try {
                $data = $this->twitter->load(Twitter::ME_AND_FRIENDS, 5);
                foreach ($data as $message) {
                    $text = (array) $message->text;
                    $createdAt = (array) $message->created_at;
                    $postedBy = (array) $message->user->name;
                    $screenName = (array) $message->user->screen_name;
                    $id = (int) $message->id;
                    $this->lastID = getPermCache('twitterLatestID'); // get the last posted ID

                    if ($id <= $this->lastID) {
                        continue;
                    }

                    $this->maxID = max($id, $this->maxID);

                    $url = 'https://twitter.com/'.$screenName[0].'/status/'.$id;
                    $message = ['message' => $text[0], 'postedAt' => $createdAt[0], 'postedBy' => $postedBy[0], 'screenName' => $screenName[0], 'url' => $url.$id[0]];
                    $msg = '**@'.$screenName[0].'** ('.$message['postedBy'].') / '.htmlspecialchars_decode($message['message']);
                    $messages[$id] = $msg;

                    $continue = true;

                    if (count($data)) {
                        setPermCache('twitterLatestID', $this->maxID);
                    }
                }
            } catch (Exception $e) {
                //$this->logger->err("Twitter Error: " . $e->getMessage()); // Don't show there was an error, it's most likely just a rate limit
            }

            if ($continue == true) {
                ksort($messages);

                foreach ($messages as $id => $msg) {
                    // Send the tweets to the channel
                    $channelID = $this->channelID;
                    $channel = Channel::find($channelID);
                    $channel->sendMessage($msg, false);
                    sleep(1); // Lets sleep for a second, so we don't rage spam
                }
            }
            $this->lastCheck = time() + 60;
        }
    }

    /**
     * @param $url
     *
     * @return string
     */
    public function shortenUrl($url)
    {
        return file_get_contents('http://is.gd/api.php?longurl='.$url);
    }

    /**
     * @param $msgData
     */
    public function onMessage($msgData)
    {
    }

    /**
     * @return array
     */
    public function information()
    {
        return [
            'name'        => '',
            'trigger'     => [''],
            'information' => '',
        ];
    }
}
