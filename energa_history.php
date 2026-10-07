#!/usr/bin/env php
<?php
/**
 * energa_history.php — pobiera cala historie dzien po dniu (np. rok, dwa lata).
 * Uzycie: php energa_history.php OD [DO]       np. php energa_history.php 2024-01-01
 * DO domyslnie = dzis. Mozna przerwac i uruchomic ponownie — dane sie nadpisza, nic sie nie zdubluje.
 */

require __DIR__ . '/energa.inc.php';

if (!isset($argv[1])) {
    fwrite(STDERR, "Uzycie: php energa_history.php OD [DO]   (daty YYYY-MM-DD)\n");
    exit(1);
}

$tz   = new DateTimeZone(ENERGA_TZ);
$from = new DateTime($argv[1], $tz);
$to   = new DateTime($argv[2] ?? 'today', $tz);

$db = energa_db();
if (!energa_login()) exit(1);

$total = 0;
$empty = 0;
for ($d = clone $from; $d <= $to; $d->modify('+1 day')) {
    $day  = $d->format('Y-m-d');
    $rows = energa_fetch($day);
    if ($rows === false) exit(1);
    energa_save($db, $rows);
    $total += count($rows);
    if (!$rows) $empty++;
    if ($db) echo "$day: " . count($rows) . " godzin\n";
    sleep(ENERGA_PAUSE);  // nie obciazamy portalu
}
if ($db) echo "Gotowe: $total godzin, dni bez danych: $empty\n";
