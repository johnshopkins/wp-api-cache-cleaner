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

    $this->worker->addFunction("api_clear_cache", array($this, "clearCache"));
  }

  public function clearCache(\GearmanJob $job)
  {
    $workload = json_decode($job->workload());

    // clear ID (if any)
    if (isset($workload->id)) {
      $this->clear($workload->id);
    }

    // clear endpoint (if any)
    if (isset($workload->endpoint)) {
      $this->clear($workload->endpoint);
    }

  }

  protected function clear($endpoint)
  {
    echo $this->getDate() . " Clearing endpoint cache for {$endpoint}\n";

    $url = $this->getBase() . "/api/" . $endpoint;

    $this->http->get($url, array("clear_cache" => true));

    $response = $this->http->response;
    unset($response["body"]);
    unset($response["body_raw"]);
    print_r($response);

    $status = $this->http->getStatusCode();

    var_dump($status);

    if ($status !== 200) {
      $this->logger->addWarning("Endpoint `{$endpoint}` was unable to be cleared. Returned with a status of {$status}");
      echo $this->getDate() . " Endpoint `{$endpoint}` was unable to be cleared. Returned with a status of {$status}\n";
    } else {
      echo $this->getDate() . " Endpoint cache cleared successfully for {$endpoint}\n";
    }

    echo "------\n";
  }

  protected function getBase()
  {
    if (ENV == "local") {
      return "https://local.jhu.edu";
    } elseif (ENV == "staging") {
      return "https://staging.jhu.edu";
    } else {
      return "https://origin-beta1.jhu.edu";
    }
  }

  protected function getDate()
  {
    return date("Y-m-d H:i:s");
  }

}
