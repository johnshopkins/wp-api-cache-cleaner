<?php

namespace CacheCleaner\Utilities;
use Secrets\Secret;

class Validator
{
  public function __construct($params)
  {
    $this->params = $params;
  }
  public function validate()
  {
    $secrets = Secret::get("jhu", ENV, "plugins", "wp-api-cache-cleaner");

    $key = $secrets->key;
    $pw = $secrets->password;

    if (!isset($this->params[$key]) || (isset($this->params[$key]) && $this->params[$key] !== $pw)) {
      return false;
    }

    return true;
  }
}
