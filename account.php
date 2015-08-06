<?php

require 'db_connect.php';

if (!isset($_COOKIE['session'])) {
    header('Location: http://' . $_SERVER['HTTP_HOST']);
    exit;
}

$pass_hash = $mysqli->real_escape_string($_COOKIE['session']);
$getUser = $mysqli->query("SELECT * FROM subscribers WHERE pass_hash = '$pass_hash' LIMIT 1");
if (!$getUser->num_rows) {
    setcookie('session', '', time() - 3600);
    header('Location: http://' . $_SERVER['HTTP_HOST']);
    exit;
}

$user = $getUser->fetch_array(MYSQLI_ASSOC);
$userId = $user['id'];
$mysqli->query("UPDATE subscribers SET date_last_visit = NOW() WHERE id = '$user[id]' LIMIT 1");

if ('POST' === $_SERVER['REQUEST_METHOD']) {
    $uniqueCompanyIds = array();

    if (!empty($_POST['companyId'])) {
        foreach ($_POST['companyId'] as $companyId => $on) {
            if (!in_array($companyId, $uniqueCompanyIds)) {
                $uniqueCompanyIds[] = $companyId;
            }
        }
    } else {
        $mysqli->query("DELETE FROM subscriptions WHERE subscriber_id = '$userId'");
        header('Location: http://' . $_SERVER['HTTP_HOST'] . '/account.php?edited');
        exit;
    }

    // @todo: проверять, на какие компании больше нет подписчиков, чтобы зря их по крону не гонять

    $mysqli->query("DELETE FROM subscriptions WHERE subscriber_id = '$userId'");
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

    header('Location: http://' . $_SERVER['HTTP_HOST'] . '/account.php?edited');
    exit;
}

function getFancyMobile($m) {
    return sprintf('+7 (%s) %s-%s-%s', $m[1].$m[2].$m[3], $m[4].$m[5].$m[6], $m[7].$m[8], $m[9].$m[10]);
}
$mobile = getFancyMobile($user['mobile']);

$getCompaniesIds = $mysqli->query("SELECT * FROM subscriptions WHERE subscriber_id = '$user[id]' ORDER BY id");
$companies = array();
if ($getCompaniesIds->num_rows) {
    while ($subscription = $getCompaniesIds->fetch_array(MYSQLI_ASSOC)) {
        $companyInfo = $mysqli->query("SELECT * FROM companies WHERE id = '$subscription[company_id]' LIMIT 1")->fetch_array(MYSQLI_ASSOC);
        $companies[] = array(
            'id'   => $companyInfo['disclosure_company_id'],
            'name' => stripslashes($companyInfo['name']),
        );
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Раскрытие информации по СМС</title>

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
    <link href="/css/jumbotron-narrow.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

    <link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
    <script src="/js/jquery.maskedinput.min.js"></script>

    <script>
        $(function () {
            var companyNameInput = $('#company_name')
                , companiesButton = $('#companiesButton')
                ;

            companyNameInput.autocomplete({
                source: function (request, response) {
                    $.ajax({
                        url: '/find-company.php',
                        dataType: 'json',
                        data: {
                            name: request.term
                        },
                        success: function (data) {
                            response(data);
                        }
                    });
                },
                minLength: 1,
                select: function (event, ui) {
                    $('#companies').append(
                        '<div class="checkbox"><label>' +
                        '<input type="checkbox" name="companyId[' + ui.item.id + ']" data-id="' + ui.item.id + '" checked> <span class="js-company-name-for-id' + ui.item.id + '">' + ui.item.label + '</span>' +
                        '</label></div>'
                    );
                    window.setTimeout(function () {
                        companyNameInput.val('');
                    }, 50); // @todo: make event driven
                },
                open: function () {
                    $(this).removeClass("ui-corner-all").addClass("ui-corner-top");
                },
                close: function () {
                    $(this).removeClass("ui-corner-top").addClass("ui-corner-all");
                }
            });

            companiesButton.on('click', function (e) {
                e.preventDefault();
                $('#companies input:checked').each(function () {
                    var companyId = $(this).data('id');
                    var companyName = $('.js-company-name-for-id' + companyId).html();
                    $('#edit_subscriptions_form').append($('<input/>', {
                        type: 'hidden',
                        name: 'company_name_for_id' + companyId,
                        value: companyName
                    }));
                });
                $('#edit_subscriptions_form').submit();
            });
        });
    </script>

    <style>
        .ui-autocomplete-loading {
            background: white url('/images/ui-anim_basic_16x16.gif') right center no-repeat;
        }

        .ui-loading-animation {
            background: transparent url('/images/ui-anim_basic_16x16.gif') right center no-repeat;
            display: inline-block;
            width: 16px;
            height: 16px;
            vertical-align: middle;
            margin-left: 10px;
        }
    </style>
</head>

<body>
<div class="container">
    <div class="header clearfix">
        <nav>
            <ul class="nav nav-pills pull-right">
                <li><a href="#" style="pointer-events: none; color: gray"><?php echo $mobile ?></a></li>
                <li><a href="/logout.php">Выйти</a></li>
            </ul>
        </nav>
        <h3 class="text-muted">Раскрытие информации по СМС</h3>
    </div>

    <?php if (isset($_GET['subscribed'])): ?>
        <div class="alert alert-success"><strong>Успех!</strong> Вы теперь будете получать СМС от интересующих эмитентов.</div>
    <?php endif ?>

    <?php if (isset($_GET['edited'])): ?>
        <div class="alert alert-info">Настройки подписки успешно обновлены.</div>
    <?php endif ?>

    <form action="" method="post" id="edit_subscriptions_form">
        <div id="choose_companies">
            <h4>Ваши подписки</h4>
            <?php if (!$getCompaniesIds->num_rows): ?>
                <div class="alert alert-warning">Вы не подписаны ни на одну компанию. Воспользуйтесь поиском.</div>
            <?php endif ?>
            <div class="form-group">
                <label for="company_name">Название компании</label>
                <input type="text" name="company_name" id="company_name" class="form-control" placeholder="Начните печатать…" autofocus>
            </div>
            <div id="companies">
                <?php if (!empty($companies)): ?>
                    <?php foreach ($companies as $company): ?>
                        <?php if (empty($company['name'])) $company['name'] = '<em>обрабатывается...</em>' ?>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="companyId[<?php echo $company['id'] ?>]" data-id="<?php echo $company['id'] ?>" checked>
                                <span class="js-company-name-for-id<?php echo $company['id'] ?>"><?php echo $company['name'] ?></span>
                            </label>
                        </div>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
            <div class="form-group">
                <button type="submit" id="companiesButton" class="btn btn-info">Сохранить</button>
            </div>
        </div>
    </form>

    <footer class="footer">
        <p>&copy; 2015</p>
    </footer>

</div>

<!-- Yandex.Metrika counter -->
<script type="text/javascript">(function (d, w, c) { (w[c] = w[c] || []).push(function() { try { w.yaCounter30036844 = new Ya.Metrika({id:30036844, webvisor:true, clickmap:true, trackLinks:true, accurateTrackBounce:true}); } catch(e) { } }); var n = d.getElementsByTagName("script")[0], s = d.createElement("script"), f = function () { n.parentNode.insertBefore(s, n); }; s.type = "text/javascript"; s.async = true; s.src = (d.location.protocol == "https:" ? "https:" : "http:") + "//mc.yandex.ru/metrika/watch.js"; if (w.opera == "[object Opera]") { d.addEventListener("DOMContentLoaded", f, false); } else { f(); } })(document, window, "yandex_metrika_callbacks");</script><noscript><div><img src="//mc.yandex.ru/watch/30036844" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->

</body>
</html>
