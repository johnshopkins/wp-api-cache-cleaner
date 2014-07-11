<?php

namespace CacheCleaner\Utilities;
use Secrets\Secret;

class Validator
{
  public function __construct($headers)
  {
    $this->headers = $headers;
  }
  public function validate()
  {
    $secrets = Secret::get("jhu", "production", "plugins", "wp-api-cache-cleaner");

    $key = $secrets->key;
    $pw = $secrets->password;

    if (!isset($this->headers[$key]) || (isset($this->headers[$key]) && $this->headers[$key] !== $pw)) {
      return false;
    }

    return true;
  }
}
