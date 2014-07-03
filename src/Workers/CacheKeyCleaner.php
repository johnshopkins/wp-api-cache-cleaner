<?php

namespace CacheCleaner\Workers;
use Secrets\Secret;

class CacheKeyCleaner
{
  protected $api;
  protected $cacheList;

  public function __construct($deps = array())
  {
    $this->api = isset($deps["api"]) ? $deps["api"] : new \WPUtilities\API();

    $cacheInfo = apc_cache_info("user");
    $this->cacheList = $cacheInfo["cache_list"];

  }

  public function clearObjectCache($id)
  {
    $pattern = "/uri=%2F" . $id . "/";

    $parents = array();

    foreach ($this->cacheList as $item) {

      $rawKey = $item["info"];
      if (!preg_match("/source=wpapi/", $rawKey)) continue;
      parse_str($rawKey, $parsedKey);

      if (preg_match($pattern, $rawKey)) {
        // this object's cache
        $this->clearCache($parsedKey);
        continue;

      } else if (isset($parsedKey["parent_of"]) && $parsedKey["parent_of"] == $id) {
        // find this object's parent caches
        $parents = apc_fetch($rawKey);
      }
    }

    // clear parent cache
    
    foreach ($parents["objects"] as $id) {
      $id = trim($id, "/");
      $this->clearObjectCache($id);
    }

    foreach ($parents["endpoints"] as $endpoint) {
      $endpoint = trim($endpoint, "/");
      $this->clearEndpointCache($endpoint);
    }

  }

  protected function clearEndpointCache($endpoint)
  {
    $pattern = "/uri=%2F" . urlencode($endpoint) . "/";

    foreach ($this->cacheList as $item) {

      $rawKey = $item["info"];
      if (!preg_match("/source=wpapi/", $rawKey)) continue;
      parse_str($rawKey, $parsedKey);

      if (preg_match($pattern, $rawKey)) {
        $this->clearCache($parsedKey);
        continue;

      }
    }
  }

  protected function clearCache($parsedKey)
  {
    $this->count++;
    $uri = $parsedKey["uri"];

    // get rid of non-query string items
    unset($parsedKey["uri"]);
    unset($parsedKey["stitched"]);
    unset($parsedKey["source"]);

    // Must use this so that all subobjects' cache is also cleared
    // $parsedKey["clear_cache"] = true;

    $query_string = http_build_query($parsedKey);

    echo "Clearing {$uri}?{$query_string}\n";

    // $this->api->get($uri, $query_string);
  }

}
