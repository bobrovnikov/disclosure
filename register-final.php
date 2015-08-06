<?php

require 'config.php';
require 'db_connect.php';

if ('POST' === $_SERVER['REQUEST_METHOD']) {
    $mobile = $_POST['mobile'];
    $normalizedMobile = $mysqli->real_escape_string('7' . str_replace(array('(', ')', ' ', '-'), '', $mobile));

    $getUser = $mysqli->query("SELECT * FROM subscribers WHERE mobile = '$normalizedMobile' LIMIT 1");

    if (!$getUser->num_rows) {
        echo json_encode(array(
            'error' => 'no such user',
        ));
        exit;
    }

    $user = $getUser->fetch_array(MYSQLI_ASSOC);
    $userId = $user['id'];

    if (md5(APP_SALT . $user['user_hash'] . $_POST['sms_code']) !== $user['pass_hash']) {
        echo json_encode(array(
            'error' => 'wrong code',
        ));
        exit;
    }

    $mysqli->query("UPDATE subscribers SET is_confirmed = '1', date_confirmed = NOW() WHERE id = '$user[id]' LIMIT 1");


    $uniqueCompanyIds = array();

    foreach ($_POST['companyId'] as $companyId => $on) {
        if (!in_array($companyId, $uniqueCompanyIds)) {
            $uniqueCompanyIds[] = $companyId;
        }
    }

    foreach ($uniqueCompanyIds as $companyId) {
        $disclosureId = $mysqli->real_escape_string($companyId);
        $companyExists = $mysqli->query("SELECT * FROM companies WHERE disclosure_company_id = '$disclosureId' LIMIT 1");
        if ($companyExists->num_rows) {
            $company = $companyExists->fetch_array(MYSQL_ASSOC);
            $dbCompanyId = $company['id'];
        } else {
            if (isset($_POST['company_name_for_id' . $disclosureId]) && strlen($_POST['company_name_for_id' . $disclosureId])) {
                $companyName = $mysqli->real_escape_string(html_entity_decode($_POST['company_name_for_id' . $disclosureId]));
                $createCompany = $mysqli->query("INSERT INTO companies (name, disclosure_company_id)
                                                                VALUES ('$companyName', '$disclosureId')");
            } else {
                $createCompany = $mysqli->query("INSERT INTO companies (disclosure_company_id) VALUES ('$disclosureId')");
            }
            $dbCompanyId = $mysqli->insert_id;
        }

        if (!$mysqli->query("SELECT * FROM subscriptions WHERE subscriber_id = '$userId' AND company_id = '$dbCompanyId' LIMIT 1")->num_rows) {
            $createSubscription = $mysqli->query("INSERT INTO subscriptions (subscriber_id, company_id) VALUES ('$userId', '$dbCompanyId')");
            if (!$createSubscription) {
                exit('Не удалось создать подписку: ' . $mysqli->error);
            }
        }
    }

    setcookie('session', $user['pass_hash'], time() + 3600 * 24 * 356);
    setcookie('was_registered', 'true', time() + 3600 * 24 * 1500);

    echo json_encode(array(
        'status' => 'ok',
        'location' => 'http://' . $_SERVER['HTTP_HOST'] . '/account.php?subscribed',
    ));
}
