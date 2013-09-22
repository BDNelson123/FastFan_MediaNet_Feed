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

    // TODO handle ajax session timeout

    $response['success'] = false;
    $response['data'] = "";
    $response['error'] = "ajax session timeout";

    $response =  json_encode($response);

    header('Content-Type: application/json; charset=utf8');
    echo $response;
    exit();
}

$currentFanId = $_SESSION['ff_user']["fan_id"];

?>