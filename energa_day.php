#!/usr/bin/env php
<?php
/**
 * energa_day.php — pobiera wczoraj i dzis (albo podany dzien).
 * Uzycie: php energa_day.php [YYYY-MM-DD]
 * Cron:   5 * * * *  /sciezka/go.sh
 */

require __DIR__ . '/energa.inc.php';

$tz   = new DateTimeZone(ENERGA_TZ);
$days = isset($argv[1])
      ? [$argv[1]]
      : [(new DateTime('yesterday', $tz))->format('Y-m-d'), (new DateTime('today', $tz))->format('Y-m-d')];

$db = energa_db();
if (!energa_login()) exit(1);

$fail = 0;
foreach ($days as $day) {
    $rows = energa_fetch($day);
    if ($rows === false) { $fail++; continue; }
    energa_save($db, $rows);
    if ($db) echo "$day: " . count($rows) . " godzin\n";
}
exit($fail ? 1 : 0);
