<?php
header('Content-Type: application/json');
require 'db.php';

// 🔄 VAPAUTA VANHAT VARAUKSET (48h)
$pdo->query("
  UPDATE products
  SET status = 'available', reserved_at = NULL
  WHERE status = 'reserved'
  AND reserved_at < NOW() - INTERVAL 2 DAY
");

$stmt = $pdo->prepare("
    SELECT * FROM products
    WHERE status = 'available'
    OR (
        status = 'reserved'
        AND reserved_at > NOW() - INTERVAL 2 DAY
    )
    ORDER BY status
");

$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($products);