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

    // Create admin pages
    add_action("wp_loaded", function () {
      new \CacheCleaner\Admin();
    });

    $this->gearmanClient = isset($injection["gearmanClient"]) ? $injection["gearmanClient"] : new \GearmanClient();

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
    // warm button in admin
    add_action("admin_post_wp_api_cache_cleaner_warm", array($this, "warm_cache"));

    // posts
    add_action("save_post", array($this, "clear_cache"));

    // if trash is turned off, add a hook to take care of deleted
    // posts. Otherwise, deleted posts are treated with save_post
    // as a status change
    if (defined("EMPTY_TRASH_DAYS") && EMPTY_TRASH_DAYS == 0) {
        add_action("deleted_post", array($this, "clear_cache"));
    }

    // attachments
    add_action("add_attachment", array($this, "clear_cache"));
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

    // don't clear nav menu items
    if ($post->post_type == "nav_menu_item") return;

    // if ACF, clear the relationship endpoint
    if ($post->post_type == "acf") return $this->clear_endpoint_cache("relationships");

    // if field of study, clear the program-explorer endpoint and the psot
    if ($post->post_type == "field_of_study") {
      return $this->gearmanClient->doHighBackground("api_cache_clean", json_encode(array(
        "post" => $post,
        "endpoint" => "program-explorer"
      )));

    }

    // otherwise, clear the post
    return $this->gearmanClient->doHighBackground("api_cache_clean", json_encode(array(
      "post" => $post
    )));

  }

  /**
   * Clear an endpoint
   * @param  string $endpoint
   * @return Job queue ID
   */
  public function clear_endpoint_cache($endpoint)
  {
    return $this->gearmanClient->doHighBackground("api_cache_clean", json_encode(array(
      "endpoint" => $endpoint
    )));
  }

  public function warm_cache()
  {
    // get checked types
    if (isset($_POST["action"])) unset($_POST["action"]);
    if (isset($_POST["submit"])) unset($_POST["submit"]);

    $this->gearmanClient->doBackground("api_cache_warm", json_encode(array()));

    $redirect = admin_url("tools.php?page=api-cache");
    header("Location: {$redirect}");
  }

}

$jhu_cache_clearer = new CacheCleanerMain($wp_logger);
