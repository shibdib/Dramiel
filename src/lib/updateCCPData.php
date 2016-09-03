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
 * @param $logger
 * @return null
 * @throws Exception
 */

function updateCCPData($logger) {
    $ccpDataURL = "https://mambaonline.org/bot/sqlite-latest.sqlite.bz2";
    $ccpDataMD5URL = "https://mambaonline.org/bot/sqlite-latest.sqlite.bz2.md5";
    $databaseDir = __DIR__ . "/../../database/";
    $md5 = explode(" ", downloadData($ccpDataMD5URL))[0];
    $lastSeenMD5 = getPermCache("CCPDataMD5");
    $lastChecked = getPermCache("CCPDataLastAttempt");
    $completed = getPermCache("CCPDataCompleted");
    if (!isset($lastChecked)) {
        $lastChecked = 1;
    }
    if ($lastSeenMD5 !== $md5 && time() > $lastChecked || $completed !== "1") {
        try {
            $checkNext = time() + 79200;
            setPermCache("CCPDataLastAttempt", $checkNext);
            $logger->addInfo("Updating CCP SQLite DB");
            $logger->addInfo("Downloading bz2 file and writing it to {$databaseDir}ccpData.sqlite.bz2");
            $downloadedData = downloadLargeData($ccpDataURL, "{$databaseDir}ccpData.sqlite.bz2");
            if ($downloadedData == false) {
                $logger->addInfo("**Error:** File not downloaded successfully, check bot command line for more information!");
                return null;
            }
            $logger->addInfo("Opening bz2 file");
            $sqliteData = bzopen("{$databaseDir}ccpData.sqlite.bz2", "r");
            $logger->addInfo("Reading from bz2 file");
            $data = "";
            while (!feof($sqliteData)) {
                $data .= bzread($sqliteData, 4096);
            }
            $logger->addInfo("Writing bz2 file contents into .sqlite file");
            file_put_contents("{$databaseDir}/ccpData.sqlite", $data);
            $logger->addInfo("Flushing bz2 data from memory");
            $data = null;
            $logger->addInfo("Memory in use: " . memory_get_usage() / 1024 / 1024 . "MB");
            gc_collect_cycles(); // Collect garbage
            $logger->addInfo("Memory in use after garbage collection: " . memory_get_usage() / 1024 / 1024 . "MB");
            $logger->addInfo("Deleting bz2 file");
            unlink("{$databaseDir}/ccpData.sqlite.bz2");
            setPermCache("CCPDataMD5", $md5);
            // Create the mapCelestialsView
            $logger->addInfo("Creating the mapAllCelestials view");
            dbExecute("DROP VIEW IF EXISTS mapAllCelestials", array(), "ccp");
            dbExecute("CREATE VIEW mapAllCelestials AS SELECT itemID, itemName, typeName, mapDenormalize.typeID, solarSystemName, mapDenormalize.solarSystemID, mapDenormalize.constellationID, mapDenormalize.regionID, mapRegions.regionName, orbitID, mapDenormalize.x, mapDenormalize.y, mapDenormalize.z FROM mapDenormalize JOIN invTypes ON (mapDenormalize.typeID = invTypes.typeID) JOIN mapSolarSystems ON (mapSolarSystems.solarSystemID = mapDenormalize.solarSystemID) JOIN mapRegions ON (mapDenormalize.regionID = mapRegions.regionID) JOIN mapConstellations ON (mapDenormalize.constellationID = mapConstellations.constellationID)", array(), "ccp");
            setPermCache("CCPDataCompleted", "1");
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        if ($lastSeenMD5 !== $md5 && time() < $lastChecked) {
            $logger->addInfo("Updating CCP SQLite DB");
            $logger->addInfo("Downloading large file and writing it to {$databaseDir}ccpData.sqlite");
            $downloadedData = downloadLargeData($ccpDataURL, "{$databaseDir}ccpData.sqlite");
            if ($downloadedData == false) {
                $logger->addInfo("**Error:** File not downloaded successfully, check bot command line for more information!");
                return null;
            }
            setPermCache("CCPDataMD5", $md5);
        }
    } else {
        $logger->addInfo("CCP Database already up to date");
    }
}