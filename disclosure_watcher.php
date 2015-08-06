<?php

require 'config.php';
require 'db_connect.php';
require 'GoogleUrlApi.php';

function sendMsgToSubscriber($msg, $subscriberId) {
    global $mysqli;
    $getSubscriber = $mysqli->query("SELECT * FROM subscribers WHERE id = '$subscriberId' LIMIT 1")->fetch_assoc();
    $mobile = $getSubscriber['mobile'];
    $response = $mysqli->real_escape_string(
        file_get_contents(
            'http://sms.ru/sms/send?' . http_build_query(array(
            'api_id' => SMS_RU_API_ID,
            'to' => $mobile,
            'text' => $msg,
        )))
    );
    $mysqli->query(sprintf("INSERT INTO messages (to_mobile, text, datetime, response) VALUES ('%s', '%s', NOW(), '%s')",
        $mysqli->real_escape_string($mobile), $mysqli->real_escape_string($msg), $mysqli->real_escape_string($response)));
}

$allCompanies = $mysqli->query('SELECT * FROM companies ORDER BY last_check_date');

while ($company = $allCompanies->fetch_assoc()) {
    $mysqli->query("UPDATE companies SET last_check_date = NOW() WHERE id = '$company[id]' LIMIT 1");
    $page = file_get_contents('http://e-disclosure.ru/portal/company.aspx?id=' . $company['disclosure_company_id']);

    if (is_null($company['last_message_id'])) {
        preg_match('/<h2>(.*)<\/h2>/', $page, $name_matches);
        $name = $mysqli->real_escape_string(html_entity_decode($name_matches[1]));

        preg_match('/href="http:\/\/e-disclosure\.ru\/portal\/event\.aspx\?EventId=(.*)" s/', $page, $lastEvent);
        $lastEventId = $mysqli->real_escape_string($lastEvent[1]);

        $update = $mysqli->query("UPDATE companies SET name = '$name', last_message_id = '$lastEventId' WHERE id = '$company[id]' LIMIT 1");
        if (!$update) {
            exit('Что-то не так с первичной обработкой компании: ' . $mysqli->error);
        }

        continue;
    }

    preg_match_all('/href="http:\/\/e-disclosure\.ru\/portal\/event\.aspx\?EventId=(.*)" s/', $page, $events);

    if ($events[1][0] === $company['last_message_id']) { // новых сообщений не добавилось
        continue;
    }

    // @todo: предусмотреть, что за одну минуту могло добавится несколько сообщений одной компании, тогда в хранологическом порядке их рассылать
    preg_match('/href="http:\/\/e-disclosure\.ru\/portal\/event\.aspx\?EventId=(.*)" style="(.*)" >(.*)<\/a>/', $page, $lastEvent);
    $update = $mysqli->query("UPDATE companies SET last_message_id = '$lastEvent[1]' WHERE id = '$company[id]' LIMIT 1");

    $longUrl = 'http://e-disclosure.ru/portal/event.aspx?EventId=' . $lastEvent[1];
    $shortener = new GoogleUrlApi(GOOGLE_API_KEY);
    $shortUrl = $shortener->shorten($longUrl);

    $getSubscribers = $mysqli->query("SELECT * FROM subscriptions WHERE company_id = '$company[id]'");
    if ($getSubscribers->num_rows) {
        $companyName = empty($company['custom_name']) ? $company['name'] : $company['custom_name'];
        $text = sprintf('%s: %s %s', $companyName, $lastEvent[3], $shortUrl);
        while ($subscriber = $getSubscribers->fetch_assoc()) {
            sendMsgToSubscriber($text, $subscriber['subscriber_id']);
        }
    }

}
