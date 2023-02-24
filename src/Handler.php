<?php

namespace Dosdashboard;

use \GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use \GuzzleHttp\Exception\ClientException;

use GuzzleHttp\HandlerStack;
use JsonException;
use Kevinrob\GuzzleCache\CacheMiddleware;

class Handler {
  public const CACHE_TTL = 180000;
  public const CACHE_LOCATION = '/tmp/dos';
  public const CSV_FILENAME = '/tmp/contribution_credits.csv';
  public const MAPPING_FILENAME = 'assets/mapping.csv';
  public const DORG_URL_PREFIX = 'https://www.drupal.org/u/';

  /**
   * @var \GuzzleHttp\Client
   */
  private Client $client;

  private $titles;

  public $verbose;

  private $mapping_file;

  public array $mapping;

  public array $unknown_users = [];

  public function __construct($titles, $base_uri, $verbose, $mapping_file = NULL) {
    $stack = HandlerStack::create();
    $stack->push(
      new CacheMiddleware(),
      'cache'
    );
    $this->client = new Client([
      'handler' => $stack,
      'base_uri' => $base_uri,
    ]);
    $this->titles = $titles;
    $this->verbose = $verbose;
    if (!file_exists(__DIR__ . DIRECTORY_SEPARATOR . $mapping_file)) {
      $mapping_file = '../' . $mapping_file;
    }
    $this->mapping_file = $mapping_file ?: self::MAPPING_FILENAME;
    $this->mapping = $this->readMapping();
  }

  public function writeCSV($results) {
    array_unshift($results, $this->titles);
    $fp = fopen(self::CSV_FILENAME, 'w');
    foreach ($results as $fields) {
      fputcsv($fp, $fields);
    }
    fclose($fp);
  }

  public function readMapping() {
    $result = [];
    if (($handle = fopen(__DIR__ . DIRECTORY_SEPARATOR . $this->mapping_file, "r")) !== FALSE) {
      while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
        $dorg_name = $data[0] ?? '';
        $epam_name = $data[1] ?? '';
        $result[strtolower($dorg_name)] = strtolower($epam_name);
      }
      fclose($handle);
    }
    return $result;
  }

  public function mapUser($user) {
    if (!empty($this->mapping[strtolower($user)])) {
      return $this->mapping[strtolower($user)];
    }

    $this->unknown_users[$user] = $user;
    return $user;
  }

  public function makeRequest($uri, $params, $headers = NULL) {
    $results = [];
    try {
      $response = $this->client->request('GET', $uri, [
        'query' => $params,
        'headers' => $headers
      ]);
      if ($response->getStatusCode() !== 200) {
        return $results;
      }
      $body = $response->getBody();
      $results = json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);
    } catch (ClientException | GuzzleException | JsonException $e) {
      $this->log($e->getMessage());
    }
    return $results;
  }

  public function log($message, $inline = FALSE) {
    if ($this->verbose) {
      $prefix = !$inline ? "\n" . date('H:i') : '';
      echo $prefix . $message;
    }
  }
}
