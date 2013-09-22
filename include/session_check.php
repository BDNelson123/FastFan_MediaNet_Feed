<?php
session_start();

require_once dirname(dirname(__FILE__)).'/common/config.php';


if (isset($_SESSION['timeout_idle']) && $_SESSION['timeout_idle'] < time()) {
    session_destroy();
    session_start();
    $_SESSION = array();
}
$_SESSION['timeout_idle'] = time() + (int)FF_SESSION_TIMEOUT;

if (    ! isset($_SESSION['ff_user'] )
    ||  ! isset($_SESSION['ff_user']["fan_id"] )
    ||  $_SESSION['ff_user']["fan_id"] == '' ) {
    header("Location: index.php");
    exit();
}
?>