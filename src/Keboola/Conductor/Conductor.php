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

    if (!empty($config['debug']))
    {
      $this->config['debug'] = true;
    }

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
    $this->logMessage('Downloading Locations.');
    $locations = $this->makeRequest('locations');
    $this->createCsv($locations,'locations');
    
    $this->logMessage('Downloading Devices.');
    $devices = $this->makeRequest('devices');
    $this->createCsv($devices,'devices');

    $this->logMessage('Downloading Rank Sources.');
    $rankSources = $this->makeRequest('rank-sources');
    $this->createCsv($rankSources,'rank_sources');

    $this->logMessage('Downloading Accounts, Web Properties, Ranked Searches and Rank Reports.');
    $accounts = $this->makeRequest('accounts');
    $this->createCsv($accounts,'accounts');

    $webPropertiesData = array();
    $rankedSearchesData = array();
    $rankReportsData = array();

    foreach ($accounts as $account)
    {
      $webProperties = $this->makeRequest('accounts/'.$account->accountId.'/web-properties');
      $webPropertiesData = array_merge($webPropertiesData, $webProperties);

      foreach ($webProperties as $webProperty)
      {
        $rankedSearches = $this->makeRequest('accounts/'.$account->accountId.'/web-properties/'.$webProperty->webPropertyId.'/tracked-searches');
        $rankedSearchesData = array_merge($rankedSearchesData, $rankedSearches);

        foreach ($rankSources as $rankSource)
        {
          $rankReports = $this->makeRequest($account->accountId.'/web-properties/'.$webProperty->webPropertyId.'/rank-sources/'.$rankSource->rankSourceId.'/tp/CURRENT/serp-items');
          $rankReportsData = array_merge($rankReportsData, $rankReports);
        }
      }
    }

    $this->createCsv($webPropertiesData,'web_properties');
    $this->createCsv($rankedSearchesData,'ranked_searches');
    $this->createCsv($rankReportsData,'rank_reports');

    $this->logMessage('Done.');
  }

  private function makeRequest($url)
  {
    if ($this->lastRequest+1 == time())
    {
      sleep(1);
    }

    if (!empty($this->config['debug']))
    {
      echo "endpoint: ";
      print_r($url);
      echo "\n";
    }

    try
    {
      $result = $this->api->get($url);
      $parsedResult = $result->decode_response();
    }
    catch (Exception $e)
    {
      print_r($result->response);
      print_r($e);
      exit;
    }

    $this->lastRequest = time();

    return $parsedResult;
  }

  private function createCsv($json, $name)
  {
    if (!empty($this->config['debug']))
    {
      echo "json: ".$name."\n";
    }

    try
    {
      $this->parser->process($json, $name);
      $result = $this->parser->getCsvFiles();
    }
    catch (Exception $e)
    {
      print_r($e);
      print_r($json);
      exit;
    }

    foreach ($result as $file)
    {
      copy($file->getPathName(), $this->destination.substr($file->getFileName(), strpos($file->getFileName(), '-')+1));
    }
  }
}