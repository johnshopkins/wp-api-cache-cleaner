<?php

namespace CacheCleaner\Workers;
use Secrets\Secret;

/**
 * Gearman Job
 */
class CacheCleaner
{
  /**
   * Gearman worker
   * @var object
   */
  protected $worker;

  /**
   * Monolog
   * @var object
   */
  protected $logger;

  /**
  * Post Utility
  * @var object
  */
  protected $post_util;

  /**
   * HTTP Engine
   * @var [type]
   */
  protected $httpEngine;

  public function __construct($settings = array(), $injection = array())
  {
    $this->worker = $settings["worker"];
    $this->logger = $settings["logger"];
    
    $this->httpEngine = isset($injection["httpEngine"]) ? $injection["httpEngine"] : new \HttpExchange\Adapters\Resty(new \Resty\Resty());
    $this->post_util = isset($injection["post_util"]) ? $injection["post_util"] : new \WPUtilities\Post();

    $this->worker->addFunction("api_cache_clean", array($this, "clean"));
  }

  public function clean(\GearmanJob $job)
{
    $workload = json_decode($job->workload());

    echo $this->getDate() . " Starting API cache clearing for post #{$workload->post->ID}.\n";

    $result = $this->clearCache($workload);
    
    if ($result) {
      echo $this->getDate() . " API cache cleared for post #{$workload->post->ID}.\n";
    } else {
      echo $this->getDate() . " API cache did not need to be cleared for post #{$workload->post->ID} (revision).\n";
    }
  }

  public function clearCache($workload)
  {
    if ($this->isRevision($workload->post)) return false;

    $secrets = Secret::get("jhu", "production", "plugins", "wp-api-cache-cleaner");

    $key = $secrets->key;
    $pw = $secrets->password;

    $get = $this->getBase() . "/assets/plugins/wp-api-cache-cleaner/src/Workers/wget_cleaner.php";
    $response = $this->httpEngine->get($get, array("endpoint" => $workload->endpoint), array($key => $pw))->getBody();

    return true;
  }

  protected function getBase()
  {
    if (ENV == "local") {
      return "http://local.jhu.edu";
    } else if (ENV == "staging") {
      return "http://staging.jhu.edu";
    } else {
      return "http://jhu.edu";
    }
  }

  protected function getDate()
  {
    return date("Y-m-d H:i:s");
  }

  /**
   * Find out if a post is a revision
   * @param  object $post Post object
   * @return boolan
   */
  protected function isRevision($post)
  {
    return $this->post_util->isRevision($post);
  }

}
