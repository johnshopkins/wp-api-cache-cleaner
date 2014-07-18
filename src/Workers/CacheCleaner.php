<?php

namespace CacheCleaner\Workers;
use Secrets\Secret;

/**
 * Gearman Worker
 */
class CacheCleaner extends BaseWorker
{
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

  public function __construct($settings = array(), $deps = array())
  {
    $this->httpEngine = isset($deps["httpEngine"]) ? $deps["httpEngine"] : new \HttpExchange\Adapters\Resty(new \Resty\Resty());
    $this->post_util = isset($deps["post_util"]) ? $deps["post_util"] : new \WPUtilities\Post();
    
    parent::__construct($settings, $deps);
  }
    

  public function addFunctions()
  {
    parent::addFunctions();
    $this->worker->addFunction("api_cache_clean", array($this, "clean"));
  }

  public function clean(\GearmanJob $job)
{
    $workload = json_decode($job->workload());

    print_r($workload->post);

    echo $this->getDate() . " Starting API cache clearing for post #{$workload->post->ID}.\n";

    $result = $this->clearCache($workload->post);
    
    if ($result) {
      echo $this->getDate() . " API cache cleared for post #{$workload->post->ID}.\n";
    } else {
      echo $this->getDate() . " API cache did not need to be cleared for post #{$workload->post->ID} (revision).\n";
    }

    echo "\n\n\n";
  }

  public function clearCache($post)
  {
    echo $this->getDate() . " Checking Revision status for #{$post->ID}.\n";

    if ($this->isRevision($post)) {
      echo $this->getDate() . " Post is a revision, skipping #{$post->ID}.\n";
      return false;
    } else {
      echo $this->getDate() . " POst is not a revision, continue #{$post->ID}.\n";
    }

    echo $this->getDate() . " Fetching secrets... #{$post->ID}.\n";

    $secrets = Secret::get("jhu", "production", "plugins", "wp-api-cache-cleaner");

    $key = $secrets->key;
    $pw = $secrets->password;

    echo $this->getDate() . " Key fetched {$key} #{$post->ID}.\n";
    echo $this->getDate() . " Password fetched {$pw} #{$post->ID}.\n";

    $get = $this->getBase() . "/assets/plugins/wp-api-cache-cleaner/src/wget_cleaner.php";
    
    $endpoint = $post->post_type == "acf" ? "relationships" : $post->post_type;
    
    $params = array(
      "endpoint" => $endpoint,
      "id" => $post->post_type == "acf" ? null : $post->ID
    );
    
    $headers = array($key => $pw);

    $result = $this->httpEngine->get($get, $params, $headers)->getBody();

    print_r(json_decode($result));

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
