<?php

ini_set('display_errors',1);
error_reporting(E_ALL);

$root = dirname(dirname(dirname(dirname(dirname(__DIR__)))));

// load wordpress for $jhu_cacher and ENV
define("WP_USE_THEMES", false);
require $root . "/vendor/autoload.php";
require $root . "/vendor/wordpress/wordpress/wp-blog-header.php";

// validate request
$validator = new \CacheCleaner\Utilities\Validator($_GET);

if (!$validator->validate()) die(1);

$cleaner = new \CacheCleaner\Utilities\CacheCleaner($jhu_cacher);

$id = isset($_GET["id"]) ? $_GET["id"] : null;
$endpoints = isset($_GET["endpoint"]) ? $_GET["endpoint"] : null;


if ($id) {
  $clearedIds = $cleaner->clearObjectCache($_GET["id"]);
  $clearedEndpoints = $cleaner->clearFoundEndpoints();
}

if ($endpoints) {
  $endpoints = explode(",", $endpoints);
  foreach ($endpoints as $endpoint) {
    $cleaner->clearEndpointCache($endpoint);
  }
}

// echo out the logs
echo json_encode($cleaner->logs);
die();
