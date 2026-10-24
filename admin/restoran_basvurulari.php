
<?php
session_start();

require_once __DIR__ . "/../config/baglanti.php";

// Yalnızca giriş yapmış yönetici erişebilir.
if (!isset($_SESSION["admin_id"])) {
    header("Location: giris.php");
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
                    ? "Başvuru onaylandı."
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
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f4f4f4;
        }

        .kapsayici {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            color: #b71c1c;
        }

        .mesaj {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
        }

        .basari {
            color: #176b32;
            background: #e4f7e9;
        }

        .hata {
            color: #b71c1c;
            background: #ffe5e5;
        }

        .tablo-kapsayici {
            overflow-x: auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        th,
        td {
            padding: 13px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #b71c1c;
            color: white;
        }

        .durum {
            font-weight: bold;
        }

        .beklemede {
            color: #a66a00;
        }

        .onaylandi {
            color: #176b32;
        }

        .reddedildi {
            color: #b71c1c;
        }

        .buton {
            padding: 8px 11px;
            margin: 2px;
            border: none;
            border-radius: 5px;
            color: white;
            cursor: pointer;
        }

        .onayla {
            background: #218838;
        }

        .reddet {
            background: #b71c1c;
        }

        .geri {
            display: inline-block;
            margin-bottom: 20px;
            color: #b71c1c;
            text-decoration: none;
        }

        .bos {
            padding: 20px;
            text-align: center;
            background: white;
        }
    </style>
</head>

<body>

<div class="kapsayici">

    <a class="geri" href="panel.php">← Yönetici Paneline Dön</a>

    <h1>Restoran Başvuruları</h1>

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
                                <?= htmlspecialchars($basvuru["onay_durumu"], ENT_QUOTES, "UTF-8") ?>
                            </span>
                        </td>

                        <td>

                            <?php if ($basvuru["onay_durumu"] === "beklemede"): ?>

                                <form method="POST" style="display:inline;">
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
                                        Onayla
                                    </button>
                                </form>

                                <form method="POST" style="display:inline;">
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
                                        Reddet
                                    </button>
                                </form>

                            <?php else: ?>

                                İşlem tamamlandı

                            <?php endif; ?>

                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>

        </div>

    <?php else: ?>

        <div class="bos">
            Henüz restoran başvurusu bulunmuyor.
        </div>

    <?php endif; ?>

</div>

</body>
</html>
