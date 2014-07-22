<?php

namespace CacheCleaner\Utilities;
use Secrets\Secret;

/**
 * The utility called by wget_cleaner.php that
 * does the API endpoint repriming.
 */
class CacheCleaner
{
  protected $cache;
  protected $cacheKeys = array();

  protected $api;
  protected $endpointsToclear = array();

  /**
   * Keep track of IDs that have already been cleared to
   * prevent recurssion from IDs that refrence each other.
   * @var array
   */
  protected $clearedIds = array();

  public $logs = array();

  public function __construct($cache, $deps = array())
  {
    $this->cache = $cache;
    $this->cacheKeys = $this->cache->getKeys();
    $this->api = isset($deps["api"]) ? $deps["api"] : new \WPUtilities\API();
  }

  public function clearObjectCache($id)
  {
    if (in_array($id, $this->clearedIds)) return;

    $this->logs[] = "Clearing object cache for: {$id}";

    $pattern = "/uri=%2F" . $id . "/";

    $parents = array();

    /**
     * To clear an object, we need to find its key and the key that
     * keeps track of its parents. After we find those, both of these
     * variables will be true and we can break out of the foreach loop.
     */
    $foundObject = false;
    $foundParents = false;

    foreach ($this->cacheKeys as $rawKey) {

      if ($foundObject && $foundParents) break;

      if (!preg_match("/source=wpapi/", $rawKey)) continue;
      parse_str($rawKey, $parsedKey);

      if (preg_match($pattern, $rawKey)) {
        // this object's cache
        $this->clearCache($parsedKey);
        $fountObject = true;
        $this->clearedIds[] = $id;
        continue;

      } else if (isset($parsedKey["parent_of"]) && $parsedKey["parent_of"] == $id) {
        // find this object's parent caches
        $parents = $this->cache->fetch($rawKey, false);
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
      if (!in_array($endpoint, $this->endpointsToclear)) {
        $this->endpointsToclear[] = $endpoint;
      }
    }

    return $this->logs;

  }

  public function clearEndpointCache($endpoint)
  {
    $this->logs[] = "Clearing endpoint cache for: {$endpoint}";

    $pattern = "/uri=%2F" . urlencode($endpoint) . "/";

    foreach ($this->cacheKeys as $rawKey) {

      if (!preg_match("/source=wpapi/", $rawKey)) continue;
      parse_str($rawKey, $parsedKey);

      if (preg_match($pattern, $rawKey)) {
        $this->clearCache($parsedKey);
        continue;

      }
    }

    return $this->logs;
  }

  public function clearFoundEndpoints()
  {
    foreach ($this->endpointsToclear as $endpoint) {
      $this->clearEndpointCache($endpoint);
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

    $parsedKey["clear_cache"] = true;

    // convert arrays to comma-separated lists
    $params = array_map(function ($param) {
      return is_array($param) ? implode(",", $param) : $param;
    }, $parsedKey);

    $query_string = http_build_query($params);

    $this->logs[] = "Clearing {$uri}?{$query_string}\n";

    // $this->api->get($uri, $query_string);
  }

}
