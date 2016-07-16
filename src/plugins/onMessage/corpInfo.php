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
use Discord\Parts\Channel\Message;

/**
 * @property  message
 */
class corpInfo
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


    public function tick()
    {
    }

    /**
     * @param $msgData
     * @param $message
     *
     * @return null
     */
    public function onMessage($msgData, $message)
    {
        $this->message = $message;

        $message = $msgData['message']['message'];
        $user = $msgData['message']['from'];

        $data = command($message, $this->information()['trigger'], $this->config['bot']['trigger']);
        if (isset($data['trigger'])) {
            $messageString = $data['messageString'];
            $cleanString = urlencode($messageString);
            $url = "https://api.eveonline.com/eve/CharacterID.xml.aspx?names={$cleanString}";
            $xml = makeApiRequest($url);
            if (empty($data)) {
                return $this->message->reply('**Error:** no results was returned.');
            }
            $corpID = null;
            if (isset($xml->result->rowset->row)) {
                foreach ($xml->result->rowset->row as $character) {
                    $corpID = $character->attributes()->characterID;
                }
            }

            if (empty($corpID)) {
                return $this->message->reply('**Error:** no data available');
            }

            // Get stats
            $statsURL = 'https://beta.eve-kill.net/api/corpInfo/corporationID/'.urlencode($corpID).'/';
            $stats = json_decode(downloadData($statsURL), true);

            if (empty($stats)) {
                return $this->message->reply('**Error:** no data available');
            }

            $corporationName = @$stats['corporationName'];
            $allianceName = isset($stats['allianceName']) ? $stats['allianceName'] : 'None';
            $factionName = isset($stats['factionName']) ? $stats['factionName'] : 'None';
            $ceoName = @$stats['ceoName'];
            $homeStation = @$stats['stationName'];
            $taxRate = @$stats['taxRate'];
            $corporationActiveArea = @$stats['corporationActiveArea'];
            $allianceActiveArea = @$stats['allianceActiveArea'];
            $lifeTimeKills = @$stats['lifeTimeKills'];
            $lifeTimeLosses = @$stats['lifeTimeLosses'];
            $memberCount = @$stats['memberArrayCount'];
            $superCaps = @count($stats['superCaps']);
            $ePeenSize = @$stats['ePeenSize'];
            $url = 'https://beta.eve-kill.net/corporation/'.@$stats['corporationID'].'/';


            $msg = "```corporationName: {$corporationName}
allianceName: {$allianceName}
factionName: {$factionName}
ceoName: {$ceoName}
homeStation: {$homeStation}
taxRate: {$taxRate}
corporationActiveArea: {$corporationActiveArea}
allianceActiveArea: {$allianceActiveArea}
lifeTimeKills: {$lifeTimeKills}
lifeTimeLosses: {$lifeTimeLosses}
memberCount: {$memberCount}
superCaps: {$superCaps}
ePeenSize: {$ePeenSize}
```
For more info, visit: $url";

            $this->logger->addInfo("Sending character info to {$user}");
            $this->message->reply($msg);
        }
    }

    /**
     * @return array
     */
    public function information()
    {
        return [
            'name'        => 'corp',
            'trigger'     => [$this->config['bot']['trigger'].'corp'],
            'information' => 'Returns basic EVE Online data about a corporation from projectRena. To use simply type !corp corporation_name',
        ];
    }

    /**
     * @param $msgData
     */
    public function onMessageAdmin($msgData)
    {
    }
}
