<?php

namespace Dosdashboard\Parsers;


use Dosdashboard\Contribution;
use Dosdashboard\Handler;

Class Credits implements Contribution {
  public const BASE_ENDPOINT = 'https://www.drupal.org/api-d7/';
  public const COMMENTS_ENDPOINT = 'comment.json';
  public const NODE_ENDPOINT = 'node.json';
  public const USER_ENDPOINT = 'user.json';
  public const EPAM_ID = 2114867;
  public const CONTRIB_TYPE = 'credit';

  /**
   * @var \Dosdashboard\Handler
   */
  public Handler $handler;

  private $start_date;

  private $end_date;

  public function __construct($start_date, $end_date) {
    $this->handler = new Handler(self::CONTRIBUTION_TITLES, self::BASE_ENDPOINT);
    $this->start_date = $start_date;
    $this->end_date = $end_date;
  }

  public function getPushes() {
//    $comments = $parser->getCommentsByUser('driverok');
    $comments_by_company = $this->getCommentsByCompany(self::EPAM_ID);
    $comments_by_customer = $this->getCommentsByCustomer(self::EPAM_ID);
    $comments = array_merge($comments_by_company, $comments_by_customer);
    $comments_credited = [];
    echo "\n Comments gathered (" . count($comments). ')';
    foreach ($comments as $comment) {
      if (!$comment || empty($comment['node'])) {
        continue;
      }
      echo '.';
      $issue = $this->getNode($comment['node']['id'])['list'][0];
      $project = $this->getNode($issue['field_project']['id'])['list'][0];
      $user = $this->getUser($comment['author']['id'])['list'][0];

      if (empty($issue['field_issue_credit'])) {
        continue;
      }
      if (!$this->inTimeFrame($issue['changed'])) {
        continue;
      }

      if ($issue['field_issue_status'] !== '7' && $issue['field_issue_status'] !== '2') {
        continue;
      }

      $is_credited = array_filter($issue['field_issue_credit'], static function($v, $k) use ($comment) {return $v['id'] === $comment['cid'];}, ARRAY_FILTER_USE_BOTH);
      if ($is_credited) {
        $comments_credited[$issue['nid']] = $this->prepareResponse($issue, $project, $user);
      }
    }
    return $comments_credited;
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

  public function getNode($issue_id) {
    return $this->handler->makeRequest(self::NODE_ENDPOINT, ['nid' => $issue_id]);
  }

  public function getUser($user_id) {
    return $this->handler->makeRequest(self::USER_ENDPOINT, ['uid' => $user_id]);
  }

  public function prepareResponse($issue, $project, $user) {
    $project_usage = 0;
    if (!empty($project['project_usage'])) {
      foreach ($project['project_usage'] as $usage) {
        $project_usage += $usage;
      }
    }
    $mail_mapping = $this->handler->readMapping();
    $description_arr = [
      'project_nid' => $project['nid'] ?? '',
      'project_title' => $project['title'] ?? '',
      'project_short' => $project['field_project_machine_name'] ?? '',
      'project_type' => $project['type'] ?? '',
      'project_url' => $project['url'] ?? '',
      'project_usage' => $project_usage,
      'issue_id' => $issue['nid'] ?? '',
      'issue_title' => $issue['title'] ?? '',
      'issue_url' => $issue['url'] ?? '',
      'uid' => $user['uid'],
      'user_name' => $user['name'],
      'user_email' => $mail_mapping[$user['name']] ?? '',
      'user_country' => $user['field_country'],
      'user_url' => $user['url'],
      'user_fio' => $user['field_first_name'] . ' ' . $user['field_last_name'] ,
      'credit_date' => $issue['changed'] ?? '',
    ];
    return [
      'user_email' => $mail_mapping[$user['name']] ?? '',
      'user_name' => $user['name'],
      'user_country' => $user['field_country'],
      'user_url' => $user['url'],
      'user_fio' => $user['field_first_name'] . ' ' . $user['field_last_name'],
      'contrib_url' => $issue['url'] ?? '',
      'contrib_date' =>date('d.m.Y', $issue['changed']) ?? '',
      'contrib_type' => self::CONTRIB_TYPE,
      'contrib_description' => serialize($description_arr),
    ];
  }

  public function getHeaders() {
    return self::CONTRIBUTION_TITLES;
  }

  private function inTimeFrame($comment_date) {
    return ($comment_date >= $this->start_date && $comment_date <= $this->end_date);
  }

  private function getComments($params, $response = []): array {
    $comments = $this->handler->makeRequest(self::COMMENTS_ENDPOINT, $params);
    $response = array_merge($response, $comments['list']);
    if ($comments['self'] === $comments['last']) {
      return $response;
    }
    $last_comment_date = end($comments['list'])['created'];
    if ($last_comment_date <= $this->start_date) {
      return $response;
    }
    $params['page']++;
    return $this->getComments($params, $response);
  }

}
