#!/bin/bash
# Pobiera cala historie dzien po dniu.  Np.:  ./history.sh 2024-01-01   albo   ./history.sh 2024-01-01 2024-12-31
php "$(dirname "$0")/energa_history.php" "$@"
