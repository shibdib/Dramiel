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
 * Class phpbbNotification
 */
class phpbbNotification
{
    public $config;
    public $db;
    public $discord;
    public $logger;
    public $guild;

    /**
     * @param $config
     * @param $primary
     * @param $discord
     * @param $logger
     */
    public function init($config, $primary, $discord, $discordWeb, $logger)
    {
        $this->config = $config;
        $this->discord = $discord;
        $this->logger = $logger;
        $this->guild = $config['bot']['guild'];
        $this->url = $config['plugins']['phpbbNotification']['url'];
        $lastCheck = getPermCache('phpbbNotification');
        if ($lastCheck === NULL) {
            // Schedule it for right now if first run
            setPermCache('phpbbNotification', time() - 5);
        }
    }

    /**
     *
     */
    public function tick()
    {
        $lastCheck = getPermCache('phpbbNotification');
        $url = $this->url;

        if ($lastCheck <= time()) {
            $this->logger->addInfo('phpbbNotification: Checking the forum for new mentions..');
            $this->getPhpbb($url);
            setPermCache('rssLastChecked', time() + 900);
            $this->logger->addInfo('phpbbNotification: All new posts checked..');
        }
    }

    /**
     * @param $url
     */

    private function getPhpbb($url)
    {
        $rss = new SimpleXMLElement($url, null, true); // XML parser
        $latestTopicDate = getPermCache("phpbbNotificationDate");
        $guildID = $this->guild;
        $guild = $this->discord->guilds->get('id', $guildID);

        //Check if feed has been checked before
        if ($latestTopicDate === NULL) {
            // Set date to now to avoid posting old articles
            setPermCache("phpbbNotificationDate", time());
        }

        //Find item to post
        foreach ($rss->entry as $item) {
            //Get item details
            $itemTitle = $item->title;
            $itemUrl = $item->id;
            $itemPubbed = $item->published;
            $itemDate = strtotime($item->published);
            $itemAuthor = $item->author;
            $itemContent = $item->content;

            if ($itemDate > getPermCache("phpbbNotificationDate")) {
                if (preg_match_all('/(?<!\w)@(\w+)/', $itemContent, $matches)) {
                    $users = $matches[1];
                    // $users should now contain array: ['SantaClaus', 'Jesus']
                    foreach ($users as $user) {
                        $user = strtolower(str_replace("_", " ", $user));
                        foreach ($guild->members as $member) {
                            $nickName = strtolower($member->nick);
                            $userName = strtolower($member->user->username);
                            if ($user === $nickName || $userName) {
                                $msg = "\n**You've been mentioned in a thread!**\n**{$itemTitle}**\n\n*{$itemPubbed}*\n{$itemUrl}\n\nYou were mentioned by{$itemAuthor}\n\n{$itemContent}";
                                $member->user->sendMessage($msg, false);
                                $this->logger->addInfo("phpbbNotification: Mention found! Informing {$nickName}");
                            }
                        }
                    }
                }
                //Check if item is old
                if ($itemDate <= $latestTopicDate) {
                    continue;
                }
                setPermCache("phpbbNotificationDate", $itemDate);
            }
        }
    }
}
