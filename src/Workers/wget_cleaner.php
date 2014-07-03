<?php
ini_set('display_errors',1); 
error_reporting(E_ALL);
?>

<?php

// autoload stuff
$root = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))));
define("WP_USE_THEMES", false);
require $root . "/vendor/autoload.php";
require $root . "/vendor/wordpress/wordpress/wp-blog-header.php"; // we need this for the ENV

// validate request
$headers = apache_request_headers();
$validator = new \CacheCleaner\Workers\ClearCacheValidator($headers);
if (!$validator->validate()) die(1);

$cleaner = new \CacheCleaner\Workers\CacheKeyCleaner();

if (!empty($_GET["endpoint"])) {
  $cleaner->clearEndpointCache($_GET["endpoint"]);
}

if (!empty($_GET["id"])) {
  $cleaner->clearObjectCache($_GET["id"]);
}

die();
