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
 * @param $config
 * @param $primary
 */

function authDB($logger)
{
    $tables = array('authUsers', 'pendingUsers');

    $tableCreateCode = array(
        'authUsers' => '
            BEGIN;
            CREATE TABLE IF NOT EXISTS `authUsers` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `eveName` VARCHAR(255) NOT NULL,
                `characterID` VARCHAR(255) NOT NULL UNIQUE,
                `discordID` VARCHAR(255) NOT NULL,
                `role` VARCHAR(255) NOT NULL,
                `active` VARCHAR(255) NOT NULL DEFAULT \'yes\'
            );
            COMMIT;',
        'pendingUsers' => '
            BEGIN;
            CREATE TABLE IF NOT EXISTS `pendingUsers` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `characterID` VARCHAR(255) NOT NULL,
                `corporationID` VARCHAR(255) NOT NULL,
                `allianceID` VARCHAR(255) NOT NULL,
                `authString` VARCHAR(255) NOT NULL,
                `active` VARCHAR(255) NOT NULL
            );
            COMMIT;',
    );

    if (!file_exists(__DIR__ . '/../../database/authDB.sqlite')) {
        touch(__DIR__ . '/../../database/authDB.sqlite');
    }

    // Create table if not exists
    foreach ($tables as $table) {
        $exists = dbQueryField("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name", 'name', array(':name' => $table));
        if (!$exists) {
            $logger->addInfo("Creating {$table} in authDB.sqlite, since it does not exist");
            dbExecute(trim($tableCreateCode[$table]));
        }
    }
}