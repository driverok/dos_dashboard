<?php
namespace Dosdashboard;


use \GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use \GuzzleHttp\Exception\ClientException;

use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;

use Kevinrob\GuzzleCache\Storage\FlysystemStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use League\Flysystem\Adapter\Local;

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

  /**
   * @var mixed|string
   */
  private mixed $mapping_file;

  public array $mapping;

  public function __construct($titles, $base_uri, $verbose, $mapping_file = NULL) {
    $stack = HandlerStack::create();
    $stack->push(
      new CacheMiddleware(
        new PrivateCacheStrategy(
          new FlysystemStorage(
            new Local(self::CACHE_LOCATION)
          ), self::CACHE_TTL
        )
      ),
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
    $this->mapping_file = $mapping_file ?? self::MAPPING_FILENAME;
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

  public function makeRequest($uri, $params) {
    try {
      $response = $this->client->request('GET', $uri, [
        'query' => $params
      ]);
      $body = $response->getBody();
    } catch (ClientException | GuzzleException $e) {
      echo $e->getMessage();
    }
    return json_decode($body, TRUE, 512, JSON_THROW_ON_ERROR);
  }

  public function log($message, $inline = FALSE) {
    if ($this->verbose) {
      $prefix = !$inline ? "\n" . date('H:i') : '';
      echo $prefix . $message;
    }
  }
}
