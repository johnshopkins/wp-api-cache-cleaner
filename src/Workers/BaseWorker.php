<?php

namespace CacheCleaner\Workers;

abstract class BaseWorker
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
     * API
     * @var object
     */
    protected $api;

    public function __construct($settings = array(), $deps = array())
    {
        $this->worker = $settings["worker"];
        $this->logger = $settings["logger"];

        $this->addFunctions();
    }

    protected function getDate()
    {
        return date("Y-m-d H:i:s");
    }

    protected function addFunctions() {}
    
}
