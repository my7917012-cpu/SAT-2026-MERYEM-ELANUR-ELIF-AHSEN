<?php
session_start();

require_once "../config/baglanti.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: giris.php");
    exit;
}

$mesaj = "";
$hata = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $kategori_adi = trim($_POST["kategori_adi"] ?? "");

    if ($kategori_adi === "") {
        $hata = "Kategori adı boş bırakılamaz.";
    } else {
        try {
            $ekle = $db->prepare(
                "INSERT INTO kategoriler (kategori_adi) VALUES (?)"
            );
            $ekle->execute([$kategori_adi]);

            $mesaj = "Kategori başarıyla eklendi.";
        } catch (PDOException $e) {
            if ($e->getCode() === "23000") {
                $hata = "Bu kategori zaten mevcut.";
            } else {
                $hata = "Kategori eklenirken bir hata oluştu.";
            }
        }
    }
}

$sorgu = $db->query("
    SELECT k.id, k.kategori_adi,
           COUNT(u.id) AS urun_sayisi
    FROM kategoriler k
    LEFT JOIN urunler u ON u.kategori_id = k.id
    GROUP BY k.id, k.kategori_adi
    ORDER BY k.kategori_adi
");

$kategoriler = $sorgu->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ürünleri Yönet</title>
<style>
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f5f5f5;
    color: #333;
}
header {
    background: #b71c1c;
    color: white;
    padding: 20px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
header h1 { margin: 0; font-size: 24px; }
a { text-decoration: none; }
header a {
    color: #b71c1c;
    background: white;
    padding: 10px 14px;
    border-radius: 6px;
}
main { max-width: 1100px; margin: 30px auto; padding: 0 18px; }
.kutu, .kart {
    background: white;
    border-radius: 10px;
    padding: 22px;
    box-shadow: 0 3px 12px #0000000d;
}
.kutu { margin-bottom: 25px; }
input, button {
    padding: 12px;
    border-radius: 6px;
    font-size: 15px;
}
input {
    width: 100%;
    border: 1px solid #ccc;
    margin: 8px 0 12px;
}
button, .buton {
    display: inline-block;
    border: 0;
    background: #b71c1c;
    color: white;
    padding: 11px 16px;
    border-radius: 6px;
    cursor: pointer;
}
.grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}
.kart h2 { color: #b71c1c; margin-top: 0; }
.mesaj { color: #16703a; }
.hata { color: #b71c1c; }
@media (max-width: 600px) {
    .grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<header>
    <h1>Ürün Yönetimi</h1>
    <a href="panel.php">Admin Paneline Dön</a>
</header>

<main>
    <section class="kutu">
        <h2>Yeni Kategori Ekle</h2>

        <?php if ($mesaj): ?>
            <p class="mesaj"><?= htmlspecialchars($mesaj) ?></p>
        <?php endif; ?>

        <?php if ($hata): ?>
            <p class="hata"><?= htmlspecialchars($hata) ?></p>
        <?php endif; ?>

        <form method="POST">
            <label for="kategori_adi">Kategori adı</label>
            <input id="kategori_adi" name="kategori_adi"
                   maxlength="100" required
                   placeholder="Örn. Tatlılar">
            <button type="submit">Kategori Ekle</button>
        </form>
    </section>

    <h2>Kategoriler</h2>

    <div class="grid">
        <?php foreach ($kategoriler as $kategori): ?>
            <article class="kart">
                <h2><?= htmlspecialchars($kategori["kategori_adi"]) ?></h2>
                <p>
                    Ürün sayısı:
                    <?= (int)$kategori["urun_sayisi"] ?>
                </p>
                <a class="buton"
                   href="kategori_urunleri.php?id=<?= (int)$kategori["id"] ?>">
                    Kategoriyi Aç
                </a>
            </article>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>