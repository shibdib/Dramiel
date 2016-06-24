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

function updateSluggardDB($logger)
{
    $tables = array("users", "usersSeen", "storage", "shipFits");

    $tableCreateCode = array(
        "users" => "
            BEGIN;
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `serverID` BIGINT(20) NOT NULL,
                `userID` BIGINT(20) NOT NULL,
                `discordID` BIGINT(20) NOT NULL,
                `characterID` INT(16) NOT NULL,
                `corporationID` VARCHAR(255) NOT NULL,
                `allianceID` VARCHAR(255) NOT NULL,
                `authString` VARCHAR(255) NOT NULL,
                `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE UNIQUE INDEX userID ON users (userID);
            CREATE INDEX serverID ON users (serverID);
            CREATE INDEX corporationID ON users (corporationID);
            CREATE INDEX allianceID ON users (allianceID);
            COMMIT;",
        "usersSeen" => "
            BEGIN;
            CREATE TABLE IF NOT EXISTS `usersSeen` (
                `id` INTEGER PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `isAdmin` TINYINT(1) NOT NULL DEFAULT '0',
                `lastSeen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `lastSpoke` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                `lastStatus` VARCHAR(50) NULL DEFAULT NULL,
                `lastWritten` TEXT NULL
            );
            CREATE INDEX name ON usersSeen (name);
            COMMIT;",
        "storage" => "
            BEGIN;
            CREATE TABLE IF NOT EXISTS `storage` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `key` VARCHAR(255) NOT NULL,
                `value` VARCHAR(255) NOT NULL
            );
            CREATE UNIQUE INDEX key ON storage (key);
            COMMIT;",
        "shipFits" => "
            BEGIN;
            CREATE TABLE IF NOT EXISTS `shipFits` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `submitter` VARCHAR(255) NOT NULL,
                `fit` VARCHAR(255) NOT NULL,
                `fitLink` VARCHAR(255) NOT NULL
            );
            COMMIT;",
    );

    // Does the file exist?
    if(!file_exists(__DIR__ . "/../database/sluggard.sqlite"))
        touch(__DIR__ . "/../database/sluggard.sqlite");

    // Create table if not exists
    foreach($tables as $table) {
        $exists = dbQueryField("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name", "name", array(":name" => $table));
        if(!$exists) {
            $logger->warn("Creating {$table} in sluggard.sqlite, since it does not exist");
            dbExecute(trim($tableCreateCode[$table]));
        }
    }
}