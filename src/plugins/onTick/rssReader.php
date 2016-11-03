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
 * Class rssReader
 */
class rssReader
{
    /**
     * @var
     */
    var $config;
    /**
     * @var
     */
    var $db;
    /**
     * @var
     */
    var $discord;
    /**
     * @var
     */
    var $channelConfig;
    /**
     * @var int
     */
    var $lastCheck = 0;
    /**
     * @var
     */
    var $logger;
    public $guild;

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
        $this->guild = $config["bot"]["guild"];
        $this->rssFeeds = $config["plugins"]["rssReader"]["rssFeeds"];
        $this->toDiscordChannel = $config["plugins"]["rssReader"]["channelID"];
        $lastCheck = getPermCache("rssLastChecked");
        if ($lastCheck == NULL) {
            // Schedule it for right now if first run
            setPermCache("rssLastChecked", time() - 5);
        }
    }

    /**
     *
     */
    function tick()
    {
        $discord = $this->discord;
        $lastCheck = getPermCache("rssLastChecked");
        $feeds = $this->rssFeeds;
        $toChannel = $this->toDiscordChannel;

        if ($lastCheck <= time()) {
            $this->logger->addInfo("RSS Reader: Checking RSS feeds for updated news..");
            $this->getRss($feeds, $toChannel, $discord);
            setPermCache("rssLastChecked", time() + 900);
            $this->logger->addInfo("RSS Reader: All feeds checked..");
        }
    }

    function getRss($feeds, $toChannel, $discord)
    {
        foreach ($feeds as $rssUrl) {
            //Check that url is set
            if (!isset($rssUrl) || $rssUrl == "") {
                continue;
            }

            $rss = simplexml_load_file($rssUrl); // XML parser
            $feedLink = $rss->channel->link;
            $latestTopicDate = getPermCache("rssFeed{$feedLink}");

            //Check if feed has been checked before
            if ($latestTopicDate == NULL) {
                // Set date to now to avoid posting old articles
                setPermCache("rssFeed{$feedLink}", time());
                continue;
            }

            //Find item to check if feed is formatted
            $itemTitle = (string)$rss->channel->item->title;
            $itemUrl = (string)$rss->channel->item->link;
            $itemDate = strtotime($rss->channel->item->pubDate);

            //Check to see if feed is formatted correctly
            if ($itemTitle == NULL || $itemUrl == NULL || $itemDate == NULL) {
                $this->logger->addInfo("RSS Reader Error: {$rssUrl} is not a properly formatted RSS feed.");
                continue;
            }

            //Find item to post
            foreach ($rss->channel->item as $item) {
                //Get item details
                $itemTitle = (string)$item->title;
                $itemUrl = (string)$item->link;
                $itemPubbed = $item->pubDate;
                $itemDate = strtotime($item->pubDate);

                //Check if item is old
                if ($itemDate <= $latestTopicDate) {
                    continue;
                }

                //Set message
                $msg = "\n**{$itemTitle}**\n\n*{$itemPubbed}*\n{$itemUrl}";

                //Send message
                $channelID = $toChannel;
                $guild = $discord->guilds->get('id', $this->guild);
                $channel = $guild->channels->get('id', $channelID);
                $channel->sendMessage($msg, false);
                $this->logger->addInfo("RSS Reader Item Posted: {$itemTitle}");
                if ($itemDate > getPermCache("rssFeed{$feedLink}")) {
                    setPermCache("rssFeed{$feedLink}", $itemDate);
                }
            }
        }
    }
}
