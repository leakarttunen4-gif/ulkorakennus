<?php

if ($_SERVER["REQUEST_METHOD"] == "POST") {

$nimi = htmlspecialchars($_POST["nimi"]);
$puhelin = htmlspecialchars($_POST["puhelin"]);
$email = htmlspecialchars($_POST["email"]);
$aihe = htmlspecialchars($_POST["aihe"]);
$viesti = htmlspecialchars($_POST["viesti"]);

$apiKey = "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";

$vastaanottaja = "lea.karttunen@edu.riveria.fi";

$sisalto = "
Nimi: $nimi<br>
Puhelin: $puhelin<br>
Sähköposti: $email<br>
Aihe: $aihe<br><br>
Viesti:<br>
$viesti
";

$data = [
  "sender" => [
    "name" => "Karelia Ulkorakennus Oy",
    "email" => "leakarttunen4@gmail.com"
  ],
  "to" => [
    [
      "email" => $vastaanottaja,
      "name" => "Karelia Ulkorakennus"
    ]
  ],
  "subject" => "Yhteydenotto verkkosivuilta",
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

$response = curl_exec($ch);

curl_close($ch);

header("Location: kiitos.html");
exit;

}

?>