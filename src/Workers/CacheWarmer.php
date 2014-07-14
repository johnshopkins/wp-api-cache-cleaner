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
    "block",
    "club",
    "division",
    "related_content",

    // embeds
    "fact",             // attachment
    "field_of_study",   // division
    "instagram_media",  // location
    "location",         // attachment
    "timeline_event",   // attachment

    "map",              // attachment, location
    "page",             // block, attachment
    
    "person",           // attachment, field_of_study, club, division
    "quote"            // person
    
  );

  protected $endpoints = array(
    "sections/maps",
    "sections/profiles",
    "sections/timeline",
    "sections/why-hopkins"
  );

  public function __construct($settings = array(), $deps = array())
  {
    $this->api = isset($deps["api"]) ? $deps["api"] : new \WPUtilities\API();
    $this->wordpress_query = isset($deps["wordpress_query"]) ? $deps["wordpress_query"] : new \WPUtilities\WPQueryWrapper();

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
    echo $this->getDate() . " Starting warming cache...\n";

    foreach ($this->contentTypes as $type) {
      $status = $type == "attachment" ? "inherit" : "publish";
      $this->warmObjects($type, $status);
    }

    foreach ($this->endpoints as $endpoint) {
      $this->api->get("/{$endpoint}");
      echo $this->getDate() . " Cached warmed for {$endpoint}.\n";
    }

    echo $this->getDate() . " Finished warming cache.\n";
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

      $this->api->get("/{$id}");
      $this->api->get("/{$id}", array("returnEmbedded" => false));
      echo $this->getDate() . " Cached warmed for {$type}/{$id}.\n";

    }

    var_dump($result->max_num_pages . " - " . $paged);

    if ($result->max_num_pages > $paged) {
      $this->warmObjects($type, $status, $paged + 1);
    } else {
      return true;
    }

  }
}
