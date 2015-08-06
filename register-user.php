<?php

require 'config.php';
require 'db_connect.php';

if ('POST' === $_SERVER['REQUEST_METHOD']) {
    $mobile = $_POST['mobile'];
    $normalizedMobile = $mysqli->real_escape_string('7' . str_replace(array('(', ')', ' ', '-'), '', $mobile));

    $getUser = $mysqli->query("SELECT * FROM subscribers WHERE mobile = '$normalizedMobile' LIMIT 1");

    if ($getUser->num_rows) {
        $user = $getUser->fetch_array(MYSQLI_ASSOC);
        $userId = $user['id'];
        echo json_encode(array(
            'error' => 'user already exists',
        ));
        exit;
    }

    function generatePassword($iChars = 8, $iComplexity = 1) {
        $a = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');

        if ($iComplexity > 1) {
            $a = array_merge($a, array( 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U', 'V', 'X', 'Y', 'Z', ));
        }

        if ($iComplexity > 2) {
            $a = array_merge($a, array( '.', ',', '(', ')', '[', ']', '!', '?', '&', '^', '%', '@', '*', '$', '<', '>', '/', '|', '+', '-', '{', '}', '`', '~', ));
        }

        for( $i = 0, $s = '', $iCount = count($a) - 1; $i < $iChars; $i++ ) {
            $s .= $a[ rand(0, $iCount) ];
        }

        return $s;
    }

    $options = array(
        'cost' => 12,
    );

    $user_pass = generatePassword();
    $user_hash = md5(microtime());
    $pass_hash = md5(APP_SALT . $user_hash . $user_pass);

    $createUser = $mysqli->query("INSERT INTO subscribers (mobile, date_registered, user_hash, pass_hash)
                                                   VALUES ('$normalizedMobile', NOW(), '$user_hash', '$pass_hash')");
    $userId = $mysqli->insert_id;

    $response = $mysqli->real_escape_string(
        file_get_contents(
            'http://sms.ru/sms/send?' . http_build_query(array(
                'api_id' => SMS_RU_API_ID,
                'to' => $normalizedMobile,
                'text' => 'Ваш пароль: ' . $user_pass,
            )))
    );

    echo json_encode(array(
        'status' => 'ok',
        'mobile' => '+7 ' . $mobile,
    ));
}
