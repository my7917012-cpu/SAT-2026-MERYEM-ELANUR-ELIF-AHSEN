
<?php
session_start();

require_once __DIR__ . "/../config/baglanti.php";

// Yalnızca giriş yapmış sistem yöneticisi erişebilir.
if (!isset($_SESSION["admin_id"])) {
    header("Location: sistem_giris.php");
    exit;
}

$mesaj = "";
$hata = "";

// Başvuruyu onaylama veya reddetme işlemi.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $basvuru_id = filter_input(
        INPUT_POST,
        "basvuru_id",
        FILTER_VALIDATE_INT
    );

    $islem = $_POST["islem"] ?? "";

    if (!$basvuru_id || !in_array($islem, ["onayla", "reddet"], true)) {
        $hata = "Geçersiz işlem.";
    } else {
        try {
            $yeni_durum = ($islem === "onayla")
                ? "onaylandi"
                : "reddedildi";

            $sorgu = $db->prepare(
                "UPDATE restoran_sahipleri
                 SET onay_durumu = ?
                 WHERE id = ?
                   AND onay_durumu = 'beklemede'"
            );

            $sorgu->execute([$yeni_durum, $basvuru_id]);

            if ($sorgu->rowCount() > 0) {
                $mesaj = ($islem === "onayla")
                    ? "Başvuru başarıyla onaylandı."
                    : "Başvuru reddedildi.";
            } else {
                $hata = "Başvuru bulunamadı veya daha önce işleme alınmış.";
            }

        } catch (PDOException $e) {
            $hata = "İşlem sırasında bir hata oluştu.";
        }
    }
}

// Başvuruları getir.
$sorgu = $db->query(
    "SELECT
        id,
        ad_soyad,
        restoran_adi,
        eposta,
        kullanici_adi,
        telefon,
        onay_durumu,
        olusturma_tarihi
     FROM restoran_sahipleri
     ORDER BY olusturma_tarihi DESC"
);

$basvurular = $sorgu->fetchAll(PDO::FETCH_ASSOC);

// Durumları Türkçe göster.
function durumYaz($durum) {
    switch ($durum) {
        case "beklemede":
            return "Beklemede";
        case "onaylandi":
            return "Onaylandı";
        case "reddedildi":
            return "Reddedildi";
        default:
            return $durum;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Restoran Başvuruları</title>

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
            max-width: 1300px;
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
            color: #fff;
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
            max-width: 1300px;
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

        .mesaj {
            padding: 14px 16px;
            margin-bottom: 18px;
            border-radius: 7px;
            font-size: 14px;
        }

        .basari {
            color: #176b32;
            background: #e4f7e9;
            border-left: 4px solid #218838;
        }

        .hata {
            color: #a32020;
            background: #ffe5e5;
            border-left: 4px solid #b71c1c;
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
            min-width: 1050px;
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
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .beklemede {
            color: #946200;
            background: #fff3cd;
        }

        .onaylandi {
            color: #176b32;
            background: #e1f5e6;
        }

        .reddedildi {
            color: #a32020;
            background: #ffe5e5;
        }

        .islem-formu {
            display: inline-block;
            margin: 2px;
        }

        .buton {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            color: white;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: 0.2s;
        }

        .onayla {
            background: #218838;
        }

        .onayla:hover {
            background: #176b32;
        }

        .reddet {
            background: #a32020;
        }

        .reddet:hover {
            background: #7c1515;
        }

        .tamamlandi {
            color: #888;
            font-size: 12px;
            white-space: nowrap;
        }

        .bos {
            padding: 45px 20px;
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

        .bos p {
            color: #888;
            font-size: 14px;
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
            <h1>Restoran Başvuruları</h1>
            <p>Sistem yöneticisi başvuru yönetim ekranı</p>
        </div>

        <a class="geri" href="sistem_paneli.php">
            ← Yönetici Paneline Dön
        </a>
    </div>
</header>

<div class="container">

    <div class="sayfa-baslik">
        <h2>Başvuru Yönetimi</h2>
        <p>Restoran sahiplerinin kayıt başvurularını inceleyebilir ve değerlendirebilirsiniz.</p>
    </div>

    <?php
        $bekleyen = 0;
        $onaylanan = 0;
        $reddedilen = 0;

        foreach ($basvurular as $b) {
            if ($b["onay_durumu"] === "beklemede") {
                $bekleyen++;
            } elseif ($b["onay_durumu"] === "onaylandi") {
                $onaylanan++;
            } elseif ($b["onay_durumu"] === "reddedildi") {
                $reddedilen++;
            }
        }
    ?>

    <div class="istatistik">

        <div class="istatistik-kart">
            <span>Toplam Başvuru</span>
            <strong><?= count($basvurular) ?></strong>
        </div>

        <div class="istatistik-kart">
            <span>Bekleyen Başvuru</span>
            <strong><?= $bekleyen ?></strong>
        </div>

        <div class="istatistik-kart">
            <span>Onaylanan Başvuru</span>
            <strong><?= $onaylanan ?></strong>
        </div>

    </div>

    <?php if ($mesaj !== ""): ?>
        <div class="mesaj basari">
            <?= htmlspecialchars($mesaj, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <?php if ($hata !== ""): ?>
        <div class="mesaj hata">
            <?= htmlspecialchars($hata, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <?php if (count($basvurular) > 0): ?>

        <div class="tablo-kapsayici">

            <table>
                <thead>
                    <tr>
                        <th>Ad Soyad</th>
                        <th>Restoran</th>
                        <th>E-posta</th>
                        <th>Kullanıcı Adı</th>
                        <th>Telefon</th>
                        <th>Başvuru Tarihi</th>
                        <th>Durum</th>
                        <th>İşlem</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($basvurular as $basvuru): ?>

                    <tr>
                        <td>
                            <?= htmlspecialchars($basvuru["ad_soyad"], ENT_QUOTES, "UTF-8") ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($basvuru["restoran_adi"], ENT_QUOTES, "UTF-8") ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($basvuru["eposta"], ENT_QUOTES, "UTF-8") ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($basvuru["kullanici_adi"], ENT_QUOTES, "UTF-8") ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($basvuru["telefon"] ?? "", ENT_QUOTES, "UTF-8") ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($basvuru["olusturma_tarihi"], ENT_QUOTES, "UTF-8") ?>
                        </td>

                        <td>
                            <span class="durum <?= htmlspecialchars($basvuru["onay_durumu"], ENT_QUOTES, "UTF-8") ?>">
                                <?= htmlspecialchars(durumYaz($basvuru["onay_durumu"]), ENT_QUOTES, "UTF-8") ?>
                            </span>
                        </td>

                        <td>

                            <?php if ($basvuru["onay_durumu"] === "beklemede"): ?>

                                <form method="POST" class="islem-formu">
                                    <input
                                        type="hidden"
                                        name="basvuru_id"
                                        value="<?= (int) $basvuru["id"] ?>"
                                    >

                                    <button
                                        class="buton onayla"
                                        type="submit"
                                        name="islem"
                                        value="onayla"
                                    >
                                        ✓ Onayla
                                    </button>
                                </form>

                                <form method="POST" class="islem-formu">
                                    <input
                                        type="hidden"
                                        name="basvuru_id"
                                        value="<?= (int) $basvuru["id"] ?>"
                                    >

                                    <button
                                        class="buton reddet"
                                        type="submit"
                                        name="islem"
                                        value="reddet"
                                    >
                                        ✕ Reddet
                                    </button>
                                </form>

                            <?php else: ?>

                                <span class="tamamlandi">İşlem tamamlandı</span>

                            <?php endif; ?>

                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>

        </div>

    <?php else: ?>

        <div class="bos">
            <div class="ikon">📋</div>
            <h3>Henüz Başvuru Bulunmuyor</h3>
            <p>Sisteme henüz herhangi bir restoran başvurusu yapılmamış.</p>
        </div>

    <?php endif; ?>

</div>

<footer>
    © <?= date("Y") ?> Restoran Yönetim Sistemi
</footer>

</body>
</html>
