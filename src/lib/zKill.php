<?php
function zKillRedis()
{
    $raw = file_get_contents('http://redisq.zkillboard.com/listen.php');
    $json = json_decode($raw, true);
    return $json['package'];
}