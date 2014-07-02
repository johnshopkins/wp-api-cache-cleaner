<?php

namespace CacheCleaner\Workers;
use Secrets\Secret;

class CacheKeyCleaner
{
  protected $api;

  public function __construct($deps = array())
  {
    

    $this->api = isset($deps["api"]) ? $deps["api"] : new \WPUtilities\API();
  }

  public function clean($endpoint)
  {
    $cacheInfo = apc_cache_info("user");
    $cacheList = $cacheInfo["cache_list"];

    $flatPatterns = array(
      "uri=%2F" . $endpoint,      // the object
      "meta_[^=]+=" . $endpoint   // meta lookups on this object
    );

    foreach ($cacheList as $item) {

      $key = $item["info"];

      // not an API cache key
      if (!preg_match("/source=wpapi/", $key)) continue;

      // parse string into array
      parse_str($item["info"], $parsedKey);

      /**
       * Look for the object's cache key or meta lookups on the object
       */
      if (preg_match("/(" . implode("|", $flatPatterns) . ")/", $key)) {
        apc_delete($key);
        $this->clearCache($parsedKey); // what if this is a deleted object?
        continue;
      }

      /**
       * Look for other objects that have this object stitched into it
       * 
       * If this key contained stitched object information and the object that
       * changed is within that key, get the value of that key, which is the parent
       * object that has the changed object stitched into it. Run the clean function
       * using this new ID to clean out its cache.
       */
      if (isset($parsedKey["stitched"]) && in_array($endpoint, $parsedKey["stitched"])) {
        
        // get rid of prepended "/"
        $parentPostId = trim(apc_fetch($key), "/");

        // get rid of this key
        apc_delete($key);

        $this->clean($parentPostId);
        continue;
      }

    }
  }

  public function clearCache($key)
  {
    $uri = $key["uri"];

    // get rid of non-query string items
    unset($key["uri"]);
    unset($key["stitched"]);
    unset($key["source"]);

    $query_string = http_build_query($key);

    $response = $this->api->get($uri, $query_string);

    print_r($response);
  }
}
