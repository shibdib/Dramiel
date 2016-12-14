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
 */

function updateDramielDB($logger)
{
    $tables = array('storage', 'messageQueue', 'renameQueue', 'corpCache');

    $tableCreateCode = array(
        'storage' => '
            BEGIN;
            CREATE TABLE IF NOT EXISTS `storage` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `key` VARCHAR(255) NOT NULL,
                `value` VARCHAR(255) NOT NULL
            );
            CREATE UNIQUE INDEX key ON storage (key);
            COMMIT;',
        'messageQueue' => '
            BEGIN;
            CREATE TABLE IF NOT EXISTS `messageQueue` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `message` VARCHAR(255) NOT NULL,
                `channel` VARCHAR(255) NOT NULL,
                `guild` VARCHAR(255) NOT NULL
            );
            COMMIT;',
        'renameQueue' => '
            BEGIN;
            CREATE TABLE IF NOT EXISTS `renameQueue` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `discordID` VARCHAR(255) NOT NULL,
                `nick` VARCHAR(255) NOT NULL,
                `guild` VARCHAR(255) NOT NULL
            );
            COMMIT;',
        'corpCache' => '
            BEGIN;
            CREATE TABLE IF NOT EXISTS `corpCache` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `corpID` VARCHAR(255) NOT NULL UNIQUE,
                `corpTicker` VARCHAR(255) NOT NULL,
                `corpName` VARCHAR(255) NOT NULL
            );
            COMMIT;',
    );

    // Does the file exist?
    if (!file_exists(__DIR__ . '/../../database/dramiel.sqlite')) {
        touch(__DIR__ . '/../../database/dramiel.sqlite');
    }

    // Create table if not exists
    foreach ($tables as $table) {
        $exists = dbQueryField("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name", 'name', array(':name' => $table));
        if (!$exists) {
            $logger->addInfo("Creating {$table} in dramiel.sqlite, since it does not exist");
            dbExecute(trim($tableCreateCode[$table]));
        }
    }
}