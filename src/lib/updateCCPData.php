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

function updateCCPData($logger) {
    $ccpDataURL = "https://mambaonline.org/bot/sqlite-latest.sqlite.bz2";
    $ccpDataMD5URL = "https://mambaonline.org/bot/sqlite-latest.sqlite.bz2.md5";
    $databaseDir = __DIR__ . "/../database/";
    $md5 = explode(" ", downloadData($ccpDataMD5URL))[0];
    $lastSeenMD5 = getPermCache("SluggardCCPDataMD5");
    if($lastSeenMD5 !== $md5) {
        try {
            $logger->notice("Updating CCP SQLite DB");
            $logger->notice("Downloading bz2 file and writing it to {$databaseDir}ccpData.sqlite.bz2");
            $downloadedData = downloadLargeData($ccpDataURL, "{$databaseDir}ccpData.sqlite.bz2");
            if($downloadedData == false) {
                $logger->warn("Error: File not downloaded successfully.");
                $logger->warn("**Error:** File not downloaded successfully, check bot command line for more information!");
            }
            $logger->notice("Opening bz2 file");
            $sqliteData = bzopen("{$databaseDir}ccpData.sqlite.bz2", "r");
            $logger->notice("Reading from bz2 file");
            $data = "";
            while(!feof($sqliteData))
                $data .= bzread($sqliteData, 4096);
            $logger->notice("Writing bz2 file contents into .sqlite file");
            file_put_contents("{$databaseDir}/ccpData.sqlite", $data);
            $logger->notice("Flushing bz2 data from memory");
            $data = null;
            $logger->notice("Memory in use: " . memory_get_usage() / 1024 / 1024 . "MB");
            gc_collect_cycles(); // Collect garbage
            $logger->notice("Memory in use after garbage collection: " . memory_get_usage() / 1024 / 1024 . "MB");
            $logger->notice("Deleting bz2 file");
            unlink("{$databaseDir}/ccpData.sqlite.bz2");
            setPermCache("SluggardCCPDataMD5", $md5);
            // Create the mapCelestialsView
            $logger->notice("Creating the mapAllCelestials view");
            dbExecute("CREATE VIEW mapAllCelestials AS SELECT itemID, itemName, typeName, mapDenormalize.typeID, solarSystemName, mapDenormalize.solarSystemID, mapDenormalize.constellationID, mapDenormalize.regionID, mapRegions.regionName, orbitID, mapDenormalize.x, mapDenormalize.y, mapDenormalize.z FROM mapDenormalize JOIN invTypes ON (mapDenormalize.typeID = invTypes.typeID) JOIN mapSolarSystems ON (mapSolarSystems.solarSystemID = mapDenormalize.solarSystemID) JOIN mapRegions ON (mapDenormalize.regionID = mapRegions.regionID) JOIN mapConstellations ON (mapDenormalize.constellationID = mapConstellations.constellationID)", array(), "ccp");
        } catch(\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
    else
        $logger->info("CCP Database already up to date");
}