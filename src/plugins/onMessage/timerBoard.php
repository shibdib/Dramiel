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
 * Class timerBoard
 */
class timerBoard
{
    public $config;
    public $discord;
    public $logger;
    public $guild;
    private $message;
    private $triggers;


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
        $this->guild = $config['bot']['guild'];
        $this->login = $config['plugins']['timerBoard']['loginurl'];
        $this->timerBoard = $config['plugins']['timerBoard']['url'];
        $this->restricted = $config['plugins']['timerBoard']['restrictedChannels'];
        $this->username = $config['plugins']['timerBoard']['username'];
        $this->password = $config['plugins']['timerBoard']['password'];
        $this->triggers[] = $this->config['bot']['trigger'] . 'tb';
        $this->triggers[] = $this->config['bot']['trigger'] . 'TB';
        $this->triggers[] = $this->config['bot']['trigger'] . 'Tb';
    }

    /**
     *
     */
    public function onMessage($msgData, $message)
    {
        $this->message = $message;
        $tbLogin = $this->login;
        $tbUrl = $this->timerBoard;
        $message = $msgData['message']['message'];
        $user = $msgData['message']['from'];

        $data = command($message, $this->information()['trigger'], $this->config['bot']['trigger']);

        if (isset($data['trigger'])) {

            $channelID = (int) $msgData['message']['channelID'];

            if (in_array($channelID, $this->restricted, true))
            {
                return null;
            }

            //get username and password from config
            $username = $this->username;
            $password = $this->password;
            //login to website
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_COOKIEJAR, "cookie.txt");
            curl_setopt($ch, CURLOPT_URL, $tbLogin);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "UserLogin[username]=".$username."&UserLogin[password]=".$password);
            ob_start();
            curl_exec($ch);
            ob_end_clean();
            curl_close ($ch);
            unset($ch);
            //retrieve the timerboard website
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_COOKIEFILE, "cookie.txt");
            curl_setopt($ch, CURLOPT_URL, $tbUrl);
            $html = curl_exec($ch);
            curl_close($ch);

            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $dom->preserveWhiteSpace = false;
            $rows = $dom->getElementsByTagName('tr');
            $table_data = array();
            $i = 1;
            foreach ($rows as $row) {
                $cols = $row->getElementsByTagName('td');
                $j = 0;
                foreach ($cols as $col) {
                    $table_data[$i][$j] = $col->textContent;
                    $j++;
                }
                if (!is_null($table_data[$i][0])) {
                    $this->message->reply("```            Location:   {$table_data[$i][0]}
            Type:       {$table_data[$i][1]}
            Cycle:      {$table_data[$i][2]}
            Planet:     {$table_data[$i][3]}
            Moon:       {$table_data[$i][4]}
            Alliance:   {$table_data[$i][5]}
            Friendly:   {$table_data[$i][6]}
            Date:       {$table_data[$i][7]}
            Remaining:  {$table_data[$i][8]}```
            For more information visit $tbUrl");
                }
                $i++;
            }
            $this->logger->addInfo("TimerBoard: Sending timer board info to {$user}");
        }
    }

    public function information()
    {
        return array(
            'name' => 'tb',
            'trigger' => $this->triggers,
            'information' => 'This shows the upcoming timers from the FCON Timerboard. To use simply type <!tb>'
        );
    }
}
