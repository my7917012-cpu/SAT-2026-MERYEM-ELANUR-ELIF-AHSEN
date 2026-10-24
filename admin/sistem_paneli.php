
<?php
session_start();

require_once __DIR__ . "/../config/baglanti.php";

// Yalnızca mevcut yönetici girişi yapan kişi erişebilir.
if (!isset($_SESSION["admin_id"])) {
    header("Location: giris.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Yöneticisi Paneli</title>

    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f4f4f4;
        }

        header {
            background: #8b0000;
            color: white;
            padding: 22px;
            text-align: center;
        }

        .container {
            width: 90%;
            max-width: 1000px;
            margin: 35px auto;
        }

        .kartlar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 20px;
        }

        .kart {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .kart h2 {
            color: #8b0000;
        }

        .buton {
            display: inline-block;
            background: #8b0000;
            color: white;
            padding: 11px 18px;
            margin-top: 10px;
            border-radius: 5px;
            text-decoration: none;
        }

        .buton:hover {
            background: #650000;
        }
    </style>
</head>

<body>

<header>
    <h1>Sistem Yöneticisi Paneli</h1>
    <p>Restoran yönetim sistemi</p>
</header>

<div class="container">

    <div class="kartlar">

        <div class="kart">
            <h2>Restoran Başvuruları</h2>
            <p>Kayıt olan restoran sahiplerinin başvurularını incele.</p>
            <a class="buton" href="restoran_basvurulari.php">
                Başvuruları Gör
            </a>
        </div>

        <div class="kart">
            <h2>Restoranlar</h2>
            <p>Sistemde kayıtlı restoranları görüntüle.</p>
            <a class="buton" href="restoranlar.php">
                Restoranları Gör
            </a>
        </div>

        <div class="kart">
            <h2>Çıkış Yap</h2>
            <p>Yönetici oturumunu güvenli şekilde kapat.</p>
            <a class="buton" href="sistem_cikis.php">
                Çıkış Yap
            </a>
        </div>

    </div>

</div>

</body>
</html>
