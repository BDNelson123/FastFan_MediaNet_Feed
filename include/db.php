<?php
require_once '../common/config.php';
require_once '../include/functions.php';
require_once "../include/Log-1.12.7/Log.php";

// Include the main Propel script
require_once '../include/propel/runtime/lib/Propel.php';

// Initialize Propel with the runtime configuration
Propel::init("../sql/propel-schema/build/conf/propel-schema-conf.php");

$propelconfig = Propel::getConfiguration(PropelConfiguration::TYPE_OBJECT);
$propelconfig->setParameter("datasources.focus_caps.connection.dsn", "mysql:host=" . DBASE_HOST . ";dbname=" . DBASE_NAME);
$propelconfig->setParameter("datasources.focus_caps.connection.database", DBASE_NAME);
$propelconfig->setParameter("datasources.focus_caps.connection.username", DBASE_USER);
$propelconfig->setParameter("datasources.focus_caps.connection.password", DBASE_PWD);

// Add the generated 'classes' directory to the include path
set_include_path("../sql/propel-schema/build/classes" . PATH_SEPARATOR . get_include_path());
?>
