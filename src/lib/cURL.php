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

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * @param $url
 * @return string
 */
function downloadData($url)
{
    $logger = new Logger('cURL');
    $logger->pushHandler(new StreamHandler(__DIR__ . '/log/libraryError.log', Logger::DEBUG));
    try {
        $userAgent = "Mozilla/5.0 (en-us;)";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($curl, CURLOPT_TIMEOUT, 12);
        curl_setopt($curl, CURLOPT_POST, false);
        curl_setopt($curl, CURLOPT_FORBID_REUSE, false);
        curl_setopt($curl, CURLOPT_ENCODING, "");
        $headers = array();
        $headers[] = "Connection: keep-alive";
        $headers[] = "Keep-Alive: timeout=12, max=1000";
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($curl);
        return $result;
    } catch (Exception $e) {
        $logger->error("cURL Error: " . $e->getMessage());
        return null;
    }
}
/**
 * @param string $url
 * @param $downloadPath
 * @return bool
 */
function downloadLargeData($url, $downloadPath)
{
    $logger = new Logger('cURL');
    $logger->pushHandler(new StreamHandler(__DIR__ . '/log/libraryError.log', Logger::DEBUG));
    $userAgent = "Mozilla/5.0 (en-us;)";
    try {
        $opts = array(
            'http' => array(
                'method' => "GET",
                'user_agent' => $userAgent,
            )
        );
        $context = stream_context_create($opts);
        $readHandle = fopen($url, "rb", false, $context);
        $writeHandle = fopen($downloadPath, "w+b");
        if (!$readHandle || !$writeHandle) {
            return false;
        }
        while (!feof($readHandle)) {
            if (fwrite($writeHandle, fread($readHandle, 4096)) === false) {
                return false;
            }
        }
        fclose($readHandle);
        fclose($writeHandle);
        return true;
    } catch (Exception $e) {
        $logger->error("Download Error: " . $e->getMessage());
        return false;
    }
}
