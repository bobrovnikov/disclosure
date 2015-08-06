<?php

$data = array(
    'lastPageSize'           => 10,
    'lastPageNumber'         => 1,
    'query'                  => $_REQUEST['name'],
    'textfield'              => $_REQUEST['name'],
    'radReg'                 => 'FederalDistricts',
    'districtsCheckboxGroup' => -1,
    'regionsCheckboxGroup'   => -1,
    'branchesCheckboxGroup'  => -1,
);

$myCurl = curl_init();
curl_setopt_array($myCurl, array(
    CURLOPT_URL => 'http://e-disclosure.ru/poisk-po-kompaniyam',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($data)
));
$response = curl_exec($myCurl);
curl_close($myCurl);

if (false !== strrpos($response, 'Ничего не найдено')) {
    echo json_encode(array());
    exit;
}

preg_match_all('/company\.aspx\?id=(\d+)" onclick="Go2Card\(\);">(.*)<\/a>/', $response, $companies);

if (!count($companies[0])) {
    echo json_encode(array());
    exit;
}

$output = array();
foreach ($companies[1] as $key => $id) {
    $output[] = array(
        'id' => $id,
        'label' => html_entity_decode($companies[2][$key], ENT_COMPAT, 'UTF-8'),
    );
}

echo json_encode($output);
