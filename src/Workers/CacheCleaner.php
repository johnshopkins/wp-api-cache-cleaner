<?php

namespace CacheCleaner\Workers;

/**
 * Gearman Worker
 */
class CacheCleaner
{
  /**
   * HTTP Engine
   * @var object
   */
  protected $http;

  public function __construct($settings = array())
  {
    $this->worker = $settings["worker"];
    $this->logger = $settings["logger"];
    $this->http = new \HttpExchange\Adapters\Resty(new \Resty\Resty());

    $this->worker->addFunction("api_clear_endpoint", array($this, "clearEndpoint"));
  }

  public function clearEndpoint(\GearmanJob $job)
  {
    $workload = json_decode($job->workload());
    $endpoint = $workload->endpoint;

    echo $this->getDate() . " Clearing endpoint cache for {$endpoint}\n";

    $url = $this->getBase() . "/api/" . $endpoint;

    $this->http->get($url, array("clear_cache" => true));

    $status = $this->http->getStatusCode();
    if ($status != 200) {
      $this->logger->addWarning("Endpoint `{$endpoint}` was unable to be cleared. Returned with a status of {$status}");
    }

    echo $this->getDate() . " Endpoint cache cleared successfully for {$endpoint}\n";
  }

  protected function getBase()
  {
    if (ENV == "local") {
      return "https://local.jhu.edu";
    } elseif (ENV == "staging") {
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
