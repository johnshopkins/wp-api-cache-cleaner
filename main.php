<?php
/*
Plugin Name: CacheCleaner
Description:
Author: johnshopkins
Version: 0.1
*/

use \Secrets\Secret;

class CacheCleanerMain
{
  protected $logger;
  protected $gearmanClient;

  public function __construct($logger)
  {
    $this->logger = $logger;
    $this->gearmanClient = new \GearmanClient();

    $servers = Secret::get("jhu", ENV, "servers");

    if ($servers) {

      foreach ($servers as $server) {
        $this->gearmanClient->addServer($server->hostname);
      }

    } else {
      $this->logger->addCritical("Servers unavailable for Gearman " . __FILE__ . " on line " . __LINE__);
    }

    $this->addHooks();
  }

  protected function addHooks()
  {
    // posts
    add_action("save_post", array($this, "clear_cache"));

    // if trash is turned off, add a hook to take care of deleted
    // posts. Otherwise, deleted posts are treated with save_post
    // as a status change
    if (defined("EMPTY_TRASH_DAYS") && EMPTY_TRASH_DAYS == 0) {
        add_action("deleted_post", array($this, "clear_cache"));
    }

    // attachments
    // add_action("add_attachment", array($this, "clear_cache")); // this is taken care of in wp-uploads-sync
    add_action("edit_attachment", array($this, "clear_cache"));

    // menu
    add_action("wp_update_nav_menu", function () {
      $this->clear_endpoint_cache("menus");
    });

  }

  /**
   * Clear an object endpoint
   * @param  integer $id
   * @return Job queue ID
   */
  public function clear_cache($id)
  {
    $post = get_post($id);

    // if ACF, clear the relationship endpoint
    if ($post->post_type == "acf") {
      $this->clear_endpoint_cache("relationships");
    }

    // if field of study, clear the program-explorer endpoint
    if ($post->post_type == "field_of_study") {
      $this->clear_endpoint_cache("program-explorer");
    }

  }

  /**
   * Clear an endpoint
   * @param  string $endpoint
   * @return Job queue ID
   */
  public function clear_endpoint_cache($endpoint)
  {
    return $this->gearmanClient->doHighBackground("api_clear_endpoint", json_encode(array(
      "endpoint" => $endpoint
    )));
  }

}

$jhu_cache_clearer = new CacheCleanerMain($wp_logger);
