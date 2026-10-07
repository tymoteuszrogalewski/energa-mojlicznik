#!/bin/bash
# Pobiera wczoraj i dzis. Do crona, np. co godzine:  5 * * * *  /sciezka/energa-mojlicznik/go.sh
php "$(dirname "$0")/energa_day.php" "$@"
