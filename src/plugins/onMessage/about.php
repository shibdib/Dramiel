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
 * Class about
 * @property  info
 * @property  info
 * @property  info
 */
class about
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
    /**
     * @var
     */
    var $message;

    /**
     * @param $config
     * @param $discord
     * @param $logger
     * @internal param $message
     */
    function init($config, $discord, $logger)
    {
        $this->config = $config;
        $this->discord = $discord;
        $this->logger = $logger;
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
     */
    function onMessage($msgData, $message)
    {
        $this->message = $message;
        $info['guilds'] = $this->discord->guilds->count();

        global $startTime; // Get the starttime of the bot
        $time1 = new DateTime(date("Y-m-d H:i:s", $startTime));
        $time2 = new DateTime(date("Y-m-d H:i:s"));
        $interval = $time1->diff($time2);

        $botid = $this->discord->id;

        $message = $msgData["message"]["message"];
        $user = $msgData["message"]["from"];

        $data = command($message, $this->information()["trigger"], $this->config["bot"]["trigger"]);
        if (isset($data["trigger"])) {
            $gitRevision = gitRevision();
            $gitBranch = gitBranch();
            $msg = "```
Developer: Shibdib (In-game Name: Mr Twinkie)

Bot ID: " . $botid . "

Current Version: " . $gitRevision["short"] . "
Current Branch: " . $gitBranch . "
Github Repo: https://github.com/shibdib/Dramiel

Statistics:
Currently on {$info['guilds']} different discord servers.
Up-time: " . $interval->y . " Year(s), " . $interval->m . " Month(s), " . $interval->d . " Days, " . $interval->h . " Hours, " . $interval->i . " Minutes, " . $interval->s . " seconds.
Memory Usage: ~" . round(memory_get_usage() / 1024 / 1024, 3) . "MB```";
            $this->logger->addInfo("Sending about info to {$user}");
            $this->message->reply($msg);
        }
    }

    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "about",
            "trigger" => array($this->config["bot"]["trigger"] . "about"),
            "information" => "Shows information on the bot, who created it, what library it's using, revision, and other stats. Example: !about"
        );
    }
}
