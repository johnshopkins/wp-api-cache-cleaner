<?php

ini_set('display_errors',1);
error_reporting(E_ALL);

$root = dirname(dirname(dirname(dirname(dirname(__DIR__)))));

// load wordpress for $jhu_cacher and ENV
define("WP_USE_THEMES", false);
require $root . "/vendor/autoload.php";
require $root . "/vendor/wordpress/wordpress/wp-blog-header.php";

// validate request
$headers = apache_request_headers();
$validator = new \CacheCleaner\Utilities\Validator($headers);

if (!$validator->validate()) {
  echo "validator failed.<br>";
  die(1);
} else {
  echo "validator passed<br>.";
}

$cleaner = new \CacheCleaner\Utilities\CacheCleaner($jhu_cacher);

if (!empty($_GET["id"])) {
  $clearedIds = $cleaner->clearObjectCache($_GET["id"]);
  $clearedEndpoints = $cleaner->clearFoundEndpoints();
  $logs = $cleaner->logs;
}

if (!empty($_GET["endpoint"])) {
  $cleaner->clearEndpointCache($_GET["endpoint"]);
  $logs = $cleaner->logs;
}

// echo out the logs
echo json_encode($logs);
die();
