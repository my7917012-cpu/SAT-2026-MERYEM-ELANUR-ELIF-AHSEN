<?php
session_start();

require_once "../config/baglanti.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: giris.php");
    exit;
}

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("Geçersiz ürün. <a href='urunler.php'>Geri dön</a>");
}

$sorgu = $db->prepare(
    "SELECT id, kategori_id, urun_adi, aciklama, fiyat
     FROM urunler WHERE id = ?"
);
$sorgu->execute([$id]);
$urun = $sorgu->fetch(PDO::FETCH_ASSOC);

if (!$urun) {
    exit("Ürün bulunamadı. <a href='urunler.php'>Geri dön</a>");
}

$hata = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $urun_adi = trim($_POST["urun_adi"] ?? "");
    $aciklama = trim($_POST["aciklama"] ?? "");
    $fiyat_girdi = str_replace(",", ".", trim($_POST["fiyat"] ?? ""));

    if ($urun_adi === "") {
        $hata = "Ürün adı boş bırakılamaz.";
    } elseif (!is_numeric($fiyat_girdi) || (float)$fiyat_girdi < 0) {
        $hata = "Geçerli, negatif olmayan bir fiyat gir.";
    } else {
        $guncelle = $db->prepare(
            "UPDATE urunler
             SET urun_adi = ?, aciklama = ?, fiyat = ?
             WHERE id = ?"
        );

        $guncelle->execute([
            $urun_adi,
            $aciklama,
            $fiyat_girdi,
            $id
        ]);

        header("Location: kategori_urunleri.php?id=" . (int)$urun["kategori_id"]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ürün Güncelle</title>
<style>
* { box-sizing: border-box; }
body {
    margin: 0;
    padding: 20px;
    font-family: Arial, sans-serif;
    background: #f5f5f5;
    color: #333;
}
main {
    max-width: 600px;
    margin: 30px auto;
    padding: 24px;
    background: white;
    border-radius: 10px;
}
label { display: block; margin-top: 14px; }
input, textarea, button {
    width: 100%;
    padding: 12px;
    margin-top: 7px;
    font: inherit;
    border: 1px solid #ccc;
    border-radius: 6px;
}
textarea { min-height: 100px; }
button {
    background: #b71c1c;
    color: white;
    border: 0;
    cursor: pointer;
    margin-top: 20px;
}
a { color: #b71c1c; }
.hata { color: #b71c1c; }
</style>
</head>
<body>
<main>
    <h1>Ürün Güncelle</h1>

    <?php if ($hata !== ""): ?>
        <p class="hata"><?= htmlspecialchars($hata) ?></p>
    <?php endif; ?>

    <form method="POST">
        <label for="urun_adi">Ürün adı</label>
        <input id="urun_adi" name="urun_adi" maxlength="150" required
               value="<?= htmlspecialchars($urun_adi ?? $urun["urun_adi"]) ?>">

        <label for="fiyat">Fiyat (TL)</label>
        <input id="fiyat" name="fiyat" type="number"
               min="0" step="0.01" required
               value="<?= htmlspecialchars((string)($fiyat_girdi ?? $urun["fiyat"])) ?>">

        <label for="aciklama">Açıklama</label>
        <textarea id="aciklama" name="aciklama"><?= htmlspecialchars($aciklama ?? $urun["aciklama"] ?? "") ?></textarea>

        <button type="submit">Değişiklikleri Kaydet</button>
    </form>

    <p>
        <a href="kategori_urunleri.php?id=<?= (int)$urun["kategori_id"] ?>">
            ← Ürün listesine dön
        </a>
    </p>
</main>
</body>
</html>