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

// Check if TQs status has changed
$loop->addPeriodicTimer(150, function() use ($logger, $discord, $config) {
    $crestData = json_decode(downloadData("https://public-crest.eveonline.com/"), true);
    $tqStatus = isset($crestData["serviceStatus"]["eve"]) ? $crestData["serviceStatus"]["eve"] : "offline";
    $tqOnline = (int) $crestData["userCounts"]["eve"];

    // Store the current status in the permanent cache
    $oldStatus = getPermCache("eveTQStatus");
    if ($tqStatus !== $oldStatus) {
        if ($tqStatus == "Offline") {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $api = serverStatus();
            if ($api = "true") {
                return null;
            }
        }
        $msg = "**New TQ Status:** {$tqStatus} / {$tqOnline} users online.";
        $logger->info("TQ Status changed from {$oldStatus} to {$tqStatus}");
        $discord->api("channel")->messages()->create($config["plugins"]["periodicTQStatus"]["channelID"], $msg);
        setPermCache("eveTQStatus", $tqStatus);
        return null;
    }
    $logger->info("TQ Status unchanged.");
    setPermCache("eveTQStatus", $tqStatus);
    return null;
});