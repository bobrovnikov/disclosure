<?php

require 'db_connect.php';

if (isset($_COOKIE['session'])) {
    $pass_hash = $mysqli->real_escape_string($_COOKIE['session']);
    if ($mysqli->query("SELECT * FROM subscribers WHERE pass_hash = '$pass_hash' LIMIT 1")->num_rows) {
        header('Location: http://' . $_SERVER['HTTP_HOST'] . '/account.php');
        exit;
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
        var baseUrl = '<?php echo $_SERVER['HTTP_HOST'] ?>';

        function toggleCompaniesButton() {
            if ($('#companies input:checked').length) {
                $('#companiesButton').prop('disabled', false);
            } else {
                $('#companiesButton').prop('disabled', true);
            }
        }

        function bindCheckboxes() {
            $('#companies input').on('change', function () {
                toggleCompaniesButton();
            });
        }

        $(function () {
            var companyNameInput = $('#company_name')
                , companiesButton = $('#companiesButton')
                , mobileInput = $('#mobile_input')
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
                    bindCheckboxes();
                    toggleCompaniesButton();
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
                $('#choose_companies').slideUp();
                $('#mobile_form').slideDown(function () {
                    $('#mobile_input').focus();
                });
                $('#companies input:checked').each(function () {
                    var companyId = $(this).data('id');
                    var companyName = $('.js-company-name-for-id' + companyId).html();
                    $('#registration_form').append($('<input/>', {
                        type: 'hidden',
                        name: 'company_name_for_id' + companyId,
                        value: companyName
                    }));
                });
            });

            mobileInput.mask('(999) 999-99-99', {
                completed: function () {
                    $('#mobile_form_group').addClass('has-success');
                    $('#get_password_button').prop('disabled', false);
                }
            });

            function isMobileValid() {
                return /\(\d{3}\) \d{3}\-\d{2}\-\d{2}/.test(mobileInput.val());
            }

            mobileInput.on('keydown', function (e) {
                if (13 === e.which) {
                    $('#get_password_button').click();
                    return;
                }

                $('#get_password_button').prop('disabled', !isMobileValid());
            });

            $('#get_password_button').on('click', function (e) {
                e.preventDefault();

                if (!isMobileValid()) {
                    $('#alert_mobile_invalid').slideDown();
                    mobileInput.focus();
                    return;
                }

                $.ajax({
                    url: '/register-user.php',
                    data: { mobile: mobileInput.val() },
                    dataType: 'json',
                    method: 'post',
                    beforeSend: function () {
                        $('.js-mobile-alert').hide();
                        $('#get_password_button').prop('disabled', true);
                        $('#get_password_button_loading').show();
                    },
                    success: function (data) {
                        $('#get_password_button').prop('disabled', false);
                        $('#get_password_button_loading').hide();

                        if ('user already exists' === data.error) {
                            $('#alert_already_exists').slideDown();
                            return;
                        }

                        if ('ok' !== data.status) {
                            $('#alert_misc_error').slideDown();
                            return;
                        }

                        $('#sent_to_mobile').text(data.mobile);
                        $('#mobile_form').slideUp();
                        $('#sms_code_form').slideDown(function () {
                            $('#sms_code_input').focus();
                        });
                    }
                });
            });

            $('#sms_code_input').on('keyup', function (e) {
                if (13 === e.which) {
                    $('#input_password_button').click();
                    return;
                }

                $('#input_password_button').prop('disabled', !(8 === $(this).val().length));
            });

            $('#input_password_button').on('click', function () {
                $.ajax({
                    url: '/register-final.php',
                    data: $('#registration_form').serialize(),
                    dataType: 'json',
                    method: 'post',
                    beforeSend: function () {
                        $('.js-password-alert').hide();
                        $('#input_password_button').prop('disabled', true);
                        $('#input_password_button_loading').show();
                    },
                    success: function (data) {
                        $('#input_password_button').prop('disabled', false);
                        $('#input_password_button_loading').hide();

                        if ('wrong code' === data.error) {
                            $('#alert_pass_error').slideDown();
                            $('#sms_code_input').val('').focus();
                            return;
                        }

                        if ('ok' !== data.status) {
                            $('#alert_pass_misc_error').slideDown();
                            return;
                        }

                        window.location = data.location;
                    }
                });
            });

            bindCheckboxes();
            toggleCompaniesButton();
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

        #mobile_form_group .glyphicon-ok {
            display: none;
        }

        #mobile_form_group.has-success .glyphicon-ok {
            display: block;
        }
    </style>

</head>

<body>

<div class="container">
    <div class="header clearfix">
        <nav>
            <ul class="nav nav-pills pull-right">
                <li><a href="/login.php"><span class="glyphicon glyphicon-lock" aria-hidden="true"></span> Войти</a></li>
            </ul>
        </nav>
        <h3 class="text-muted">Раскрытие информации по СМС</h3>
    </div>

    <?php if (isset($_GET['subscribed'])): ?>
        <div class="alert alert-success"><strong>Успех!</strong> Вы теперь будете получать СМС от интересующих эмитентов.</div>
    <?php endif ?>

    <form action="" method="post" id="registration_form">
        <div id="choose_companies">
            Выберите эмитентов
            <div id="companies">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="companyId[934]" data-id="934"> <span class="js-company-name-for-id934">ОАО «Газпром»</span>
                    </label>
                </div>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="companyId[1942]" data-id="1942"> <span class="js-company-name-for-id1942">ОАО «Мечел»</span>
                    </label>
                </div>
            </div>

            или найдите по названию среди 34 000 компаний.
            <div class="form-group" style="padding-top: 15px">
                <label for="company_name">Название компании</label>
                <input type="text" name="company_name" id="company_name" class="form-control" placeholder="Начните печатать…" autofocus>
            </div>

            <div class="form-group">
                <button type="button" id="companiesButton" class="btn btn-info" disabled>Подписаться на новости эмитентов</button>
            </div>
        </div>

        <div id="mobile_form" style="display: none;">
            <div class="alert alert-warning js-mobile-alert" id="alert_mobile_invalid" style="display:none">Недопустимый формат телефона.</div>
            <div class="alert alert-warning js-mobile-alert" id="alert_already_exists" style="display:none">Такой номер уже зарегистрирован. <a href="/login.php">Войти</a></div>
            <div class="alert alert-danger js-mobile-alert" id="alert_misc_error" style="display:none">Произошла неизвестная ошибка. Попробуйте снова.</div>
            <div class="form-group" id="mobile_form_group">
                <label for="mobile_input">Номер телефона</label>
                <div class="input-group">
                    <span class="input-group-addon" id="basic-addon1" title="Доступны только российские номера">+7</span>
                    <input type="text" class="form-control" id="mobile_input" name="mobile" aria-describedby="basic-addon1">
                    <span class="glyphicon glyphicon-ok form-control-feedback"></span>
                </div>
            </div>

            <div class="form-group">
                <button type="button" id="get_password_button" class="btn btn-info" disabled>Получить пароль</button>
                <i class="ui-loading-animation" id="get_password_button_loading" style="display: none;"></i>
            </div>
        </div>

        <div id="sms_code_form" style="display: none">
            <div class="alert alert-info">На номер <strong id="sent_to_mobile"></strong> был отправлен пароль для входа.</div>
            <div class="alert alert-danger js-password-alert" id="alert_pass_error" style="display:none">Неверный пароль.</div>
            <div class="alert alert-danger js-password-alert" id="alert_pass_misc_error" style="display:none">Произошла неизвестная ошибка. Попробуйте снова.</div>
            <div class="form-group">
                <label for="sms_code_input">Пароль</label>
                <input type="password" name="sms_code" class="form-control" id="sms_code_input" placeholder="Введите пароль">
            </div>

            <div class="form-group">
                <button type="button" id="input_password_button" class="btn btn-success" disabled>Завершить регистрацию</button>
                <i class="ui-loading-animation" id="input_password_button_loading" style="display: none;"></i>
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
