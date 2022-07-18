<?php
require 'vendor/autoload.php';
use Dosdashboard\Parsers\Credits;
$shortopts = "c:";
$shortopts .= "u:";
$shortopts .= "sd::";
$shortopts .= "ed::";
$shortopts .= "v::";

$longopts  = [
  "company:",
  "verbose:",
  "user:",
  "start::",
  "end::",
];
$options = getopt($shortopts, $longopts);
$company = $options['company'] ?? $options['c'] ?? NULL;
$user = $options['user'] ?? $options['u'] ?? NULL;
$start = $options['start'] ?? $options['sd'] ?? NULL;
$end = $options['end'] ?? $options['ed'] ?? NULL;
$verbose = $options['verbose'] ?? $options['v'] ?? FALSE;
$start_tm = strtotime($start);
$end_tm = strtotime($end);

if (!empty($start) && !$start_tm) {
  die('Wrong date format for start date');
}
if (!empty($end) && !$end_tm) {
  die('Wrong date format for end date');
}
if (empty($company) && empty($user)) {
  die('Please provide company ID or username from Drupal.org');
}

if (!empty($company) && !empty($user)) {
  die('Please provide either company ID or username from Drupal.org');
}

echo "\n" . date('H:i') . ' Gathering credits from ' . $start . ' till ' . $end . ' ...';

$credit_parser = new Credits($company, $user, $start_tm, $end_tm, $verbose);
$pushes = $credit_parser->getPushes();
$credit_parser->handler->writeCSV($pushes);

echo "\n" . date('H:i') . ' Finish. (' . (count($pushes)) . ' credits). ' . $credit_parser->handler::CSV_FILENAME;
