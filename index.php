<?php
require 'vendor/autoload.php';
use Dosdashboard\Parsers\Credits;
use Dosdashboard\Parsers\Drupalcode;

$shortopts = "c:";
$shortopts .= "u:";
$shortopts .= "sd::";
$shortopts .= "ed::";
$shortopts .= "mf::";
$shortopts .= "v:";

$longopts  = [
  "company:",
  "verbose:",
  "user:",
  "start::",
  "end::",
  "mapping-file::",
];
$options = getopt($shortopts, $longopts);
$company = $options['company'] ?? $options['c'] ?? NULL;
$user = $options['user'] ?? $options['u'] ?? NULL;
$start = $options['start'] ?? $options['sd'] ?? NULL;
$end = $options['end'] ?? $options['ed'] ?? NULL;
$verbose = $options['verbose'] ?? $options['v'] ?? FALSE;
$mapping_file = $options['mapping-file'] ?? $options['mf'] ?? FALSE;
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

$credit_parser = new Credits($company, $user, $start_tm, $end_tm, $verbose, $mapping_file);
$drupalcode_parser = new Drupalcode('', $start_tm, $end_tm);

$credit_pushes = $credit_parser->getPushes();
echo "\n Gathered " . count($credit_parser->users) . ' users';
foreach ($credit_parser->users as $user) {
  echo "\n Processing user " . $user['name'];
  $drupalcode_parser->setUser($user['name']);
  $drupalcode_parser->clearState();
  $drupalcode_pushes = $drupalcode_parser->getPushes();
  $credit_pushes = array_merge($credit_pushes, $drupalcode_pushes);
  echo ", found " . count($drupalcode_pushes) . ' pushes to gitlab';
}
echo "\n Found " . count($credit_pushes) . ' pushes';die();
//$pushes = array_merge($credit_pushes, $drupalcode_pushes);
$credit_parser->handler->writeCSV($pushes);

echo "\n" . date('H:i') . ' Finish. (' . (count($pushes)) . ' credits). ' . $credit_parser->handler::CSV_FILENAME;
