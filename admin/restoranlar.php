
<?php

session_start();

require_once __DIR__ . "/../config/baglanti.php";

// Sistem yöneticisi girişi kontrolü
if (!isset($_SESSION["admin_id"])) {
    header("Location: sistem_giris.php");
    exit;
}

$mesaj = "";
$restoranlar = [];

try {
    $sorgu = $db->prepare("
        SELECT
            id,
            restoran_adi,
            adres,
            telefon,
            aktif,
            olusturma_tarihi
        FROM restoranlar
        ORDER BY id DESC
    ");

    $sorgu->execute();
    $restoranlar = $sorgu->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $mesaj = "Restoranlar listelenirken bir hata oluştu.";
    $restoranlar = [];
}

// Restoran istatistikleri
$toplamRestoran = count($restoranlar);
$aktifRestoran = 0;
$pasifRestoran = 0;

foreach ($restoranlar as $restoran) {
    if ((int) $restoran["aktif"] === 1) {
        $aktifRestoran++;
    } else {
        $pasifRestoran++;
    }
}

?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kayıtlı Restoranlar</title>

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
            padding: 28px 20px;
            border-bottom: 4px solid #c49a58;
        }

        .ust-kapsayici {
            max-width: 1250px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        header h1 {
            margin: 0;
            font-size: 25px;
        }

        header p {
            color: #f0d9b5;
            margin: 8px 0 0;
            font-size: 14px;
        }

        .geri {
            display: inline-block;
            color: white;
            text-decoration: none;
            border: 1px solid #c49a58;
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 13px;
            transition: 0.2s;
        }

        .geri:hover {
            background: #c49a58;
            color: #590014;
        }

        .container {
            width: 94%;
            max-width: 1250px;
            margin: 30px auto;
        }

        .sayfa-baslik {
            margin-bottom: 22px;
        }

        .sayfa-baslik h2 {
            color: #650017;
            font-size: 22px;
            margin: 0 0 8px;
        }

        .sayfa-baslik p {
            color: #777;
            font-size: 14px;
            margin: 0;
        }

        .istatistik {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 22px;
        }

        .istatistik-kart {
            background: white;
            padding: 18px;
            border-radius: 9px;
            border-top: 3px solid #c49a58;
            box-shadow: 0 3px 12px rgba(70, 20, 20, 0.06);
        }

        .istatistik-kart span {
            display: block;
            color: #777;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .istatistik-kart strong {
            font-size: 24px;
            color: #650017;
        }

        .tablo-kapsayici {
            overflow-x: auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(70, 20, 20, 0.07);
            border-top: 4px solid #c49a58;
        }

        table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        th {
            background: #650017;
            color: white;
            padding: 16px 13px;
            text-align: left;
            font-size: 13px;
            white-space: nowrap;
        }

        td {
            padding: 14px 13px;
            border-bottom: 1px solid #eee5df;
            font-size: 13px;
            color: #444;
        }

        tbody tr:hover {
            background: #fcf7f3;
        }

        .durum {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .aktif {
            color: #176b32;
            background: #e1f5e6;
        }

        .pasif {
            color: #a32020;
            background: #ffe5e5;
        }

        .bos,
        .hata-kutu {
            padding: 40px 20px;
            text-align: center;
            background: white;
            border-radius: 10px;
            border-top: 4px solid #c49a58;
            box-shadow: 0 4px 15px rgba(70, 20, 20, 0.06);
        }

        .bos .ikon {
            font-size: 38px;
            margin-bottom: 12px;
        }

        .bos h3 {
            color: #650017;
            margin: 0 0 8px;
        }

        .bos p,
        .hata-kutu p {
            color: #888;
            font-size: 14px;
        }

        .hata-kutu {
            color: #a32020;
            border-top-color: #b71c1c;
        }

        footer {
            text-align: center;
            color: #999;
            font-size: 12px;
            padding: 25px 10px;
        }

        @media (max-width: 700px) {
            header {
                padding: 22px 16px;
            }

            .ust-kapsayici {
                align-items: flex-start;
                flex-direction: column;
            }

            header h1 {
                font-size: 21px;
            }

            .container {
                width: 94%;
                margin: 22px auto;
            }

            .istatistik {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .istatistik-kart {
                padding: 14px;
            }

            .istatistik-kart strong {
                font-size: 21px;
            }

            .sayfa-baslik h2 {
                font-size: 19px;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="ust-kapsayici">
        <div>
            <h1>🏪 Kayıtlı Restoranlar</h1>
            <p>Sistem yöneticisi restoran yönetim ekranı</p>
        </div>

        <a href="sistem_paneli.php" class="geri">
            ← Yönetici Paneline Dön
        </a>
    </div>
</header>

<div class="container">

    <div class="sayfa-baslik">
        <h2>Restoran Yönetimi</h2>
        <p>Sisteme kayıtlı restoranların bilgilerini ve durumlarını görüntüleyebilirsiniz.</p>
    </div>

    <div class="istatistik">

        <div class="istatistik-kart">
            <span>Toplam Restoran</span>
            <strong><?= $toplamRestoran ?></strong>
        </div>

        <div class="istatistik-kart">
            <span>Aktif Restoran</span>
            <strong><?= $aktifRestoran ?></strong>
        </div>

        <div class="istatistik-kart">
            <span>Pasif Restoran</span>
            <strong><?= $pasifRestoran ?></strong>
        </div>

    </div>

    <?php if ($mesaj !== ""): ?>

        <div class="hata-kutu">
            <p><?= htmlspecialchars($mesaj, ENT_QUOTES, "UTF-8") ?></p>
        </div>

    <?php elseif ($toplamRestoran > 0): ?>

        <div class="tablo-kapsayici">

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Restoran Adı</th>
                        <th>Adres</th>
                        <th>Telefon</th>
                        <th>Durum</th>
                        <th>Kayıt Tarihi</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($restoranlar as $restoran): ?>

                        <tr>
                            <td>
                                <?= (int) $restoran["id"] ?>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($restoran["restoran_adi"] ?? "", ENT_QUOTES, "UTF-8") ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars($restoran["adres"] ?? "", ENT_QUOTES, "UTF-8") ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($restoran["telefon"] ?? "", ENT_QUOTES, "UTF-8") ?>
                            </td>

                            <td>
                                <?php if ((int) $restoran["aktif"] === 1): ?>

                                    <span class="durum aktif">● Aktif</span>

                                <?php else: ?>

                                    <span class="durum pasif">● Pasif</span>

                                <?php endif; ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($restoran["olusturma_tarihi"] ?? "", ENT_QUOTES, "UTF-8") ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>
            </table>

        </div>

    <?php else: ?>

        <div class="bos">
            <div class="ikon">🏪</div>
            <h3>Henüz Restoran Bulunmuyor</h3>
            <p>Sisteme henüz herhangi bir restoran kaydedilmemiş.</p>
        </div>

    <?php endif; ?>

</div>

<footer>
    © <?= date("Y") ?> Restoran Yönetim Sistemi
</footer>

</body>
</html>
