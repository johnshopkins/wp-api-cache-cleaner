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
      $this->logger->addAlert("Servers unavailable for Gearman " . __FILE__ . " on line " + __LINE__);
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

  }

  public function clear_cache($id)
  {
    $post = get_post($id);

    return $this->gearmanClient->doHighBackground("api_cache_clean", json_encode(array(
      "post" => $post
    )));
    
  }

  public function warm_cache()
  {
    // get checked types
    if (isset($_POST["action"])) unset($_POST["action"]);
    if (isset($_POST["submit"])) unset($_POST["submit"]);
    $types = array_keys($_POST);
    
    $this->gearmanClient->doNormal("api_cache_warm", json_encode(array(
      "types" => $types
    )));

    $redirect = admin_url("options-general.php?page=api-cache");
    header("Location: {$redirect}");
  }

}

new CacheCleanerMain($wp_logger);
