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
use function in_array;
use function json_decode;
use function str_replace;
use function strtotime;
use function time;
use function trim;
use const PHP_EOL;

class Drupalcode implements Contribution {

  public const CSV_FILENAME = '/tmp/contribution_credits.csv';

  public function __construct($user_name, $from_timestamp, $to_timestamp) {
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

  public function getPushes() {
    while (!$this->endActivity) {
      $this->getUserActivity();
    }

  return $this->pushes;
  }

  public function writeCSV($results) {
    $fp = fopen(self::CSV_FILENAME, 'w');

    foreach ($results as $fields) {
      fputcsv($fp, $fields);
    }

    fclose($fp);
  }

  public function getUserActivity() {
    $results = [];

    $ch = curl_init();
    // Let's use xjm user for reference.
    curl_setopt($ch, CURLOPT_URL, 'https://git.drupalcode.org/users/' . $this->userName . '/activity?limit=' . $this->limit. '&offset=' . $this->offset);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Accept: application/json, text/plain, */*',
      'User-Agent: Postman',
      'X-Requested-With: XMLHttpRequest'
    ]);

    $this->parseUserActivity(json_decode(curl_exec($ch)));
    curl_close($ch);

    $this->offset += $this->limit;
  }

  public function parseUserActivity($result) {
    $events = $this->dom->loadStr($result->html)->find('div.event-item');
    foreach ($events as $event) {
      $time = $event->find('time')->text;
      $from = date('M d, Y', $this->fromTimestamp);
      $to = date('M d, Y', $this->toTimestamp);
      $timestamp = strtotime($time);

      if ($timestamp > $this->toTimestamp) {
        echo PHP_EOL . 'Acitivity was made at ' . $time . ', which is newer than requested, till ' . $to;
        // Still did not reach start date, latest activities newer than requested.
        continue;
      }

      if ($timestamp < $this->fromTimestamp) {
        echo PHP_EOL . 'Acitivity was made at ' . $time . ', which is older than requested, from ' . $from;
        // Reach start date, older activities are not required.
        $this->endActivity = TRUE;
        return;
      }

      $project_name = $event->find('div.event-title')->find('span.event-scope')->find('a')->title;
      $project_html = $this->getProjectHtml($project_name);
      $project_dom = $this->dom->loadStr($project_html);
      // $project_id = $project_dom->find('body')->getAttribute('data-project-id');

      $event_type = trim($event->find('div.event-title')->find('span.event-type')->text);
      if (!in_array($event_type, ['pushed to branch', 'opened'])) {
        continue;
      }

      if ($event_type === 'opened') {
        $event_type = $event_type . ' ' . $event->find('div.event-title')->find('span.event-target-type')->text;
        $contrib_description = $event->find('div.event-title')->find('.event-target-title')->text;
        $contrib_url = 'https://git.drupalcode.org' . $event->find('div.event-title')->find('.event-target-link')->href;
      }

      if ($event_type === 'pushed to branch') {
        $event_type = $event_type . ' ' . $event->find('div.event-title')->find('.text-truncate')->find('.ref-name')->text;
        $contrib_description = $event->find('.commit-row-title')->text;
        $contrib_url = 'https://git.drupalcode.org' . $event->find('.event-body')->find('.commit-sha')->href;
      }

      $this->pushes[] = [
        'user_email' => '',
        'user_name' => $this->userName,
        'user_country' => '',
        'user_url' => '',
        'user_fio' => '',
        'contrib_url' => $contrib_url,
        'contrib_date' => $time,
        'contrib_type' => $event_type,
        'contrib_description' => $contrib_description
      ];
      echo PHP_EOL . 'Total Drupalcode pushes: ' . count($this->pushes);
    }
  }

  private function getProjectHtml($project_name) {
    $project_ch = curl_init();
    curl_setopt($project_ch, CURLOPT_URL, 'https://git.drupalcode.org/project/' . $project_name);
    curl_setopt($project_ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($project_ch, CURLOPT_HTTPHEADER, [
      'Accept: application/json, text/plain, */*',
      'User-Agent: Postman',
      'X-Requested-With: XMLHttpRequest'
    ]);

    $project_html = curl_exec($project_ch);
    // Handles forks.
    if(curl_getinfo($project_ch, CURLINFO_HTTP_CODE) !== 200) {
      $project_html = $this->getForkProjectHtml($project_name);
    }
    curl_close($project_ch);

    return $project_html;
  }

  private function getForkProjectHtml($project_name) {
    $fork_ch = curl_init();
    curl_setopt($fork_ch, CURLOPT_URL, 'https://git.drupalcode.org/issue/' . $project_name);
    curl_setopt($fork_ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($fork_ch, CURLOPT_HTTPHEADER, [
      'Accept: application/json, text/plain, */*',
      'User-Agent: Postman',
      'X-Requested-With: XMLHttpRequest'
    ]);

    $project_html = curl_exec($fork_ch);
    curl_close($fork_ch);

    return $project_html;
  }

}
