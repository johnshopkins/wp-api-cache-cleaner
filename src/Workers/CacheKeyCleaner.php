<?php

namespace CacheCleaner\Workers;
use Secrets\Secret;

class CacheKeyCleaner
{
  protected $api;
  protected $endpoints = array();

  public function __construct($deps = array())
  {
    $this->api = isset($deps["api"]) ? $deps["api"] : new \WPUtilities\API();
  }

  public function cleanKeys($endpoint)
  {
    $cacheInfo = apc_cache_info("user");
    $cacheList = $cacheInfo["cache_list"];

    $encodedUri = urlencode($endpoint);

    $flatPatterns = array(
      // the object/endpoint (123, sections/timeline)
      "uri=%2F" . $encodedUri
    );

    if (is_int($endpoint)) {
      // meta lookups on this object
      $flatPatterns[] = "meta_[^=]+=" . $endpoint;
    }

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
        $this->endpoints[] = $key;
        // $this->clearCache($parsedKey); // what if this is a deleted object?
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
      if (isset($parsedKey["stitched"])) {

        $stitched = explode(",", $parsedKey["stitched"]);
      
        if (in_array($endpoint, $stitched)) {

          // find which endpoint this object is stitched to (value of cache key)
          $parentPostId = trim(apc_fetch($key), "/");

          // get rid of this key
          apc_delete($key);

          // clean the parent endpoint
          $this->cleanKeys($parentPostId);

        }
        
      }

    }
  }

  public function primeEndpoints()
  {
    $endpoints = array_unique($this->endpoints);

    foreach ($this->endpoints as $endpoint) {
      $this->clearCache($endpoint);
    }

  }

  protected function clearCache($endpoint)
  {
    parse_str($endpoint, $parsedEndpoint);

    $uri = $parsedEndpoint["uri"];

    // get rid of non-query string items
    unset($parsedEndpoint["uri"]);
    unset($parsedEndpoint["stitched"]);
    unset($parsedEndpoint["source"]);

    // Why does this have to be on? The cached should is deleted by now.
    $parsedEndpoint["clear_cache"] = true;

    $query_string = http_build_query($parsedEndpoint);

    echo "Clearing {$uri}?{$query_string}\n";

    $this->api->get($uri, $query_string);
  }
}
