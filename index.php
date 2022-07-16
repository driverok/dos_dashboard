<?php
require 'vendor/autoload.php';
use Dosdashboard\Parsers\Credits;
// @TODO: add args for time frame, company, user

$start  = mktime(0, 0, 0, date("m") -6  , 1, date("Y"));
$end  = mktime(0, 0, 0, date("m")  , 31, date("Y"));
$credit_parser = new Credits($start, $end);
echo "\n Starting gathering credits from " . date('d.m.Y', $start) . ' till ' . date('d.m.Y', $end);

$pushes = $credit_parser->getPushes();
$credit_parser->handler->writeCSV($pushes);

echo "\n Finish. (" . (count($pushes)) . ' credits)';
