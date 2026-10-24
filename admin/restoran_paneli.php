
<?php
session_start();

require_once __DIR__ . "/../config/baglanti.php";

// Restoran sahibi giriş yapmamışsa giriş sayfasına yönlendir.
if (!isset($_SESSION["restoran_sahibi_id"], $_SESSION["restoran_id"])) {
    header("Location: restoran_giris.php");
    exit;
}

// Oturumdaki restoran sahibinin bilgilerini getir.
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

// Oturumdaki bilgiler geçersizse erişimi kapat.
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
            background: #f4f4f4;
            color: #333;
        }

        header {
            background: #8b0000;
            color: white;
            padding: 25px 15px;
            text-align: center;
        }

        header h1 {
            margin: 0 0 10px;
        }

        .container {
            width: 90%;
            max-width: 1000px;
            margin: 30px auto;
        }

        .kartlar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .kart {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .kart h2 {
            color: #8b0000;
        }

        .buton {
            display: inline-block;
            margin-top: 10px;
            padding: 11px 18px;
            background: #8b0000;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .buton:hover {
            background: #650000;
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
            <span>Ürün yönetimi sonraki adımda bağlanacak.</span>
        </div>

        <div class="kart">
            <h2>Kategoriler</h2>
            <p>Ürün kategorilerinizi yönetin.</p>
            <span>Kategori yönetimi sonraki adımda bağlanacak.</span>
        </div>

        <div class="kart">
            <h2>Menüler</h2>
            <p>Özel menülerinizi ve fiyatlarınızı yönetin.</p>
            <span>Menü yönetimi sonraki adımda bağlanacak.</span>
        </div>

        <div class="kart">
            <h2>Restoran Ayarları</h2>
            <p>Restoran bilgilerinizin yönetimi sonraki adımda bağlanacak.</p>
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
