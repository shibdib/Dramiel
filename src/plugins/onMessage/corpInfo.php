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
class corpInfo
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
    var $excludeChannel;
    public $message;

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
        $this->excludeChannel = $this->config['bot']['restrictedChannels'];
    }

    /**
     *
     */
    function tick()
    {
    }

    /**
     * @param $msgData
     * @param $message
     * @return null
     */
    function onMessage($msgData, $message)
    {
        $channelID = (int)$msgData['message']['channelID'];

        if (in_array($channelID, $this->excludeChannel, true))
        {
            return null;
        }

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
                return $this->message->reply('**Error:** Unable to find any group matching that name.');
            }
            $corpID = null;
            if (isset($xml->result->rowset->row)) {
                foreach ($xml->result->rowset->row as $character) {
                    $corpID = $character->attributes()->characterID;
                }
            }

            if (empty($corpID)) {
                return $this->message->reply('**Error:** Unable to find any group matching that name.');
            }

            $url = "https://api.eveonline.com/corp/CorporationSheet.xml.aspx?corporationID={$corpID}";
            $xml = makeApiRequest($url);
            foreach ($xml->result as $corporation) {
                $corporationName = $corporation->corporationName;
                $allianceName = $corporation->allianceName;
                $ceoName = $corporation->ceoName;
                $taxRate = $corporation->taxRate;
                $homeStation = $corporation->stationName;
                $memberCount = $corporation->memberCount;
                $corpTicker = $corporation->ticker;
                $url = "https://zkillboard.com/corporation/{$corpID}/";
            }

            if ($corporationName == null || $corporationName == '') {
                return $this->message->reply('**Error:** No corporation found.');
            }


            $msg = "```Corp Name: {$corporationName}
Corp Ticker: {$corpTicker}
CEO: {$ceoName}
Alliance Name: {$allianceName}
Home Station: {$homeStation}
Tax Rate: {$taxRate}%
Member Count: {$memberCount}
```
For more info, visit: $url";

            $this->logger->addInfo("corpInfo: Sending corp info to {$user}");
            $this->message->reply($msg);
        }
        return null;
    }

    /**
     * @return array
     */
    function information()
    {
        return array(
            'name' => 'corp',
            'trigger' => array($this->config['bot']['trigger'] . 'corp'),
            'information' => 'Returns basic EVE Online data about a corporation from projectRena. To use simply type !corp corporation_name'
        );
    }

    function onMessageAdmin()
    {
    }

}
