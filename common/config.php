<?php
// Switches
define("PROJECT_NAME", "fastfan");
define("PROJECT_VERSION", "2.0b");

$mnDb = false;

$hostname = gethostname();

if ($hostname == "lumair.local") {
    define('DEPLOYMENT', "scott");
} elseif ($hostname == "zBook"  ||  $hostname == "zbook") {
    define('DEPLOYMENT', "zbook");
} elseif ($hostname == "zBook.local"  ||  $hostname == "zbook.local") {
    define('DEPLOYMENT', "zbook");
} elseif ($hostname == "marco-fastfan") {
    define('DEPLOYMENT', "marco");
} elseif ($hostname == "BenTest") {
    define('DEPLOYMENT', "BenTest");
} else {
    define('DEPLOYMENT', "live");
}

// define('DBASE_NAME', "fastfan_full_feeds");
define('DBASE_NAME', "799180_fastfandemo_2");
define('DBASE_HOST', "localhost");
define('DBASE_PORT', 3306);
define('DBASE_USER', "root");
define('DBASE_PWD', "password");
define("BASE_URL", "http://184.106.240.31/");
define("DEBUG_MODE", true);
define('FF_SESSION_TIMEOUT', "86400");
define('artistService', "demoArtists.php");
define('albumService', "demoAlbums.php");
define('trackService', "demoTracks.php");

// General
if (isset($_SERVER['SERVER_NAME'])) {
    define('DIR_BASE', 'http://' . $_SERVER['SERVER_NAME'] . '/');
    define('DIR_ROOT', 'http://' . $_SERVER['SERVER_NAME'] . '/');
}
?>
