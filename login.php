<?php

require 'config.php';
require 'db_connect.php';

if (isset($_COOKIE['session'])) {
    $pass_hash = $mysqli->real_escape_string($_COOKIE['session']);
    if ($mysqli->query("SELECT * FROM subscribers WHERE pass_hash = '$pass_hash' LIMIT 1")->num_rows) {
        header('Location: http://' . $_SERVER['HTTP_HOST'] . '/account.php');
        exit;
    }
}

$error = false;
if ('POST' === $_SERVER['REQUEST_METHOD']) {
    $normalizedMobile = $mysqli->real_escape_string('7' . str_replace(array('(', ')', ' ', '-'), '', $_POST['mobile']));

    $getUser = $mysqli->query("SELECT * FROM subscribers WHERE mobile = '$normalizedMobile' LIMIT 1");
    if (!$getUser->num_rows) {
        $error = 'Такой номер не зарегистрирован в системе. <a href="/">Перейти к регистрации</a>';
    }

    if (!$error) {
        $user = $getUser->fetch_array(MYSQLI_ASSOC);

        if (md5(APP_SALT . $user['user_hash'] . $_POST['password']) !== $user['pass_hash']) {
            $error = 'Неверный пароль.';
        }

        if (!$error) {
            setcookie('session', $user['pass_hash'], time() + 3600 * 24 * 365);
            header('Location: http://' . $_SERVER['HTTP_HOST'] . '/account.php');
        }
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
            $('#mobile_input').mask('(999) 999-99-99', {
                completed: function () {
                    $('#mobile_form_group').addClass('has-success');
                    $('#get_password_button').prop('disabled', false);
                }
            });
        });
    </script>
</head>

<body>
<div class="container">
    <div class="header clearfix">
        <nav>
            <ul class="nav nav-pills pull-right">
                <li class="active"><a href="/login.php"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span> Войти</a></li>
            </ul>
        </nav>
        <h3 class="text-muted"><a href="/">Раскрытие информации по СМС</a></h3>
    </div>

    <form class="form-horizontal" action="" method="post">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error ?></div>
        <?php endif ?>
        <div class="form-group">
            <label for="mobile_input" class="col-sm-2 control-label">Номер</label>
            <div class="input-group col-sm-6">
                <span class="input-group-addon" id="basic-addon1" title="Доступны только российские номера">+7</span>
                <input type="text" class="form-control" id="mobile_input" name="mobile" aria-describedby="basic-addon1">
            </div>
        </div>
        <div class="form-group">
            <label for="password" class="col-sm-2 control-label">Пароль</label>
            <div class="input-group col-sm-6">
                <input type="password" class="form-control" id="password" name="password">
            </div>
        </div>
        <div class="form-group">
            <div class="col-sm-offset-7 col-sm-6">
                <button type="submit" class="btn btn-default">Войти</button>
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
