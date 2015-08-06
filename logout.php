<?php

setcookie('session', '', time() - 3600);
header('Location: http://' . $_SERVER['HTTP_HOST']);
exit;
