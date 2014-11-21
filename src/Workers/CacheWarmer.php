<?php

namespace CacheCleaner\Workers;

class CacheWarmer extends BaseWorker
{
  /**
   * API
   * @var object
   */
  protected $api;

  /**
   * In order of least embedded objects to most
   * @var array
   */
  protected $contentTypes = array(

    // no embeds
    "attachment",
    "club",
    "division",
    "related_content",

    // embeds
    "fact",             // attachment
    "field_of_study",   // division
    "location",         // attachment
    "instagram_media",  // location
    "timeline_event",   // attachment

    "map",              // attachment, location
    "page",             // block, attachment
    
    "person",           // attachment, field_of_study, club, division
    "quote",            // person

    "block",            // many things
    "collection"
    
  );

  protected $endpoints = array(
    "sections/maps",
    // "sections/profiles",
    // "sections/timeline",
    // "sections/why-hopkins",
    // "map/hopkins-on-the-road",
    "menus"
  );

  public function __construct($settings = array(), $deps = array())
  {
    $this->api = isset($deps["api"]) ? $deps["api"] : new \WPUtilities\API();
    $this->wordpress_query = isset($deps["wordpress_query"]) ? $deps["wordpress_query"] : new \WPUtilities\WPQueryWrapper();
    $this->cache = $settings["cache"];
    $this->logger = $settings["logger"];

    $this->cachePrefix = isset($deps["cachePrefix"]) ? $deps["cachePrefix"] : new \WordPressAPI\Utilities\CachePrefix($this->cache);

    parent::__construct($settings, $deps);
  }

  public function addFunctions()
  {
    parent::addFunctions();
    $this->worker->addFunction("api_cache_warm", array($this, "warmCache"));
  }

  /**
   * Warm cache. We don't just query the collection endpoint
   * because they would cause that endpoint to be cleared
   * everytime a post in that post type is changed. By
   * warming only the object endpoints, we limit the work
   * needed to be done by the cache cleaner in the future.
   * @return null
   */
  public function warmCache(\GearmanJob $job)
  {
    $workload = json_decode($job->workload());
    $types = $workload->types;

    $oldStorePrefix = $this->cachePrefix->getStorePrefix();

    // if the memcached server is disabled for more than 10 seconds
    if (!$oldStorePrefix) {
      $this->logger->addCritical("Memcached server is unable to store items in " . __FILE__ . " on line " . __LINE__);
      return false;
    }

    // increment cache prefix
    $this->cachePrefix->incremenetStorePrefix();

    // warm cache
    $newStorePrefix = $this->cachePrefix->getStorePrefix();
    echo $this->getDate() . " Warming cache to new prefix ({$newStorePrefix})...\n";
    echo "-\n";

    // content types
    foreach ($this->contentTypes as $type) {

      if (!in_array($type, $types)) continue;

      $status = $type == "attachment" ? "inherit" : "publish";
      $this->warmObjects($type, $status);

      echo "-\n";
    }

    // endpoints
    foreach ($this->endpoints as $endpoint) {
      $params = array("warming" => true);
      $this->api->get("/{$endpoint}", $params);
      echo $this->getDate() . " Cached warmed for {$endpoint}.\n";
    }

    echo "-\n";

    echo $this->getDate() . " Finished warming cache.\n";


    // sync the fetch prefix with the store prefix
    $this->cachePrefix->syncFetchPrefix();

    // clean up old cache entries
    echo $this->getDate() . " Removing items from cache with old prefix ({$oldStorePrefix})...\n";
    $this->cleanupOldCache($oldStorePrefix);

    echo $this->getDate() . " Old items cleaned up.\n";

    echo "------\n";

    return true;
  }

  protected function cleanupOldCache($prefix)
  {
    $cacheKeys = $this->cache->getKeys();

    if (!$cacheKeys) return;

    foreach ($cacheKeys as $rawKey) {

      parse_str($rawKey, $parsedKey);

      if (isset($parsedKey["prefix"]) && $parsedKey["prefix"] == $prefix) {
        // echo $this->getDate() . " Deleting key with {$prefix} prefix.\n";
        $this->cache->delete($rawKey, false);
      }

    }

  }

  protected function warmObjects($type, $status, $paged = 1)
  {
    $result = $this->wordpress_query->run(array(
      "post_type" => $type,
      "post_status" => $status,
      "paged" => $paged,
      "fields" => "ids"
    ));

    $ids = $result->posts;

    foreach ($ids as $id) {

      $params = array("warming" => true);
      $this->api->get($id, $params);

      $params["returnEmbedded"] = false;
      $this->api->get($id, $params);

      echo $this->getDate() . " Cached warmed for {$type}/{$id}.\n";

    }

    echo $this->getDate() . " Page {$paged} of {$result->max_num_pages} for {$type} complete.\n";

    if ($result->max_num_pages > $paged) {
      $this->warmObjects($type, $status, $paged + 1);
    } else {
      return true;
    }

  }
}
