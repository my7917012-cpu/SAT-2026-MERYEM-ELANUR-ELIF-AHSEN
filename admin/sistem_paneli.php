
<?php
session_start();

require_once __DIR__ . "/../config/baglanti.php";

// Yalnızca giriş yapan sistem yöneticisi erişebilir.
if (!isset($_SESSION["admin_id"])) {
    header("Location: sistem_giris.php");
    exit;
}

$yoneticiAdi = $_SESSION["admin_kullanici"] ?? "Sistem Yöneticisi";
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Yöneticisi Paneli</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f4eee8;
            color: #333;
        }

        header {
            background: linear-gradient(120deg, #590014, #8b001e, #650017);
            color: white;
            padding: 35px 20px;
            text-align: center;
            border-bottom: 4px solid #c49a58;
        }

        header h1 {
            margin: 0;
            font-size: 29px;
            letter-spacing: 0.5px;
        }

        header p {
            margin: 10px 0 0;
            color: #f0d9b5;
            font-size: 14px;
        }

        .yonetici-bilgi {
            margin-top: 15px;
            font-size: 14px;
            color: #fff;
        }

        .container {
            width: 90%;
            max-width: 1050px;
            margin: 40px auto;
        }

        .baslik {
            text-align: center;
            margin-bottom: 28px;
        }

        .baslik h2 {
            color: #650017;
            font-size: 23px;
            margin-bottom: 8px;
        }

        .baslik p {
            color: #777;
            font-size: 14px;
        }

        .kartlar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 22px;
        }

        .kart {
            background: #fff;
            padding: 28px 22px;
            border-radius: 12px;
            border-top: 4px solid #c49a58;
            box-shadow: 0 4px 15px rgba(70, 20, 20, 0.08);
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .kart:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 22px rgba(70, 20, 20, 0.13);
        }

        .ikon {
            width: 58px;
            height: 58px;
            margin: 0 auto 17px;
            border-radius: 50%;
            background: #f8eee9;
            color: #800020;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 27px;
        }

        .kart h2 {
            color: #650017;
            font-size: 19px;
            margin: 0 0 12px;
        }

        .kart p {
            color: #777;
            font-size: 14px;
            line-height: 1.6;
            min-height: 45px;
        }

        .buton {
            display: inline-block;
            background: #800020;
            color: white;
            padding: 11px 19px;
            margin-top: 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            transition: background 0.2s;
        }

        .buton:hover {
            background: #590014;
        }

        .cikis {
            background: #a33b35;
        }

        .cikis:hover {
            background: #7c211c;
        }

        footer {
            text-align: center;
            color: #999;
            font-size: 12px;
            padding: 25px 10px;
        }

        @media (max-width: 600px) {
            header {
                padding: 27px 15px;
            }

            header h1 {
                font-size: 23px;
            }

            .container {
                width: 92%;
                margin: 28px auto;
            }

            .kartlar {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .kart {
                padding: 24px 18px;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Sistem Yöneticisi Paneli</h1>
    <p>Restoran Yönetim Sistemi</p>

    <div class="yonetici-bilgi">
        Hoş geldiniz, <?= htmlspecialchars($yoneticiAdi, ENT_QUOTES, "UTF-8") ?>
    </div>
</header>

<div class="container">

    <div class="baslik">
        <h2>Yönetim Merkezi</h2>
        <p>Sistem işlemlerinizi bu panel üzerinden gerçekleştirebilirsiniz.</p>
    </div>

    <div class="kartlar">

        <div class="kart">
            <div class="ikon">📋</div>
            <h2>Restoran Başvuruları</h2>
            <p>Kayıt olan restoran sahiplerinin başvurularını inceleyebilir ve değerlendirebilirsiniz.</p>
            <a class="buton" href="restoran_basvurulari.php">
                Başvuruları Gör
            </a>
        </div>

        <div class="kart">
            <div class="ikon">🏪</div>
            <h2>Restoranlar</h2>
            <p>Sistemde kayıtlı restoranları görüntüleyebilir ve yönetebilirsiniz.</p>
            <a class="buton" href="restoranlar.php">
                Restoranları Gör
            </a>
        </div>

        <div class="kart">
            <div class="ikon">🔒</div>
            <h2>Güvenli Çıkış</h2>
            <p>Sistem yöneticisi oturumunuzu güvenli şekilde sonlandırabilirsiniz.</p>
            <a class="buton cikis" href="sistem_cikis.php">
                Çıkış Yap
            </a>
        </div>

    </div>

</div>

<footer>
    © <?= date("Y") ?> Restoran Yönetim Sistemi
</footer>

</body>
</html>
