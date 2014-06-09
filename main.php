<?php
/*
Plugin Name: CacheCleaner
Description: 
Author: johnshopkins
Version: 0.1
*/

class CacheCleanerMain
{
  protected $logger;
  protected $gearmanClient;

  public function __construct($logger)
  {
    $this->logger = $logger;

    $this->gearmanClient = isset($injection["gearmanClient"]) ? $injection["gearmanClient"] : new \GearmanClient();
    $this->gearmanClient->addServer("127.0.0.1");

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
    add_action("add_attachment", array($this, "clear_cache"));
    add_action("edit_attachment", array($this, "clear_cache"));
  }

  public function clear_cache($id)
  {
    $post = get_post($id);

    return $this->gearmanClient->doBackground("api_cache_clean", json_encode(array(
      "post" => $post,
      "endpoint" => $post->post_type == "acf" ? "relationships" : $id
    )));
    
  }

}

new CacheCleanerMain($wp_logger);
