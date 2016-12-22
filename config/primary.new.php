<?php
$primary = array();

$primary['database'] = array(
    'host' => 'localhost',
    'user' => '',
    'pass' => '',
    'database' => 'discord'
);

$primary['bot'] = array(
    'token' => '', //enter the token for your app (https://discordapp.com/developers/applications/me)
);

$primary['plugins'] = array(
    'fileReader' => array(
        'db' => '/../../jabberPings.db'
    ),
    'auth' => array(
        'url' => 'http://...'
    )
);