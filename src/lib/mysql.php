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
 * @param $url
 * @param $user
 * @param $pass
 * @param $dbName
 * @param $userID
 * @param $characterID
 * @param $eveName
 * @param $type
 * @return null
 */


function insertUser($url, $user, $pass, $dbName, $userID, $characterID, $eveName, $type){
    $host = $url;
    $username = $user;
    $password = $pass;
    $database = $dbName;
    $mysqli = mysqli_connect($host,$username,$password,$database);

    if ($stmt = $mysqli->prepare("REPLACE into authUsers (characterID, discordID, eveName, active, role) values(?,?,?,'yes',?)")) {

        // Bind the variables to the parameter as strings.
        $stmt->bind_param("ssss", $characterID,$userID,$eveName,$type);

        // Execute the statement.
        $stmt->execute();

        // Close the prepared statement.
        $stmt->close();
        return null;
    }
    return null;
}

/**
 * @param $url
 * @param $user
 * @param $pass
 * @param $dbName
 * @param $authCode
 * @return null
 */
function disableReg($url, $user, $pass, $dbName, $authCode){
    $host = $url;
    $username = $user;
    $password = $pass;
    $database = $dbName;
    $mysqli = mysqli_connect($host,$username,$password,$database);

    if ($stmt = $mysqli->prepare("UPDATE pendingUsers SET active='0' WHERE authString= ?")) {

        // Bind the variables to the parameter as strings.
        $stmt->bind_param("s", $authCode);

        // Execute the statement.
        $stmt->execute();

        // Close the prepared statement.
        $stmt->close();
        return null;
    }
    return null;
}

/**
 * @param $url
 * @param $user
 * @param $pass
 * @param $dbName
 * @param $authCode
 * @return bool|null
 */
function selectPending($url, $user, $pass, $dbName, $authCode){
    $host = $url;
    $username = $user;
    $password = $pass;
    $database = $dbName;
    $mysqli = mysqli_connect($host,$username,$password,$database);

    if ($stmt = $mysqli->prepare("SELECT * FROM pendingUsers WHERE authString= ? AND active='1'")) {

        // Bind the variables to the parameter as strings.
        $stmt->bind_param("s", $authCode);

        // Execute the statement.
        $stmt->execute();

        // Return Row
        $result = $stmt->get_result();

        // Close the prepared statement.
        $stmt->close();
        return $result;
    }
    return null;
}