<?php
require 'vendor/autoload.php';
use Dosdashboard\Parsers\Credits;
$shortopts = "c:";
$shortopts .= "u:";
$shortopts .= "sd::";
$shortopts .= "ed::";

$longopts  = [
  "company:",
  "user:",
  "start::",
  "end::",
];
$options = getopt($shortopts, $longopts);
$company = $options['company'] ?? $options['c'] ?? NULL;
$user = $options['user'] ?? $options['u'] ?? NULL;
$start = $options['start'] ?? $options['sd'] ?? NULL;
$end = $options['end'] ?? $options['ed'] ?? NULL;

if (!empty($start) && !$start_tm = strtotime($start)) {
  die('Wrong date format for start date');
}
if (!empty($end) && !$end_tm = strtotime($end)) {
  die('Wrong date format for end date');
}
if (empty($company) && empty($user)) {
  die('Please provide company ID or username from Drupal.org');
}

if (!empty($company) && !empty($user)) {
  die('Please provide either company ID or username from Drupal.org');
}


$credit_parser = new Credits($company, $user, $start_tm, $end_tm);
echo "\n Starting gathering credits from " . $start . ' till ' . $end ;

$pushes = $credit_parser->getPushes();
$credit_parser->handler->writeCSV($pushes);

echo "\n Finish. (" . (count($pushes)) . ' credits)';
