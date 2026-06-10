<?php
// hae-ajat.php — AJAX-rajapinta vapaiden aikojen hakemiseen
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once 'db.php';

$toiminto = $_GET['toiminto'] ?? '';

$pdo = db();

// --- Hae roolit ---
if ($toiminto === 'roolit') {
    $roolit = $pdo->query(
        "SELECT id, nimi, kuvaus FROM roolit WHERE aktiivinen = 1 ORDER BY nimi"
    )->fetchAll();
    echo json_encode(['ok' => true, 'roolit' => $roolit]);
    exit;
}

// --- Hae vapaat ajat kuukaudelle ---
if ($toiminto === 'ajat') {
    $rooli_id = filter_input(INPUT_GET, 'rooli_id', FILTER_VALIDATE_INT);
    $vuosi    = filter_input(INPUT_GET, 'vuosi',    FILTER_VALIDATE_INT);
    $kuukausi = filter_input(INPUT_GET, 'kuukausi', FILTER_VALIDATE_INT);

    if (!$rooli_id || !$vuosi || $kuukausi < 1 || $kuukausi > 12) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'virhe' => 'Virheelliset parametrit.']);
        exit;
    }

    $kuukausi_str = sprintf('%04d-%02d', $vuosi, $kuukausi);

    $stmt = $pdo->prepare(
        "SELECT id, paivamaara, alkuaika, loppuaika
         FROM ajat
         WHERE rooli_id = ?
           AND varattu   = 0
           AND DATE_FORMAT(paivamaara, '%Y-%m') = ?
           AND paivamaara >= CURDATE()
         ORDER BY paivamaara, alkuaika"
    );
    $stmt->execute([$rooli_id, $kuukausi_str]);
    $rivit = $stmt->fetchAll();

    // Ryhmittele päivämäärän mukaan
    $ajat = [];
    foreach ($rivit as $r) {
        $pvm = $r['paivamaara'];
        $ajat[$pvm][] = [
            'id'        => (int)$r['id'],
            'alkuaika'  => $r['alkuaika'],
            'loppuaika' => $r['loppuaika'],
        ];
    }

    echo json_encode(['ok' => true, 'ajat' => $ajat]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'virhe' => 'Tuntematon toiminto.']);
