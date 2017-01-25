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
class setAvatar
{
    public $config;
    public $discord;
    public $logger;
    public $message;

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
    }

    /**
     * @param $msgData
     * @param $message
     * @return null
     */
    public function onMessage($msgData, $message)
    {
        $this->message = $message;

        $message = $msgData['message']['message'];

        $data = command($message, $this->information()['trigger'], $this->config['bot']['trigger']);
        if (isset($data['trigger'])) {

            //Admin Check
            $userID = $msgData['message']['fromID'];
            $adminRoles = $this->config['bot']['adminRoles'];
            $adminRoles = array_map('strtolower', $adminRoles);
            $id = $this->config['bot']['guild'];
            $guild = $this->discord->guilds->get('id', $id);
            $member = $guild->members->get('id', $userID);
            $roles = $member->roles;
            foreach ($roles as $role) {
                if (in_array(strtolower($role->name), $adminRoles, true)) {
                    $avatarURL = strtolower((string)$data['messageString']);
                    $ch = curl_init($avatarURL);
                    if (substr($avatarURL, -4) === '.jpg') {
                        $fp = fopen('/tmp/avatar.jpg', 'wb');
                        $dir = '/tmp/avatar.jpg';
                    } elseif (substr($avatarURL, -4) === '.png') {
                        $fp = fopen('/tmp/avatar.png', 'wb');
                        $dir = '/tmp/avatar.png';
                    } else {
                        return $this->message->reply('Invalid URL. Make sure the URL links directly to a JPG or PNG.');
                    }
                    curl_setopt($ch, CURLOPT_FILE, $fp);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    curl_exec($ch);
                    curl_close($ch);
                    fclose($fp);
                    $this->discord->setAvatar($dir);
                    $this->discord->save();

                    $msg = 'New avatar set';
                    $this->logger->addInfo("setGame: Bot avatar changed to {$avatarURL} by {$msgData['message']['from']}");
                    $this->message->reply($msg);
                    return null;
                }
            }
            $this->logger->addInfo("setGame: {$msgData['message']['from']} attempted to change the bot's avatar.");
            $msg = ':bangbang: You do not have the necessary roles to issue this command :bangbang:';
            $this->message->reply($msg);
            return null;
        }
        return null;
    }

    /**
     * @return array
     */
    public function information()
    {
        return array(
            'name' => 'avatar',
            'trigger' => array($this->config['bot']['trigger'] . 'avatar'),
            'information' => 'Changes the bots avatar (!avatar url) **(Admin Role Required)**'
        );
    }

}
