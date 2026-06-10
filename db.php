<?php
// db.php — Tietokantayhteys
// MUOKKAA nämä tiedot omiksi ennen käyttöönottoa

define('DB_HOST', 'localhost');
define('DB_NAME', 'karelia');   // tietokantasi nimi
define('DB_USER', 'lea'); // tietokantakäyttäjä
define('DB_PASS', 'karelia10$');      // tietokantasalasana
define('DB_CHARSET', 'utf8mb4');

// Admin-salasana (vaihda tämä!)
define('ADMIN_SALASANA', 'GnJcng9FkYQmSliMeHS5');

// Sähköpostiasetukset
define('YRITYS_EMAIL',  'lea.karttunen@edu.riveria.fi');
define('YRITYS_NIMI',   'Karelia Ulkorakennus Oy');
define('SIVUSTO_URL',   'https://te26kl.okuserveri.com/karelia-ulkorakennus'); // ilman loppuvinoviivaa

// Varauksen kesto minuuteissa
define('VARAUS_KESTO_MIN', 60);

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['ok' => false, 'virhe' => 'Tietokantayhteysvirhe.']));
        }
    }
    return $pdo;
}
