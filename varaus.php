<?php
// varaus.php — Varauksen käsittely (AJAX POST)
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'virhe' => 'Väärä pyyntötyyppi.']);
    exit;
}

// --- Syötteen siivous ---
function siisti(string $s): string {
    return trim(htmlspecialchars($s, ENT_QUOTES, 'UTF-8'));
}

$aika_id   = filter_input(INPUT_POST, 'aika_id',   FILTER_VALIDATE_INT);
$etunimi   = siisti($_POST['etunimi']   ?? '');
$sukunimi  = siisti($_POST['sukunimi']  ?? '');
$email     = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$puhelin   = siisti($_POST['puhelin']   ?? '');
$osoite    = siisti($_POST['osoite']    ?? '');
$palvelut  = [];
if (!empty($_POST['palvelu']) && is_array($_POST['palvelu'])) {
    $sallitut = ['terassi','pergola','piharakennus','aita','piharemontti','muu'];
    foreach ($_POST['palvelu'] as $p) {
        if (in_array($p, $sallitut, true)) $palvelut[] = $p;
    }
}
$palvelut_str = implode(', ', $palvelut);
$lisatiedot   = siisti($_POST['lisatiedot'] ?? '');

// --- Validointi ---
$virheet = [];
if (!$aika_id)           $virheet[] = 'Valitse varausaika.';
if ($etunimi === '')      $virheet[] = 'Etunimi puuttuu.';
if ($sukunimi === '')     $virheet[] = 'Sukunimi puuttuu.';
if (!$email)             $virheet[] = 'Sähköpostiosoite on virheellinen.';
if ($puhelin === '')      $virheet[] = 'Puhelinnumero puuttuu.';
if ($osoite === '')       $virheet[] = 'Osoite puuttuu.';
if (empty($palvelut))    $virheet[] = 'Valitse vähintään yksi palvelu.';

if (!empty($virheet)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'virhe' => implode(' ', $virheet)]);
    exit;
}

$pdo = db();

// --- Tarkista että aika on vapaa ---
$stmt = $pdo->prepare(
   "SELECT a.id, a.paivamaara, 
       TIME_FORMAT(a.alkuaika, '%H:%i') AS alkuaika,
       TIME_FORMAT(a.loppuaika, '%H:%i') AS loppuaika,
       r.nimi AS rooli
	FROM ajat a
	JOIN roolit r ON r.id = a.rooli_id
	WHERE a.id = ? AND a.varattu = 0
	FOR UPDATE"
	);

$pdo->beginTransaction();
try {
    $stmt->execute([$aika_id]);
    $aika = $stmt->fetch();

    if (!$aika) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['ok' => false, 'virhe' => 'Valittu aika on jo varattu tai ei enää saatavilla. Valitse toinen aika.']);
        exit;
    }

    // Merkitään aika varatuksi
    $pdo->prepare("UPDATE ajat SET varattu = 1 WHERE id = ?")->execute([$aika_id]);

    // Luodaan peruutusavain
    $peruutusavain = bin2hex(random_bytes(32));

    // Tallennetaan varaus
    $pdo->prepare(
        "INSERT INTO varaukset (aika_id, etunimi, sukunimi, email, puhelin, osoite, palvelut, lisatiedot, peruutusavain)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([$aika_id, $etunimi, $sukunimi, $email, $puhelin, $osoite, $palvelut_str, $lisatiedot, $peruutusavain]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'virhe' => 'Palvelinvirhe. Yritä uudelleen.']);
    exit;
}

// --- Sähköposti asiakkaalle (Brevo) ---
$apiKey = "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";  
 
$peruutus_url = SIVUSTO_URL . '/peruuta.php?avain=' . urlencode($peruutusavain);
$pvm  = date('j.n.Y', strtotime($aika['paivamaara']));
$viikonpaivat = ['Monday'=>'Maanantai','Tuesday'=>'Tiistai','Wednesday'=>'Keskiviikko',
                 'Thursday'=>'Torstai','Friday'=>'Perjantai','Saturday'=>'Lauantai','Sunday'=>'Sunnuntai'];
$vpnimi = $viikonpaivat[date('l', strtotime($aika['paivamaara']))] ?? '';
$alku  = substr($aika['alkuaika'], 0, 5);
$loppu = substr($aika['loppuaika'], 0, 5); 
  
$sisalto = "
<strong>Varausvahvistus — Karelia Ulkorakennus Oy</strong><br><br>
Hei $etunimi,<br><br>
Varauksesi on vahvistettu!<br><br>
<strong>Kartoituskäynti:</strong> $vpnimi $pvm klo $alku – $loppu<br>
<strong>Osoite:</strong> $osoite<br>
<strong>Palvelut:</strong> $palvelut_str<br><br>
Peruutuslinkki (viimeistään 24 h ennen käyntiä):<br>
<a href=\"$peruutus_url\">$peruutus_url</a><br><br>
Ystävällisin terveisin,<br>
Karelia Ulkorakennus Oy
";

$data = [
    "sender" => [
        "name"  => YRITYS_NIMI,
        "email" => "leakarttunen4@gmail.com"  // sama lähettäjä kuin muuallakin
    ],
    "to" => [
        ["email" => $email, "name" => "$etunimi $sukunimi"]
    ],
    "cc" => [
        ["email" => "lea.karttunen@edu.riveria.fi", "name" => YRITYS_NIMI]
    ],
    "subject"     => "Varausvahvistus — Karelia Ulkorakennus",
    "htmlContent" => $sisalto
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.brevo.com/v3/smtp/email");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "accept: application/json",
    "api-key: $apiKey",
    "content-type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
$brevo_vastaus = curl_exec($ch);
curl_close($ch);

echo json_encode([
    'ok'     => true,
    'viesti' => "Varaus vahvistettu! Vahvistus lähetetty osoitteeseen $email.",
    'pvm'    => "$vpnimi $pvm klo $alku–$loppu"
]);

 