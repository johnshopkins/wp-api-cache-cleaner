<?php

namespace CacheCleaner;

class Warmer
{
  /**
   * In order of least embedded objects to most
   * @var array
   */
  protected $contentTypes = array(

    // no embeds
    "attachment",
    "block",
    "club",
    "division"
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
    "quote",            // person
    
  );

  protected $endpoints = array(
    "sections/maps",
    "sections/profiles",
    "sections/timeline",
    "sections/why-hopkins"
  );

  protected $api;

  public function __construct($deps = array())
  {
    $this->api = isset($deps["api"]) ? $deps["api"] : new \WPUtilities\API();
  }

  public function warmCache()
  {
    // content types
    $params = array("per_page" => -1);
    foreach ($this->contentTypes as $type) {
      $this->api->get($type, $params);
    }

    // if this is only used for elastic search, don't do it
    // $params["returnEmbedded"] = false;
    // foreach ($this->contentTypes as $type) {
    //   $this->get($type, $params);
    // }
    
    // custom endpoints
    $params = array();
    foreach ($this->endpoints as $endpoint) {
      $this->api->get($endpoint, $params);
    }
  }
}
