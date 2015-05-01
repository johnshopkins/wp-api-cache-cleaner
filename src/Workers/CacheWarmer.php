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
   * A list of content types to clear in
   * the cache in the exact order they should
   * clear. Objects that have other objects
   * embedded in them should clear __after__
   * their embedded objects for maximum speed.
   * @var array
   */
  protected $contentTypes = array(

    // no embeds
    "attachment",
    "club",
    "related_content",
    "button",


    "division",         // attachment
    "teaser",           // attachment
    "fact",             // attachment
    "location",         // attachment
    "hero_video",       // attachment
    // "milestone",        // attachment -- not being used for anything right now`

    "field_of_study",   // division
    "instagram_media",  // location
    "map",              // location



    /**
     * Person and quote objects both contain each
     * other, so their order doesn't really matter.
     */
    "person",           // attachment, field_of_study, club, division, quote
    "quote",            // person

    "block",            // so many things
    "page",             // block, attachment
    "collection"

  );

  protected $endpoints = array(
    "sections/maps",
    "sections/divisions",
    "sections/research",
    "menus",
    "rave/alert",
    "bigstory",
    "hub/articles/tray",
    "hub/events/tray",
    "program-explorer"
  );

  public function __construct($settings = array(), $deps = array())
  {
    $this->api = isset($deps["api"]) ? $deps["api"] : new \WPUtilities\API(null, true);
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

      // add breadcrumbs and menus endpoints
      if ($type == "page") {
        $this->api->get("breadcrumbs/{$id}", $params);
        $this->api->get("menus", $params);
      }

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
