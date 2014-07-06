<?php

namespace CacheCleaner\Workers;
use Secrets\Secret;

class CacheKeyCleaner
{
  protected $api;
  protected $cacheList;
  public $logs = array();

  public function __construct($deps = array())
  {
    $this->api = isset($deps["api"]) ? $deps["api"] : new \WPUtilities\API();

    $cacheInfo = apc_cache_info("user");
    $this->cacheList = $cacheInfo["cache_list"];

  }

  public function clearObjectCache($id)
  {
    $this->logs[] = "Clearing object cache for: {$id}";

    $pattern = "/uri=%2F" . $id . "/";

    $parents = array();

    $foundObject = false;
    $foundParents = false;

    foreach ($this->cacheList as $item) {

      if ($foundObject && $foundParents) break;

      $rawKey = $item["info"];
      if (!preg_match("/source=wpapi/", $rawKey)) continue;
      parse_str($rawKey, $parsedKey);

      if (preg_match($pattern, $rawKey)) {
        // this object's cache
        $this->clearCache($parsedKey);
        $fountObject = true;
        continue;

      } else if (isset($parsedKey["parent_of"]) && $parsedKey["parent_of"] == $id) {
        // find this object's parent caches
        $parents = apc_fetch($rawKey);
        $foundParents = true;
      }

    }

    if (!$parents) return;

    // clear parent cache
    
    foreach ($parents["objects"] as $id) {
      $id = trim($id, "/");
      $this->clearObjectCache($id);
    }

    foreach ($parents["endpoints"] as $endpoint) {
      $endpoint = trim($endpoint, "/");
      $this->clearEndpointCache($endpoint);
    }

    return $this->logs;

  }

  protected function clearEndpointCache($endpoint)
  {
    $this->logs[] = "Clearing endpoint cache for: {$endpoint}";

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

    return $this->logs;
  }

  protected function clearCache($parsedKey)
  {
    $this->count++;
    $uri = $parsedKey["uri"];

    // get rid of non-query string items
    unset($parsedKey["uri"]);
    unset($parsedKey["stitched"]);
    unset($parsedKey["source"]);

    $parsedKey["clear_cache"] = true;

    $query_string = http_build_query($parsedKey);

    $this->logs[] = "Clearing {$uri}?{$query_string}\n";

    $this->api->get($uri, $query_string);
  }

}
