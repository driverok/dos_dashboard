<?php
require 'src/Parser.php';
use Dosdashboard\Parser;

$parser = new Parser(20);
echo "\n Starting...";
$comments = $parser->getCommentsByUser('driverok');
//$comments_by_company = $parser->getCommentsByCompany($parser::EPAM_ID);
//$comments_by_customer = $parser->getCommentsByCustomer($parser::EPAM_ID);
//$comments = array_merge($comments_by_company, $comments_by_customer);
$comments_credited = [];
echo "\n Comments gathered (" . count($comments). ')';
foreach ($comments as $comment) {
  if (!$comment || empty($comment['node'])) {
    continue;
  }
  echo '.';
  $issue = $parser->getNode($comment['node']['id'])['list'][0];
  $project = $parser->getNode($issue['field_project']['id'])['list'][0];
  $user = $parser->getUser($comment['author']['id'])['list'][0];


  if (empty($issue['field_issue_credit'])) {
    continue;
  }

  if ($issue['field_issue_status'] !== '7' && $issue['field_issue_status'] !== '2') {
    continue;
  }

  $is_credited = array_filter($issue['field_issue_credit'], static function($v, $k) use ($comment) {return $v['id'] === $comment['cid'];}, ARRAY_FILTER_USE_BOTH);
  if ($is_credited) {
    $comments_credited[$issue['nid']] = $parser->prepareResponse($comment, $issue, $project, $user);
  }
}
$parser->writeCSV($comments_credited);
echo "\n Finish. (" . (count($comments_credited)) . ' credits)';
