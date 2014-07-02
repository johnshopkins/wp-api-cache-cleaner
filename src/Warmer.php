<?php

namespace CacheCleaner;

class Warmer
{
  protected $endpoints = array(
    "page",
    "attachment",
    "block",
    "club",
    "division",
    "fact",
    "field_of_study",
    "instagram_media",
    "location",
    "map",
    "person",
    "quote",
    "related_content",
    "timeline_event"
  );

  protected $api;

  public function __construct($deps = array())
  {
    $this->api = isset($deps["api"]) ? $deps["api"] : new \WPUtilities\API();
  }

  public function warmCache()
  {
    foreach ($this->endpoints as $endpoint) {
      $this->get($endpoint);
    }
  }

  protected function get($endpoint, $params = array())
  {
    $params["per_page"] = 1;
    $response = $this->api->get($endpoint, $params);

    print_r($response); die();

  }
}
