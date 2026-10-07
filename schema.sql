-- Energa Mój Licznik — godzinowe zuzycie energii.
-- Uzycie: mysql energa < schema.sql
-- Kazdy wiersz = jedna godzina; ts_* = POCZATEK godziny (np. 14:00 = zuzycie 14:00-15:00).
-- Kluczem jest czas UTC, bo przy zmianie czasu na zimowy godzina 02:00 czasu polskiego wystepuje dwa razy.

CREATE TABLE IF NOT EXISTS `energa_hourly` (
  `ts_utc`     datetime NOT NULL,
  `ts_local`   datetime NOT NULL,
  `kwh_import` decimal(10,5) DEFAULT NULL,  -- A+  pobor z sieci
  `kwh_export` decimal(10,5) DEFAULT NULL,  -- A-  oddanie do sieci (PV), gdy ENERGA_EXPORT = true
  PRIMARY KEY (`ts_utc`),
  KEY `idx_local` (`ts_local`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Przyklad: zuzycie dzienne
-- SELECT DATE(ts_local) dzien, SUM(kwh_import) kwh FROM energa_hourly GROUP BY dzien ORDER BY dzien DESC;
