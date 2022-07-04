<?php
namespace Dosdashboard;

require 'vendor/autoload.php';

use \GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use \GuzzleHttp\Psr7;
use \GuzzleHttp\Exception\ClientException;

use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;

use Kevinrob\GuzzleCache\Storage\FlysystemStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use League\Flysystem\Adapter\Local;


class Parser {

  public const BASE_ENDPOINT = 'https://www.drupal.org/api-d7/';
  public const COMMENTS_ENDPOINT = 'comment.json';
  public const NODE_ENDPOINT = 'node.json';
  public const USER_ENDPOINT = 'user.json';
  public const EPAM_ID = 2114867;
  public const CACHE_TTL = 180000;
  public const CACHE_LOCATION = '/tmp/dos';
  public const CSV_FILENAME = '/tmp/contribution_credits.csv';
  public const MAPPING_FILENAME = 'assets/mapping.csv';

  /**
   * @var \GuzzleHttp\Client
   */
  private Client $client;

  /**
   * @var int|mixed
   */
  private mixed $limit;

  public function __construct($limit = 0) {
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
      'base_uri' => self::BASE_ENDPOINT,
      'handler' => $stack,
    ]);
    $this->limit = $limit;

  }

  public function getCommentsByCompany($company) {
    $params = [
      'field_attribute_contribution_to' => $company,
      'sort' => 'created',
      'direction' => 'desc',
      'page' => 0,
    ];
    return $this->getComments($params);
  }

  public function getCommentsByCustomer($customer): array {
    $params = [
      'field_for_customer' => $customer,
      'sort' => 'created',
      'direction' => 'desc',
      'page' => 0,
    ];
    return $this->getComments($params);
  }

  public function getCommentsByUser($user, $limit = 0): array {
    $params = [
      'name' => $user,
      'sort' => 'created',
      'direction' => 'desc',
      'page' => 0,
      'limit' => $limit,
    ];
    return $this->getComments($params);
  }


  public function getNode($issue_id) {
    return $this->makeRequest(self::NODE_ENDPOINT, ['nid' => $issue_id]);
  }

  public function getUser($user_id) {
    return $this->makeRequest(self::USER_ENDPOINT, ['uid' => $user_id]);
  }

  public function prepareResponse($comment, $issue, $project, $user) {
    $project_usage = 0;
    if (!empty($project['project_usage'])) {
      foreach ($project['project_usage'] as $usage) {
        $project_usage += $usage;
      }
    }
    $mapping = $this->readMapping();
    $weight = $this->getWeight($project['field_project_machine_name'], $project['type'], $project_usage);
    return [
      'project_nid' => $project['nid'] ?? '',
      'project_title' => $project['title'] ?? '',
      'project_short' => $project['field_project_machine_name'] ?? '',
      'project_type' => $project['type'] ?? '',
      'project_url' => $project['url'] ?? '',
      'project_usage' => $project_usage,
      'adjusted_weight' => $weight,
      'issue_id' => $issue['nid'] ?? '',
      'issue_title' => $issue['title'] ?? '',
      'issue_url' => $issue['url'] ?? '',
      'uid' => $user['uid'],
      'user_name' => $user['name'],
      'user_email' => $mapping[$user['name']] ?? '',
      'user_country' => $user['field_country'],
      'user_url' => $user['url'],
      'user_fio' => $user['field_first_name'] . ' ' . $user['field_last_name'] ,
      'credit_date' => $issue['changed'] ?? '',
    ];
  }

  public function getHeaders() {
    return [
      'Project Id',
      'Project Title',
      'Project Short',
      'Project Type',
      'Project URL',
      'Project Usage',
      'Adjusted Weight',
      'issue Id',
      'Issue Title',
      'Issue URL',
      'Uid',
      'User Name',
      'User Email',
      'User Country',
      'User URL',
      'User FIO',
      'Credit Date',
    ];
  }

  public function writeCSV($results) {
    array_unshift($results, $this->getHeaders());
    $fp = fopen(self::CSV_FILENAME, 'w');
    foreach ($results as $fields) {
      fputcsv($fp, $fields);
    }
    fclose($fp);
  }

  private function readMapping() {
    $result = [];
    if (($handle = fopen(__DIR__ . DIRECTORY_SEPARATOR . self::MAPPING_FILENAME, "r")) !== FALSE) {
      while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
        $dorg_name = $data[0] ?? '';
        $epam_name = $data[1] ?? '';
        $result[$dorg_name] = $epam_name;
      }
      fclose($handle);
    }
    return $result;
  }

  private function getWeight($project_name, $project_type, $usages) {
    if ($project_name === 'once' || $project_name === 'a11y_autocomplete' ||
      $project_name === 'automatic_updates' || $project_name === 'decoupled_menus_initiative' ||
      $project_name === 'infrastructure' || $project_name === 'drupalorg' ||
      $project_name === 'olivero' || $project_name === 'project_browser' ||
      $project_name === 'ideas' || $project_name === 'ckeditor5') {
      return 9.5;
    }
    if ($project_type === 'project_drupalorg') {
      return 2;
    }

    $weight = $usages / 100000;
    if ($weight < 1) {
      $weight = 1;
    }
    return $weight;
  }

  private function getComments($params, $response = []): array {
    $comments = $this->makeRequest(self::COMMENTS_ENDPOINT, $params);
    $response = array_merge($response, $comments['list']);
    if ($comments['self'] === $comments['last']) {
      return $response;
    }
    if ($this->limit && count($response) >= $this->limit) {
      return array_slice($response, 0, $this->limit);
    }
    $params['page']++;
    return $this->getComments($params, $response);
  }

  private function makeRequest($uri, $params) {
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



}
