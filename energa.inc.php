<?php
/**
 * energa.inc.php — biblioteka: logowanie do Energa Mój Licznik, pobranie
 * godzinowego zuzycia (A+) i oddania do sieci (A-), zapis do MySQL/MariaDB lub CSV.
 */

$cfg = __DIR__ . '/config.php';
if (!file_exists($cfg)) {
    fwrite(STDERR, "Brak config.php — skopiuj config.example.php do config.php i uzupelnij.\n");
    exit(1);
}
require $cfg;

const ENERGA_URL = 'https://mojlicznik.energa-operator.pl/dp';
const ENERGA_UA  = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';
const ENERGA_TZ  = 'Europe/Warsaw';

$ENERGA_JAR   = sys_get_temp_dir() . '/energa_cookie_' . md5(ENERGA_USER) . '.txt';
$ENERGA_METER = ENERGA_METER;  // puste = wykryj automatycznie po zalogowaniu

function energa_http($url, $post = null) {
    global $ENERGA_JAR;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE     => $ENERGA_JAR,
        CURLOPT_COOKIEJAR      => $ENERGA_JAR,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => ENERGA_UA,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Referer: ' . ENERGA_URL . '/UserLogin.do']);
    }
    $body = curl_exec($ch);
    curl_close($ch);
    return $body;
}

// Logowanie: strona logowania (cookie + token _antixsrf), potem POST z loginem.
function energa_login() {
    global $ENERGA_JAR, $ENERGA_METER;
    @unlink($ENERGA_JAR);

    $page = energa_http(ENERGA_URL . '/UserLogin.do');
    if (!$page) { fwrite(STDERR, "Energa: brak odpowiedzi strony logowania\n"); return false; }
    preg_match('/name="_antixsrf"\s+value="([^"]*)"/', $page, $m);

    $resp = energa_http(ENERGA_URL . '/UserLogin.do', [
        'j_username'   => ENERGA_USER,
        'j_password'   => ENERGA_PASS,
        'loginNow'     => 'zaloguj się',
        'selectedForm' => '1',
        'save'         => 'save',
        'clientOS'     => 'web',
        'rememberMe'   => 'on',
        '_antixsrf'    => $m[1] ?? '',
    ]);
    if ($resp && stripos($resp, 'captcha') !== false) {
        fwrite(STDERR, "Energa: wymagana CAPTCHA — zaloguj sie raz recznie w przegladarce i sprobuj ponownie\n");
        return false;
    }

    // Numer licznika (meterPoint) — ze strony konta, gdy nie podany w config.php
    if ($ENERGA_METER === '') {
        $page = energa_http(ENERGA_URL . '/UserData.do');
        if (!preg_match('/meters\.list\.push\(\{\s*id:\s*(\d+)/', (string)$page, $m)) {
            fwrite(STDERR, "Energa: nie wykryto numeru licznika — sprawdz login/haslo albo wpisz ENERGA_METER\n");
            return false;
        }
        $ENERGA_METER = $m[1];
        fwrite(STDERR, "Energa: wykryty licznik (meterPoint) $ENERGA_METER\n");
    }
    return true;
}

// Pobiera jeden dzien (YYYY-MM-DD, czas polski). $mo: 'A+' pobor, 'A-' oddanie.
// Zwraca [ts_utc => kwh] (ts = poczatek godziny) albo false gdy sesja/odpowiedz zla.
function energa_day($date, $mo = 'A+') {
    global $ENERGA_METER;
    $ms = (new DateTime($date, new DateTimeZone(ENERGA_TZ)))->getTimestamp() * 1000;
    $url = ENERGA_URL . '/resources/chart?mainChartDate=' . $ms . '&type=DAY'
         . '&meterPoint=' . urlencode($ENERGA_METER) . '&mo=' . urlencode($mo);

    $data = json_decode((string)energa_http($url), true);
    if (!isset($data['response'])) return false;

    $rows = [];
    foreach ($data['response']['mainChart'] ?? [] as $frame) {
        // taryfy wielostrefowe (G12, G12w...) — zuzycie godziny jest w jednej ze stref
        $zones = array_filter($frame['zones'] ?? [], fn($z) => $z !== null);
        if (!$zones) continue;
        $ts = gmdate('Y-m-d H:i:s', intdiv((int)$frame['tm'], 1000));
        $rows[$ts] = round(array_sum($zones), 5);
    }
    return $rows;
}

// Pobiera dzien z ponownym logowaniem, gdy sesja wygasla.
function energa_fetch($date) {
    $imp = energa_day($date, 'A+');
    if ($imp === false) {
        if (!energa_login()) return false;
        $imp = energa_day($date, 'A+');
        if ($imp === false) { fwrite(STDERR, "Energa: zla odpowiedz dla $date (login/haslo/nr licznika?)\n"); return false; }
    }
    $exp = ENERGA_EXPORT ? (energa_day($date, 'A-') ?: []) : [];

    $out = [];
    foreach (array_unique(array_merge(array_keys($imp), array_keys($exp))) as $ts) {
        $out[$ts] = ['import' => $imp[$ts] ?? null, 'export' => $exp[$ts] ?? null];
    }
    ksort($out);
    return $out;
}

function energa_db() {
    if (DB_NAME === '') return null;  // tryb CSV
    try {
        return new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    } catch (mysqli_sql_exception $e) {
        fwrite(STDERR, "DB: " . $e->getMessage() . "\n");
        exit(1);
    }
}

// Zapis do tabeli energa_hourly albo wypis CSV na stdout, gdy brak bazy.
function energa_save($db, $rows) {
    $tz = new DateTimeZone(ENERGA_TZ);
    foreach ($rows as $ts => $r) {
        $local = (new DateTime($ts, new DateTimeZone('UTC')))->setTimezone($tz)->format('Y-m-d H:i:s');
        if (!$db) {
            echo "$ts;$local;" . ($r['import'] ?? '') . ';' . ($r['export'] ?? '') . "\n";
            continue;
        }
        $imp = $r['import'] === null ? 'NULL' : (float)$r['import'];
        $exp = $r['export'] === null ? 'NULL' : (float)$r['export'];
        $db->query("INSERT INTO energa_hourly (ts_utc, ts_local, kwh_import, kwh_export)
                    VALUES ('$ts', '$local', $imp, $exp)
                    ON DUPLICATE KEY UPDATE ts_local = VALUES(ts_local),
                        kwh_import = COALESCE(VALUES(kwh_import), kwh_import),
                        kwh_export = COALESCE(VALUES(kwh_export), kwh_export)");
    }
}
