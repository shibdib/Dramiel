<?php
function pasteLog($pasteText, $pasteName)
{
    $api_dev_key = 'b76a9a39acaa8d9b2cec417a0f3a822a'; // Dramiel bot devs account
    $api_paste_code = $pasteText; // your paste text
    $api_paste_private = '0'; // 0=public 1=unlisted 2=private
    $api_paste_name = $pasteName; // name or title of your paste
    $api_paste_expire_date = '10M';
    $api_paste_format = 'php';
    $api_user_key = ''; // if an invalid api_user_key or no key is used, the paste will be create as a guest
    $api_paste_name = urlencode($api_paste_name);
    $api_paste_code = urlencode($api_paste_code);


    $url = 'http://pastebin.com/api/api_post.php';
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'api_option=paste&api_user_key=' . $api_user_key . '&api_paste_private=' . $api_paste_private . '&api_paste_name=' . $api_paste_name . '&api_paste_expire_date=' . $api_paste_expire_date . '&api_paste_format=' . $api_paste_format . '&api_dev_key=' . $api_dev_key . '&api_paste_code=' . $api_paste_code . '');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_NOBODY, 0);

    $response = curl_exec($ch);
    return $response;
}