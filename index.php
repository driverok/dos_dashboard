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
$drupalcode_parser = new Drupalcode('', $start_tm, $end_tm, $verbose, $mapping_file);

$credit_pushes = $credit_parser->getPushes();
echo "\n Gathered " . count($credit_parser->users) . ' users and ' . count($credit_pushes) . ' issue credits from Drupal.org';
foreach ($credit_parser->users as $user) {
  echo "\n Processing user " . $user['name'];
  $drupalcode_parser->setUser($user['name']);
  $drupalcode_parser->clearState();
  $drupalcode_pushes = $drupalcode_parser->getPushes();
  $credit_pushes = array_merge($credit_pushes, $drupalcode_pushes);
  echo ", found " . count($drupalcode_pushes) . ' pushes to gitlab';
}

// Filter unique contribs only
$unique_pushes = [];
foreach ($credit_pushes as $push) {
  $id = $push['user_name'] . '|' . $push['contrib_url'];
  isset($unique_pushes[$id]) or $unique_pushes[$id] = $push;
}

$credit_pushes = array_values($unique_pushes);
$credit_parser->handler->writeCSV($credit_pushes);
echo "\n" . date('H:i') . ' Finish. (' . (count($credit_pushes)) . ' credits). ' . $credit_parser->handler::CSV_FILENAME;

if (count($credit_parser->handler->unknown_users) > 0) {
  echo "\n" . date('H:i') . ' Found . (' . (count($credit_parser->handler->unknown_users)) . ' unknown users)';
  echo "'n" . implode(';', $credit_parser->handler->unknown_users);
}
