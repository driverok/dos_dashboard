<?php

namespace Dosdashboard\Parsers;

use Dosdashboard\Contribution;
use Dosdashboard\Handler;
use PHPHtmlParser\Dom;
use League\Csv\Writer;
use function array_merge;
use function array_push;
use function count;
use function curl_close;
use function curl_exec;
use function explode;
use function in_array;
use function json_decode;
use function sprintf;
use function str_replace;
use function strtotime;
use function time;
use function trim;
use const PHP_EOL;

class Drupalcode implements Contribution {

  public const CSV_FILENAME = '/tmp/contribution_credits.csv';
  private const DORG_USER_PROFILE_BASE = 'https://www.drupal.org/u/';
  private const DORG_ISSUE_BASE = 'https://www.drupal.org/project/%s/issues/%s';

  /**
   * @var \Dosdashboard\Handler
   */
  private Handler $handler;
  public const BASE_ENDPOINT = 'https://git.drupalcode.org/users/';

  public function __construct($user_name, $from_timestamp, $to_timestamp, $verbose, $mapping_file=NULL) {
    $this->handler = new Handler(self::CONTRIBUTION_TITLES, self::BASE_ENDPOINT, $verbose, $mapping_file);
    $this->userName = $user_name;
    $this->fromTimestamp = $from_timestamp;
    $this->toTimestamp = $to_timestamp;
    $this->endActivity = FALSE;
    $this->offset = 0;
    $this->limit = 10;
    $this->pushes = [];
    // @todo Use dependency injection.
    $this->dom = new Dom();
  }

  public function clearState() {
    $this->offset = 0;
    $this->pushes = [];
    $this->issues = [];
    $this->endActivity = FALSE;
  }

  public function getPushes() {
    while (!$this->endActivity) {
      $this->getUserActivity();
    }

  $this->handler->log(PHP_EOL . 'Total Drupalcode pushes: ' . count($this->pushes));
  return $this->pushes;
  }

  public function setUser($user) {
    $this->userName = $user;
  }

  public function getUserActivity() {
    $url = self::BASE_ENDPOINT . $this->userName . '/activity';
    $headers = [
      'Accept' => 'application/json, text/plain, */*',
      'User-Agent' => 'Postman',
      'X-Requested-With' => 'XMLHttpRequest',
    ];
    $params = ['limit' => $this->limit, 'offset' => $this->offset];
    $results = $this->handler->makeRequest($url, $params, $headers);
    $this->parseUserActivity($results);

    $this->offset += $this->limit;
  }

  public function parseUserActivity($result) {
    if (empty($result['html'])) {
      $this->endActivity = TRUE;
      return;
    }
    if (empty($result['count'])) {
      $this->endActivity = TRUE;
      return;
    }

    $events = $this->dom->loadStr($result['html'])->find('div.event-item');
    if (count($events) === 0) {
      $this->endActivity = TRUE;
      return;
    }
    foreach ($events as $event) {
      $event_type = trim($event->find('div.event-title')->find('span.event-type')->text);
      if (!in_array($event_type, ['pushed to branch'])) {
        continue;
      }

      [$project_name, $issue_number] = explode('-', $event->find('span.project-name')->text);
      if (in_array($issue_number, $this->issues)) {
        continue;
      }
      $this->issues[] = $issue_number;

      $time = $event->find('time')->text;
      $from = date('M d, Y', $this->fromTimestamp);
      $to = date('M d, Y', $this->toTimestamp);
      $timestamp = strtotime($time);

      if ($timestamp > $this->toTimestamp) {
        $this->handler->log(PHP_EOL . 'Activity was made at ' . $time . ', which is newer than requested, till ' . $to);
        // Still did not reach start date, latest activities newer than requested.
        continue;
      }

      if ($timestamp < $this->fromTimestamp) {
        $this->handler->log(PHP_EOL . 'Activity was made at ' . $time . ', which is older than requested, from ' . $from);
        // Reach start date, older activities are not required.
        $this->endActivity = TRUE;
        return;
      }

      $contrib_description = $event->find('div.event-title')->find('.text-truncate')->find('.ref-name')->text;
      $contrib_url = sprintf(self::DORG_ISSUE_BASE, $project_name, $issue_number);

      $this->pushes[] = [
        'user_email' => $this->handler->mapUser(self::DORG_USER_PROFILE_BASE . strtolower($this->userName)),
        'user_name' => $this->userName,
        'user_country' => '',
        'user_url' => '',
        'user_fio' => '',
        'contrib_url' => $contrib_url,
        'contrib_date' => $time,
        'contrib_type' => $event_type,
        'contrib_description' => $contrib_description
      ];
    }
  }

}
