<?php
// Skopiuj do config.php i uzupelnij. config.php jest w .gitignore.

// Konto Energa Mój Licznik (mojlicznik.energa-operator.pl)
define('ENERGA_USER',   'user@example.com');
define('ENERGA_PASS',   'password');
define('ENERGA_METER',  '');           // meterPoint; puste = wykryj automatycznie (pierwszy licznik na koncie)
define('ENERGA_EXPORT', false);        // true = pobieraj tez energie oddana do sieci (A-), np. przy PV
define('ENERGA_PAUSE',  2);            // przerwa w sekundach miedzy dniami przy pobieraniu historii

// Baza MySQL / MariaDB. DB_NAME = '' -> zamiast zapisu do bazy wypisuje CSV na ekran.
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_USER', 'energa');
define('DB_PASS', 'password');
define('DB_NAME', 'energa');
