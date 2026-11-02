
<?php
session_start();

require_once __DIR__ . "/../config/baglanti.php";

if (!isset($_SESSION["restoran_sahibi_id"], $_SESSION["restoran_id"])) {
    header("Location: restoran_giris.php");
    exit;
}

$sorgu = $db->prepare(
    "SELECT ad_soyad, restoran_adi
     FROM restoran_sahipleri
     WHERE id = ? AND restoran_id = ?
     LIMIT 1"
);

$sorgu->execute([
    $_SESSION["restoran_sahibi_id"],
    $_SESSION["restoran_id"]
]);

$restoranSahibi = $sorgu->fetch(PDO::FETCH_ASSOC);

if (!$restoranSahibi) {
    $_SESSION = [];
    session_destroy();

    header("Location: restoran_giris.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Restoran Yönetim Paneli</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    min-height: 100vh;
    color: #333;

    background-color: #f4eee8;

    background-image:
        linear-gradient(
            120deg,
            rgba(255,255,255,0.91),
            rgba(246,235,225,0.94)
        ),
        url("https://www.transparenttextures.com/patterns/food.png");

    background-attachment: fixed;
}

/* ÜST BÖLÜM */

header {
    background: linear-gradient(120deg, #590014, #8b001e, #650017);
    color: white;

    padding: 35px 15px;
    text-align: center;

    border-bottom: 4px solid #c49a58;

    box-shadow: 0 5px 20px rgba(70,0,15,0.2);
}

header h1 {
    margin: 0 0 15px;
    font-size: 30px;
    letter-spacing: 1px;
}

header p {
    margin: 7px 0;
    color: #f4e5dc;
    font-size: 15px;
}

/* ANA ALAN */

.container {
    width: 90%;
    max-width: 1100px;
    margin: 45px auto;
}

/* KARTLAR */

.kartlar {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 25px;
}

.kart {
    background: rgba(255,255,255,0.96);

    padding: 32px 22px;

    border-radius: 13px;
    text-align: center;

    border: 1px solid #eadbd2;
    border-top: 4px solid #800020;

    box-shadow: 0 6px 20px rgba(75,20,30,0.09);

    transition: all 0.3s ease;

    position: relative;
    overflow: hidden;
}

.kart::after {
    content: "";
    position: absolute;

    width: 75px;
    height: 75px;

    right: -35px;
    bottom: -35px;

    border: 1px solid rgba(139,0,32,0.12);
    border-radius: 50%;
}

.kart:hover {
    transform: translateY(-6px);

    box-shadow: 0 12px 28px rgba(75,20,30,0.16);

    border-top-color: #c49a58;
}

.kart h2 {
    color: #800020;
    font-size: 22px;
    margin-top: 0;
}

.kart p {
    color: #777;
    line-height: 1.6;
    min-height: 48px;
}

/* BUTONLAR */

.buton {
    display: inline-block;

    margin-top: 12px;
    padding: 12px 21px;

    background: #800020;
    color: white;

    text-decoration: none;
    border-radius: 6px;

    font-weight: bold;
    transition: 0.3s;
}

.buton:hover {
    background: #b18a50;
    color: white;
}

/* ALT BÖLÜM */

@media (max-width: 600px) {

    header {
        padding: 25px 12px;
    }

    header h1 {
        font-size: 23px;
    }

    .container {
        width: 92%;
        margin: 25px auto;
    }

    .kartlar {
        grid-template-columns: 1fr;
        gap: 18px;
    }

    .kart {
        padding: 27px 18px;
    }
}

</style>
</head>

<body>

<header>

    <h1>Restoran Yönetim Paneli</h1>

    <p>
        Hoş geldiniz,
        <?= htmlspecialchars($restoranSahibi["ad_soyad"], ENT_QUOTES, "UTF-8") ?>
    </p>

    <p>
        Restoran:
        <?= htmlspecialchars($restoranSahibi["restoran_adi"], ENT_QUOTES, "UTF-8") ?>
    </p>

</header>

<div class="container">

    <div class="kartlar">

        <div class="kart">
            <h2>Ürünler</h2>
            <p>Restoranınızın ürünlerini yönetin.</p>
            <a class="buton" href="urunler.php">Ürünleri Yönet</a>
        </div>

        <div class="kart">
            <h2>Menüler</h2>
            <p>Oluşturduğunuz özel menüleri görüntüleyin.</p>
            <a class="buton" href="../menu.php">Menüyü Gör</a>
        </div>

        <div class="kart">
            <h2>Menüleri Yönet</h2>
            <p>Yeni menü oluşturun veya mevcut menülerinizi yönetin.</p>
            <a class="buton" href="menu_yonet.php">Menüleri Yönet</a>
        </div>

        <div class="kart">
            <h2>Restoran Ayarları</h2>
            <p>Restoranınızın adını, adresini ve telefon numarasını güncelleyin.</p>
            <a class="buton" href="restoran_ayarlar.php">Ayarları Düzenle</a>
        </div>

        <div class="kart">
            <h2>Güvenlik</h2>
            <p>Hesabınızın güvenliği için şifrenizi değiştirebilirsiniz.</p>
            <a class="buton" href="restoran_sifre_degistir.php">Şifre Değiştir</a>
        </div>

        <div class="kart">
            <h2>Çıkış Yap</h2>
            <p>Restoran sahibi oturumunuzu kapatın.</p>
            <a class="buton" href="restoran_cikis.php">Çıkış Yap</a>
        </div>

    </div>

</div>

</body>
</html>
