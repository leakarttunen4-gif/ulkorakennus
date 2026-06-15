<?php
// admin.php — Hallintapaneeli
session_start();
require_once 'db.php';

// 🔴 POISTA TUOTE
if (isset($_POST['delete_id'])) {
    $id = $_POST['delete_id'];

    // Hae tuote ja kuva
    $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    // Poista kuva tiedostosta
    if ($product && $product['image']) {
        $filePath = "../" . $product['image'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // Poista tuote tietokannasta
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);

    // Välitön uudelleenlataus, jotta lista päivittyy
    header("Location: admin.php");
    exit;
}

// 🟡 MERKITSE MYYDYKSI
if (isset($_POST['sold_id'])) {
    $id = $_POST['sold_id'];

    $stmt = $pdo->prepare("UPDATE products SET status = 'sold' WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: admin.php");
    exit;
}

// ➕ LISÄÄ TUOTE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $name = $_POST['name'];
    $price = $_POST['price'];

    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $targetDir = "../uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $fileName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
            $imagePath = "uploads/" . $fileName;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO products (name, price, image) VALUES (?, ?, ?)");
    $stmt->execute([$name, $price, $imagePath]);

    header("Location: admin.php");
    exit;
}
?>


<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Admin</title>

  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f5f7fa;
      padding: 20px;
    }

    h2 {
      margin-top: 40px;
      color: #333;
    }

    .card {
      background: #fff;
      border-radius: 10px;
      padding: 15px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .form-card {
      max-width: 400px;
    }

    input, button {
      padding: 10px;
      margin-bottom: 10px;
      width: 100%;
      border-radius: 6px;
      border: 1px solid #ccc;
    }

    button {
      cursor: pointer;
      border: none;
    }

    .btn-primary {
      background: #2c7be5;
      color: white;
    }

    .btn-danger {
      background: #e55353;
      color: white;
    }

    .btn-warning {
      background: #f0ad4e;
      color: white;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 15px;
    }

    .product-card {
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
      padding-bottom: 10px;
    }

    .product-card img {
      width: 100%;
      height: 150px;
      object-fit: cover;
    }

    .product-body {
      padding: 10px;
    }

    .status {
      font-size: 14px;
      margin-bottom: 5px;
    }

    .buttons form {
      margin-top: 5px;
    }
  </style>
</head>
<body>

<h2>➕ Lisää tuote</h2>

<div class="card form-card">
<form method="POST" enctype="multipart/form-data">
    <input type="text" name="name" placeholder="Tuotteen nimi" required>
    <input type="number" step="0.01" name="price" placeholder="Hinta" required>

    <!-- EI pakollinen -->
    <input type="file" name="image">

    <button type="submit" class="btn-primary">Lisää tuote</button>
</form>
</div>

<h2>📦 Tuotteet</h2>

<div class="grid">
<?php
$stmt = $pdo->query("
    SELECT * FROM products 
    ORDER BY 
      CASE status 
        WHEN 'reserved' THEN 1
        WHEN 'available' THEN 2
        WHEN 'sold' THEN 3
      END,
      created_at DESC
");

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($products as $p) {

    // STATUS
    switch($p['status']) {
        case 'available': $statusText = '🟢 Vapaa'; break;
        case 'reserved': $statusText = '🟡 Varattu'; break;
        case 'sold': $statusText = '🔴 Myyty'; break;
        default: $statusText = $p['status'];
    }

    // KUVA TAI PLACEHOLDER
    $image = $p['image'] ? "../{$p['image']}" : "../uploads/placeholder.webp";

    echo "
    <div class='product-card'>
        <img src='{$image}'>

        <div class='product-body'>
            <strong>{$p['name']}</strong><br>
            {$p['price']} €<br>

            <div class='status'>{$statusText}</div>

            <div class='buttons'>

                <form method='POST'>
                    <input type='hidden' name='delete_id' value='{$p['id']}'>
                    <button class='btn-danger'>Poista</button>
                </form>
    ";

    if ($p['status'] !== 'sold') {
        echo "
            <form method='POST'>
                <input type='hidden' name='sold_id' value='{$p['id']}'>
                <button class='btn-warning'>Merkitse myydyksi</button>
            </form>
        ";
    }

    echo "
            </div>
        </div>
    </div>
    ";
}
?>
</div>

</body>
</html>