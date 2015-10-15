<?php
/*
Plugin Name: CacheCleaner
Description: Takes care of clearing the permanantly cached items when necessary.
Author: Jen Wachter
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
    add_action("save_post", array($this, "savedPost"));

    // if trash is turned off, add a hook to take care of deleted posts.
    // Otherwise, deleted posts are treated with save_post as a status change
    if (defined("EMPTY_TRASH_DAYS") && EMPTY_TRASH_DAYS == 0) {
        add_action("deleted_post", array($this, "deletedPost"));
    }

    // menu
    add_action("wp_update_nav_menu", function () {
      $this->gearmanClient->doHighBackground("api_clear_cache", json_encode(array(
        "endpoint" => "menus"
      )));
    });

  }

  public function savedPost($id)
  {
    $post = get_post($id);

    // do nothing if this is just an auto draft
    if ($post->post_status == "auto-draft") return;

    if (in_array($post->post_type, array("field_of_study", "search_response"))) {

      $this->gearmanClient->doHighBackground("api_clear_cache", json_encode(array(
        "id" => $id,
        "endpoint" => "program-explorer"
      )));

    }
  }

  public function deletedPost($id)
  {
    $post = get_post($id);

    if (in_array($post->post_type, array("field_of_study", "search_response"))) {

      // remove this post from the program explorer
      $this->gearmanClient->doHighBackground("api_clear_cache", json_encode(array(
        "endpoint" => "program-explorer"
      )));

    }
  }

}

$jhu_cache_clearer = new CacheCleanerMain($wp_logger);
