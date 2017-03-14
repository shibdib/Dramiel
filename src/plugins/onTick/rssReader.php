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
    public $config;
    public $db;
    public $discord;
    public $logger;
    public $guild;
    public $itemTitle;
    public $itemUrl;
    public $itemDate;
	public $strDate;

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
        $this->rssFeeds = $config['plugins']['rssReader']['rssFeeds'];
        $this->toDiscordChannel = $config['plugins']['rssReader']['channelID'];
        $lastCheck = 1489200520; //getPermCache('rssLastChecked');
        if (is_null($lastCheck)) {
            // Schedule it for right now if first run
            setPermCache('rssLastChecked', time() - 5);
        }
    }

    /**
     *
     */
    public function tick()
    {
        $lastCheck = getPermCache('rssLastChecked');
        $feeds = $this->rssFeeds;
        $toChannel = $this->toDiscordChannel;

        if ($lastCheck <= time()) {
            $this->logger->addInfo('rssReader: Checking RSS feeds for updated news..');
            $this->getRss($feeds, $toChannel);
            setPermCache('rssLastChecked', time() + 900);
            $this->logger->addInfo('rssReader: All feeds checked..');
        }
    }

    /**
     * @param $feeds
     * @param $toChannel
     */
	 
    private function getRss($feeds, $toChannel)
    {
        foreach ($feeds as $rssUrl) {
            //Check that url is set
            if (!isset($rssUrl) || $rssUrl === '') {
                continue;
            }

            $rss = new SimpleXMLElement($rssUrl); // XML parser
			if ($rss===null || !is_object($rss)) {
				$this->logger->addInfo("Failed to load xml file: {$rssUrl}");
				break;
			}
			if (!is_object($rss->feed)) {
				$this->logger->addInfo("Feed is not an object at: {$rssUrl}");
				break;
			}
			$feedLink = $rss->feed->id;
            $latestTopicDate = getPermCache("rssFeed{$feedLink}");

            //Check if feed has been checked before
            if ($latestTopicDate === NULL) {
                // Set date to now to avoid posting old articles
                setPermCache("rssFeed{$feedLink}", time());
                continue;
            }

            //Find item to check if feed is formatted 
            $rss->feed->entry[0]->title = $itemTitle;
            $rss->feed->entry[0]->id = $itemUrl;
			$rss->feed->entry[0]->published = $strDate;
			
			$itemDate = strtotime($strDate);
			
            //Check to see if feed is formatted correctly
            if (is_null($itemTitle) || is_null($itemUrl) || is_null($itemDate)) {
                $this->logger->addInfo("rssReader: {$rssUrl} is not a properly formatted RSS feed. {$itemTitle} {$itemUrl} {$itemDate}");
                continue;
            }

            //Find item to post
            foreach ($rss->feed->entry as $item) {
                //Get item details
                $itemTitle = $item->title;
                $itemUrl = $item->id;
                $itemPubbed = $item->published;
                $itemDate = strtotime($item->published);

                //Check if item is old
                if ($itemDate <= $latestTopicDate) {
                    continue;
                }

                //Set message
                $msg = "\n**{$itemTitle}**\n\n*{$itemPubbed}*\n{$itemUrl}";

                //Send message
                $channelID = $toChannel;
                queueMessage($msg, $channelID, $this->guild);
                $this->logger->addInfo("rssReader: Item Queued {$itemTitle}");
                if ($itemDate > getPermCache("rssFeed{$feedLink}")) {
                    setPermCache("rssFeed{$feedLink}", $itemDate);
                }
            }
        }
    }
}
