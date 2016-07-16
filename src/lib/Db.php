<?php
/**
 * The MIT License (MIT).
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
 * @param null $db
 *
 * @return null|PDO
 */
function openDB($db = null)
{
    if ($db == null) {
        $db = __DIR__.'/../../database/dramiel.sqlite';
    }
    if ($db == 'ccp') {
        $db = __DIR__.'/../../database/ccpData.sqlite';
    }

    $dsn = "sqlite:$db";
    try {
        $pdo = new PDO($dsn, '', '', [
                PDO::ATTR_PERSISTENT       => false,
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            ]
        );
    } catch (Exception $e) {
        var_dump($e->getMessage());
        $pdo = null;

        return $pdo;
    }

    return $pdo;
}

/**
 * @param string $query
 * @param string $field
 * @param array  $params
 * @param string $db
 *
 * @return string
 */
function dbQueryField($query, $field, $params = [], $db = null)
{
    $pdo = openDB($db);
    if ($pdo == null) {
        return;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $pdo = null;

    if (count($result) == 0) {
        return;
    }

    $resultRow = $result[0];

    return $resultRow[$field];
}

/**
 * @param string $query
 * @param array  $params
 * @param string $db
 *
 * @return null|void
 */
function dbQueryRow($query, $params = [], $db = null)
{
    $pdo = openDB($db);
    if ($pdo == null) {
        return;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $pdo = null;

    if (count($result) >= 1) {
        return $result[0];
    }
}

/**
 * @param string $query
 * @param array  $params
 * @param string $db
 *
 * @return array|void
 */
function dbQuery($query, $params = [], $db = null)
{
    $pdo = openDB($db);
    if ($pdo == null) {
        return;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    $pdo = null;

    return $result;
}

/**
 * @param string $query
 * @param array  $params
 * @param string $db
 */
function dbExecute($query, $params = [], $db = null)
{
    $pdo = openDB($db);
    if ($pdo == null) {
        return;
    }

    // This is ugly, but, yeah..
    if (stristr($query, ';')) {
        $explodedQuery = explode(';', $query);
        foreach ($explodedQuery as $newQry) {
            $stmt = $pdo->prepare($newQry);
            $stmt->execute($params);
        }
        $stmt->closeCursor();
        $pdo = null;
    } else {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $stmt->closeCursor();
        $pdo = null;
    }
}
