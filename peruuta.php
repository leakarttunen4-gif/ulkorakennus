<?php
// peruuta.php — Varauksen peruutus
require_once 'db.php';

$avain = trim($_GET['avain'] ?? '');
$virhe = '';
$onnistui = false;
$varaus = null;

if ($avain !== '') {
    $pdo = db();
     $stmt = $pdo->prepare(
    "SELECT v.id, v.aika_id, v.etunimi, v.sukunimi, v.email, v.puhelin, 
            v.osoite, v.palvelut, v.lisatiedot, v.tila, v.peruutusavain,
            a.paivamaara,
            TIME_FORMAT(a.alkuaika, '%H:%i') AS alku_aika,
            TIME_FORMAT(a.loppuaika, '%H:%i') AS loppu_aika,
            a.id AS aika_id
     FROM varaukset v
     JOIN ajat a ON a.id = v.aika_id
     WHERE v.peruutusavain = ?"
  );
  
    $stmt->execute([$avain]);
    $varaus = $stmt->fetch();
  
  // Lasketaan heti käytettäväksi kaikkialla
	if ($varaus) {
    	$pvm    = date('j.n.Y', strtotime($varaus['paivamaara']));
        $alku   = $varaus['alku_aika'];    
		$loppu  = $varaus['loppu_aika'];  
    	$viikonpaivat = ['Monday'=>'Maanantai','Tuesday'=>'Tiistai','Wednesday'=>'Keskiviikko',
                     'Thursday'=>'Torstai','Friday'=>'Perjantai','Saturday'=>'Lauantai','Sunday'=>'Sunnuntai'];
    	$vpnimi = $viikonpaivat[date('l', strtotime($varaus['paivamaara']))] ?? '';
	}

    if (!$varaus) {
        $virhe = 'Peruutuslinkkiä ei löydy tai se on jo käytetty.';
    } elseif ($varaus['tila'] === 'peruutettu') {
        $virhe = 'Tämä varaus on jo peruutettu.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vahvista'])) {
        // Tarkistetaan 24h raja
        $varausaika = strtotime($varaus['paivamaara'] . ' ' . $varaus['alku_aika']);
        if ($varausaika - time() < 86400) {
            $virhe = 'Varauksen voi perua viimeistään 24 tuntia ennen käyntiä. Ota yhteyttä suoraan puhelimitse.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE varaukset SET tila = 'peruutettu' WHERE peruutusavain = ?")->execute([$avain]);
                $pdo->prepare("UPDATE ajat SET varattu = 0 WHERE id = ?")->execute([$varaus['aika_id']]);
                $pdo->commit();
                $onnistui = true;
              
              
              // Sähköposti peruutuksesta (Brevo)
$apiKey = "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";

$sisalto = "
Hei {$varaus['etunimi']},<br><br>
Varauksesi <strong>$vpnimi $pvm klo $alku – $loppu</strong> on peruutettu.<br><br> 
Jos peruutus oli virheellinen tai haluat varata uuden ajan, ota yhteyttä:<br>
<a href=\"mailto:" . YRITYS_EMAIL . "\">" . YRITYS_EMAIL . "</a><br><br>
Ystävällisin terveisin,<br>
" . YRITYS_NIMI;
              
$data = [
    "sender" => [
        "name"  => YRITYS_NIMI,
        "email" => "leakarttunen4@gmail.com"
    ],
    "to" => [
        ["email" => $varaus['email'], "name" => "{$varaus['etunimi']} {$varaus['sukunimi']}"]
    ],
    "cc" => [
        ["email" => "lea.karttunen@edu.riveria.fi", "name" => YRITYS_NIMI]
    ],
    "subject"     => "Varaus peruutettu — Karelia Ulkorakennus",
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
curl_exec($ch);
curl_close($ch);

                
              
              
              
            } catch (Exception $e) {
                $pdo->rollBack();
                $virhe = 'Palvelinvirhe. Yritä uudelleen tai ota yhteyttä sähköpostitse.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Peruuta varaus – Karelia Ulkorakennus Oy</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <style>
    .peruuta-card {
      max-width: 540px;
      margin: 80px auto;
      background: var(--color-white);
      border-radius: var(--radius-lg);
      padding: 44px;
      box-shadow: var(--shadow-lg);
      text-align: center;
    }
    .peruuta-card h2 { margin-bottom: 12px; }
    .peruuta-card p  { color: var(--color-muted); margin-bottom: 24px; }
    .varaus-info {
      background: var(--color-bg-alt);
      border-radius: var(--radius);
      padding: 16px 20px;
      margin-bottom: 28px;
      text-align: left;
      font-size: 0.9rem;
    }
    .varaus-info strong { display: block; margin-bottom: 4px; color: var(--color-text); }
    .virhe-box {
      background: #fdecea;
      border: 1.5px solid #e74c3c;
      border-radius: var(--radius);
      padding: 16px 20px;
      color: #c0392b;
      font-size: 0.9rem;
      margin-bottom: 24px;
    }
    .onnistui-box {
      background: #e8f5e9;
      border: 1.5px solid var(--color-primary);
      border-radius: var(--radius);
      padding: 20px;
      color: var(--color-primary);
      font-size: 0.95rem;
      margin-bottom: 24px;
    }
  </style>
</head>
<body>
<nav class="site-nav">
  <div class="container">
    <a href="index.html" class="nav-logo">
      <span>Karelia Ulkorakennus Oy</span>
      Joensuu · Pohjois-Karjala
    </a>
  </div>
</nav>

<main>
  <div class="container">
    <div class="peruuta-card">
      <?php if ($avain === ''): ?>
        <div style="font-size:2.5rem;margin-bottom:16px;">⚠️</div>
        <h2>Virheellinen linkki</h2>
        <p>Peruutuslinkki puuttuu. Tarkista sähköpostiviestissä oleva linkki.</p>
        <a href="ajanvaraus.html" class="btn btn-primary">Takaisin ajanvaraukseen</a>

      <?php elseif ($onnistui): ?>
        <div style="font-size:2.5rem;margin-bottom:16px;">✅</div>
        <h2>Varaus peruutettu</h2>
        <div class="onnistui-box">Varauksesi on peruutettu.</div>
        <p>Haluatko varata uuden ajan?</p>
        <a href="ajanvaraus.html" class="btn btn-primary">Varaa uusi aika</a>

      <?php elseif ($virhe !== ''): ?>
        <div style="font-size:2.5rem;margin-bottom:16px;">❌</div>
        <h2>Peruutus epäonnistui</h2>
        <div class="virhe-box"><?= htmlspecialchars($virhe) ?></div>
        <a href="ajanvaraus.html" class="btn btn-secondary">Takaisin</a>

      <?php elseif ($varaus): ?>
        <div style="font-size:2.5rem;margin-bottom:16px;">🗓️</div>
        <h2>Peruuta varaus</h2>
        <p>Haluatko varmasti perua seuraavan varauksen?</p>
        <div class="varaus-info">
          <strong><?= htmlspecialchars($varaus['etunimi'] . ' ' . $varaus['sukunimi']) ?></strong>
          <?= htmlspecialchars("$vpnimi $pvm klo " . $alku . "–" . $loppu) ?><br> 
          Osoite: <?= htmlspecialchars($varaus['osoite']) ?>
        </div>
        <form method="POST">
          <input type="hidden" name="vahvista" value="1">
          <button type="submit" class="btn btn-primary btn-full" style="background:#e74c3c;border-color:#e74c3c;margin-bottom:12px;">Peruuta varaus</button>
        </form>
        <a href="ajanvaraus.html" class="btn btn-ghost btn-full">Älä peruuta — palaa takaisin</a>
      <?php endif; ?>
    </div>
  </div>
</main>

<!--#include file="footer.shtml" -->
</body>
</html>
