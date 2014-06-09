<?php
ini_set('display_errors',1); 
error_reporting(E_ALL);
?>


<?php

use Secrets\Secret;

$root = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))));
require $root . "/vendor/autoload.php";

$headers = apache_request_headers();
$secrets = Secret::get("jhu", "production", "plugins", "wp-api-cache-cleaner");

$key = $secrets->key;
$pw = $secrets->password;

if (!isset($headers[$key]) || (isset($headers[$key]) && $headers[$key] !== $pw)) {
  die(1);
}

$endpoint = $_GET["endpoint"];

$info = apc_cache_info("user");

$patterns = array(
  "uri=%2F" . $endpoint,
  "meta_[^=]+=" . $endpoint
);

foreach ($info["cache_list"] as $cachedItem) {
  if (preg_match("/(" . implode("|", $patterns) . ")/", $cachedItem["info"])) {
    apc_delete($cachedItem["info"]);
  }
}

die();
