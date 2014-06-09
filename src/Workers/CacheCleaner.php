<?php

namespace CacheCleaner\Workers;

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

  public function __construct($settings = array(), $injection = array())
  {
    $this->worker = $settings["worker"];
    $this->logger = $settings["logger"];
    
    $this->worker->addFunction("api_cache_clean", array($this, "clean"));
  }

  public function clean(\GearmanJob $job)
{
    $workload = json_decode($job->workload());
    $this->clearCache($workload->endpoint);
    
    echo $this->getDate() . " API cache cleared for post #{$workload->endpoint}.\n\n";
  }

  public function clearCache($endpoint)
  {
    $info = apc_cache_info("user");

    $patterns = array(
      "uri=%2F" . $endpoint,
      "meta_[^=]+=" . $endpoint
    );

    foreach ($info["cache_list"] as $cachedItem) {
      if (preg_match("/(" . implode("|", $patterns) . ")/", $cachedItem["info"])) {
        echo $this->getDate() . " Deleting {$cachedItem['info']} key... .\n";
        apc_delete($cachedItem["info"]);
      }
    }

  }

  protected function getDate()
  {
    return date("Y-m-d H:i:s");
  }
}
