<?php

use Keboola\Json\Parser;

class Conductor
{
  private $api;
  private $parser;
  private $config;
  private $destination;
  private $lastRequest = 0;

  public function __construct($config, $destination)
  {
    $mandatoryConfigColumns = array(
      'apiKey', 
      '#sharedSecret'
    );

    date_default_timezone_set('UTC');
    $this->destination = $destination;

    foreach ($mandatoryConfigColumns as $c)
    {
      if (!isset($config[$c])) 
      {
        throw new Exception("Mandatory column '{$c}' not found or empty.");
      }

      $this->config[$c] = $config[$c];
    }

    // API initialization
    $this->api = new RestClient(array(
        'base_url' => "https://api.conductor.com/v3/", 
        'headers' => array(
          'Accept' => 'application/json',
        ),
        'parameters' => array(
          'apiKey' => $this->config['apiKey'],
          'sig' => md5($this->config['apiKey'].$this->config['#sharedSecret'].time()),
        ),
    ));

    $this->api->register_decoder('json', 
    create_function('$a', "return json_decode(\$a);"));

    $this->parser = Parser::create(new \Monolog\Logger('json-parser'));
  }

  private function logMessage($message)
  {
    echo($message."\n");
  }

  public function run()
  {
    $this->logMessage('Downloading Rank Sources.');
    $rankSources = $this->makeRequest('rank-sources');
    $this->createCsv($rankSources,'rank_sources');

    $this->logMessage('Downloading Locations.');
    $rankSources = $this->makeRequest('locations');
    $this->createCsv($rankSources,'locations');
    
    $this->logMessage('Downloading Devices.');
    $rankSources = $this->makeRequest('devices');
    $this->createCsv($rankSources,'devices');

    $this->logMessage('Done.');
  }

  private function makeRequest($url)
  {
    if ($this->lastRequest == time())
    {
      sleep(1);
    }

    $result = $this->api->get($url);
    $result = $result->decode_response();

    $this->lastRequest = time();

    return $result;
  }

  private function createCsv($json, $name)
  {
    $this->parser->process($json, $name);
    $result = $this->parser->getCsvFiles();

    foreach ($result as $file)
    {
      copy($file->getPathName(), $this->destination.substr($file->getFileName(), strpos($file->getFileName(), '-')+1));
    }
  }
}