<?php

namespace CacheCleaner\Workers;

/**
 * Gearman Worker
 */
class CacheCleaner extends BaseWorker
{
  /**
   * HTTP Engine
   * @var [type]
   */
  protected $http;

  public function __construct($settings = array())
  {
    $this->worker = $settings["worker"];
    $this->logger = $settings["logger"];
    $this->http = new \HttpExchange\Adapters\Resty(new \Resty\Resty());

    $this->addFunctions();
  }

  public function addFunctions()
  {
    parent::addFunctions();
    $this->worker->addFunction("api_clear_endpoint", array($this, "clearEndpoint"));
  }

  public function clearEndpoint(\GearmanJob $job)
  {
    $workload = json_decode($job->workload());
    $endpoint = $wordload->endpoint;

    echo $this->getDate() . " Clearing endpoint cache for {$endpoint}\n";

    $url = $this->getBase() . "/" + $endpoint;
    $result = $this->http->get($url);

    print_r($results); die();
  }

  protected function getBase()
  {
    if (ENV == 'local') {
      return "https://local.jhu.edu";
    } elseif (ENV == 'staging') {
      return "https://staging.jhu.edu";
    } else {
      return "https://www.jhu.edu";
    }
  }

  protected function getDate()
  {
    return date("Y-m-d H:i:s");
  }

}
