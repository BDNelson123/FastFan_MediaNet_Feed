<?php
require_once(__DIR__.'/config.php');
require_once '../include/functions.php';
require_once 'zebra/Zebra_Database.php';

// create a new database wrapper object
global $db;
$db = new Zebra_Database();

// turn debugging on
$db->debug = DEBUG_MODE;
        
$db->connect( DBASE_HOST, // host
	DBASE_USER, // user name
	DBASE_PWD, // password 
	DBASE_NAME  // database
);

function validateGids( $gids ) {
    // we can't use zebra's ? replacement for IN clause so we have to validate for ourselves that the gids are a comma separated list of digits
    $gidArray = explode( ',', $gids );
    $allowedGids = array();
    foreach( $gidArray as $gid ) {
        if ( is_numeric( $gid ) ) {
            array_push( $allowedGids, $gid );
        }
    }
    $allowedGidList = implode( ',', $allowedGids );
    return $allowedGidList;
}
?>
