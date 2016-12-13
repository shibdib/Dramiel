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
class getLog
{
    /**
     * @var
     */
    public $config;
    public $discord;
    public $logger;
    public $message;

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
            $id = $this->config['bot']['guild'];
            $guild = $this->discord->guilds->get('id', $id);
            $member = $guild->members->get('id', $userID);
            $roles = $member->roles;
            foreach ($roles as $role) {
                if (in_array($role->name, $adminRoles, true)) {
                    $logType = (string) $data['messageString'];
                    if ($logType === 'log') {
                        $filePath = __DIR__ . '/../../../log/dramielLog.log';
                        $title = "dramielLog - from userID {$userID}";
                    } elseif ($logType === 'error') {
                        $filePath = __DIR__ . '/../../../log/dramielError.log';
                        $title = "dramielError - from userID {$userID}";
                    } elseif ($logType === 'other') {
                        $filePath = __DIR__ . '/../../../log/dramielOther.log';
                        $title = "dramielOther - from userID {$userID}";
                    } else {
                        $msg = 'Incorrect log selected. Use either log, error, other';
                        $this->message->reply($msg);
                        return null;
                    }
                    $logContents = tailCustom($filePath, 100);
                    $pasteURL = pasteLog($logContents, $title);

                    $this->message->reply($pasteURL);
                    return null;
                }
            }
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
            'name' => 'log',
            'trigger' => array($this->config['bot']['trigger'] . 'log'),
            'information' => 'Get a pastebin of the last 100 lines of your log files. Follow command with either log, error, other **(Admin Role Required)**'
        );
    }

}
