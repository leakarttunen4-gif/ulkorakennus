<?php
header('Content-Type: application/json');
require 'db.php';

// 🔹 Lue JSON input
$data = json_decode(file_get_contents("php://input"), true);

$product_id = $data['product_id'] ?? null;
$name = htmlspecialchars($data['name'] ?? '');
$contact = htmlspecialchars($data['contact'] ?? '');

if (!$product_id || !$name || !$contact) {
    echo json_encode(["success" => false]);
    exit;
}

// 🔹 Yritetään varata (estää tuplavaraukset)
$stmt = $pdo->prepare("
    UPDATE products
    SET status = 'reserved',
        reserved_at = NOW(),
        customer_name = :name,
        customer_contact = :contact
    WHERE id = :id AND status = 'available'
");

$stmt->execute([
    ':name' => $name,
    ':contact' => $contact,
    ':id' => $product_id
]);

if ($stmt->rowCount() === 0) {
    echo json_encode(["success" => false]);
    exit;
}

// 🔹 Haetaan tuotteen tiedot
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);


// =======================
// 📧 BREVO
// =======================

$apiKey = "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";

// 🔹 SINULLE lähtevä viesti
$sisalto_admin = "
<h3>Uusi varaus</h3>
<b>Tuote:</b> {$product['name']}<br>
<b>Hinta:</b> {$product['price']} €<br><br>

<b>Asiakas:</b> $name<br>
<b>Yhteystieto:</b> $contact
";

// 🔹 ASIAKKAALLE lähtevä viesti
$sisalto_asiakas = "
<h3>Varaus vahvistettu</h3>
Olet varannut tuotteen:<br><br>

<b>{$product['name']}</b><br>
Hinta: {$product['price']} €<br><br>

Nouda tuote 2 arkipäivän sisällä.<br><br>

Terveisin,<br>
Karelia Ulkorakennus Oy
";

// 🔹 FUNKTIO lähetykseen (ettei toisteta koodia)
function sendBrevoMail($toEmail, $toName, $subject, $htmlContent, $apiKey) {
    $data = [
        "sender" => [
            "name" => "Karelia Ulkorakennus Oy",
            "email" => "leakarttunen4@gmail.com"
        ],
        "to" => [
            [
                "email" => $toEmail,
                "name" => $toName
            ]
        ],
        "subject" => $subject,
        "htmlContent" => $htmlContent
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
}

// 🔹 Lähetetään sähköpostit

// SINULLE
sendBrevoMail(
    "lea.karttunen@edu.riveria.fi",
    "Karelia Ulkorakennus",
    "Uusi varaus: " . $product['name'],
    $sisalto_admin,
    $apiKey
);

// ASIAKKAALLE
// HUOM: toimii jos contact on sähköposti
if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
    sendBrevoMail(
        $contact,
        $name,
        "Varaus vahvistettu",
        $sisalto_asiakas,
        $apiKey
    );
}

echo json_encode(["success" => true]);