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

    if (isset($workload->post)) {

      $result = $this->clearCache($workload);
      if ($result) {
        echo $this->getDate() . " API cache cleared for post #{$workload->post->ID}.\n";
        echo "------\n";
      }

    }

    if (isset($workload->endpoint)) {

      $result = $this->clearCache($workload);
      if ($result) {
        echo $this->getDate() . " API cache cleared for endpoint /{$workload->endpoint}.\n";
        echo "------\n";
      }

    }

  }

  public function clearCache($workload)
  {
    // stop if the post is a revision
    if (isset($workload->post) && $this->isRevision($workload->post)) return false;

    $url = $this->getBase() . "/assets/plugins/wp-api-cache-cleaner/src/wget_cleaner.php";

    $results = $this->httpEngine->get($url, $this->getParams($workload), $this->getHeaders())->getBody();
    $results = json_decode($results);

    foreach ($results as $result) {
      echo $this->getDate() . " {$result}\n";
    }

    return true;
  }

  protected function getParams($workload)
  {
    $params = array();

    if (isset($workload->post)) {

      $params["endpoint"] = $workload->post->post_type;
      $params["id"] = $workload->post->ID;

    } else if (isset($workload->endpoint)) {

      $params["endpoint"] = $workload->endpoint;

    }

    return $params;
  }

  protected function getHeaders()
  {
    $secrets = Secret::get("jhu", "production", "plugins", "wp-api-cache-cleaner");

    $key = $secrets->key;
    $pw = $secrets->password;

    return array($key => $pw);
  }

  protected function getBase()
  {
    if (ENV == 'local') {
      return "https://local.jhu.edu";
    } elseif (ENV == 'staging') {
      return "https://staging.jhu.edu";
    } elseif (ENV == 'beta') {
      return "https://beta.jhu.edu";
    } else {
      return "https://jhu.edu";
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
