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
 * Class price
 */
class price
{
    /**
     * @var array
     */
    public $triggers = array();
    private $excludeChannel;
    private $message;
    private $config;
    private $discord;
    private $logger;

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
        $this->triggers[] = $this->config['bot']['trigger'] . 'pc';
        $this->triggers[] = $this->config['bot']['trigger'] . strtolower('Jita');
        $this->triggers[] = $this->config['bot']['trigger'] . strtolower('Amarr');
        $this->triggers[] = $this->config['bot']['trigger'] . strtolower('Rens');
        $this->triggers[] = $this->config['bot']['trigger'] . strtolower('Dodixie');
        $this->triggers[] = $this->config['bot']['trigger'] . 'Pc';
        $this->triggers[] = $this->config['bot']['trigger'] . 'Jita';
        $this->triggers[] = $this->config['bot']['trigger'] . 'Amarr';
        $this->triggers[] = $this->config['bot']['trigger'] . 'Rens';
        $this->triggers[] = $this->config['bot']['trigger'] . 'Dodixie';
        $this->excludeChannel = $this->config['bot']['restrictedChannels'];
    }

    /**
     * @param $msgData
     * @param $message
     * @return null
     */
    public function onMessage($msgData, $message)
    {
        $this->message = $message;
        $user = $msgData['message']['from'];
        $channelID = (int) $msgData['message']['channelID'];

        if (in_array($channelID, $this->excludeChannel, true))
        {
            return null;
        }


        // Bind a few things to vars for the plugins
        $message = $msgData['message']['message'];

        $data = command(strtolower($message), $this->information()['trigger'], $this->config['bot']['trigger']);

        if (isset($data['trigger'])) {

            $systemName = $data['trigger'];
            $itemName = $data['messageString'];
            $single = getTypeID($itemName);

            // Check if the channel is restricted
            if (in_array($channelID, $this->excludeChannel, true)) {
                return $this->message->reply('**Price Check not allowed in this channel**');
            }

            // If there is a single result, we'll get data now!
            if ($single) {
                $typeID = $single;

                if ($systemName === 'pc') {
                    $solarSystemID = 'global';
                } else {
                    $solarSystemID = getSystemID($systemName);
                }

                // Get pricing data
                if ($solarSystemID === 'global') {
                    $data = new SimpleXMLElement(downloadData("https://api.eve-central.com/api/marketstat?typeid={$typeID}"));
                } else {
                    $data = new SimpleXMLElement(downloadData("https://api.eve-central.com/api/marketstat?usesystem={$solarSystemID}&typeid={$typeID}"));
                }

                $lowBuy = str_pad(number_format((float) $data->marketstat->type->buy->min, 2),18," ",STR_PAD_LEFT);
                $avgBuy = str_pad(number_format((float) $data->marketstat->type->buy->avg, 2),18," ",STR_PAD_LEFT);
                $highBuy = str_pad(number_format((float) $data->marketstat->type->buy->max, 2),18," ",STR_PAD_LEFT);
                $lowSell = str_pad(number_format((float) $data->marketstat->type->sell->min, 2),18," ",STR_PAD_LEFT);
                $avgSell = str_pad(number_format((float) $data->marketstat->type->sell->avg, 2),18," ",STR_PAD_LEFT);
                $highSell = str_pad(number_format((float) $data->marketstat->type->sell->max, 2),18," ",STR_PAD_LEFT);

                $this->logger->addInfo("Price: Sending pricing info to {$user}");
                $solarSystemName = $systemName === 'pc' ? 'Global' : ucfirst($systemName);
                $messageData = "
```  System:   {$solarSystemName}
    Item:   {$itemName}```
**Buy:**
```    Low: {$lowBuy}
    Avg: {$avgBuy}
   High: {$highBuy}```
**Sell:**
```    Low: {$lowSell}
    Avg: {$avgSell}
   High: {$highSell}```";
                $this->message->reply($messageData);
            } else {
                $this->message->reply("**Error:** ***{$itemName}*** not found");
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
            'name' => 'pc',
            'trigger' => $this->triggers,
            'information' => 'Shows price information for items in EVE. To use simply type **!pc item_name** for global stats or **!jita/amarr/rens_or_dodixie item_name** for hub specific info.'
        );
    }
}
