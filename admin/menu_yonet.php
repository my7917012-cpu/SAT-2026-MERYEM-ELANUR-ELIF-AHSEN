
<?php
session_start();

require_once "../config/baglanti.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: giris.php");
    exit;
}

if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$mesaj = "";
$hata = "";

// Menü silme işlemi
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["menu_sil"])) {
    try {
        if (
            !isset($_POST["csrf_token"]) ||
            !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
        ) {
            throw new Exception("Güvenlik doğrulaması başarısız. Sayfayı yenileyin.");
        }

        $menu_id = filter_input(INPUT_POST, "menu_id", FILTER_VALIDATE_INT);

        if (!$menu_id || $menu_id < 1) {
            throw new Exception("Geçersiz menü seçildi.");
        }

        $sil = $db->prepare("DELETE FROM ozel_menuler WHERE id = ?");
        $sil->execute([$menu_id]);

        if ($sil->rowCount() === 0) {
            throw new Exception("Menü bulunamadı veya zaten silinmiş.");
        }

        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

        header("Location: menu_yonet.php?silindi=1");
        exit;

    } catch (Exception $e) {
        $hata = $e->getMessage();
    } catch (PDOException $e) {
        $hata = "Menü silinemedi. Lütfen tekrar deneyin.";
    }
}

if (isset($_GET["silindi"])) {
    $mesaj = "Özel menü başarıyla silindi.";
}

// Tüm özel menüleri listele
$sorgu = $db->query("
    SELECT
        om.*,
        COUNT(omu.id) AS urun_sayisi
    FROM ozel_menuler om
    LEFT JOIN ozel_menu_urunleri omu ON omu.menu_id = om.id
    GROUP BY
        om.id,
        om.menu_adi,
        om.menu_fiyati,
        om.indirim_orani,
        om.indirim_baslangic,
        om.indirim_bitis,
        om.aktif,
        om.olusturma_tarihi
    ORDER BY om.olusturma_tarihi DESC
");

$menuler = $sorgu->fetchAll(PDO::FETCH_ASSOC);

function tlYaz($fiyat) {
    return number_format((float)$fiyat, 2, ',', '.') . " TL";
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Özel Menüleri Yönet</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #333;
        }

        .kutu {
            max-width: 1000px;
            margin: auto;
            padding: 25px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,.08);
        }

        .menu-kart {
            margin-top: 18px;
            padding: 18px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .menu-kart h3 {
            margin-top: 0;
        }

        .bilgi {
            line-height: 1.8;
        }

        .buton {
            display: inline-block;
            margin: 6px 5px 0 0;
            padding: 10px 14px;
            border: none;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .guncelle {
            background: #0d6efd;
        }

        .sil {
            background: #dc3545;
        }

        .yeni {
            background: #198754;
        }

        .geri {
            background: #555;
        }

        .mesaj, .hata {
            padding: 12px;
            margin: 15px 0;
            border-radius: 5px;
        }

        .mesaj {
            background: #d1e7dd;
            color: #0f5132;
        }

        .hata {
            background: #f8d7da;
            color: #842029;
        }

        .bos {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .kutu {
                padding: 15px;
            }
        }
    </style>
</head>

<body>
<div class="kutu">

    <h1>Özel Menüleri Yönet</h1>

    <?php if ($mesaj !== ""): ?>
        <div class="mesaj">
            <?= htmlspecialchars($mesaj, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <?php if ($hata !== ""): ?>
        <div class="hata">
            <?= htmlspecialchars($hata, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <a href="menu_olustur.php" class="buton yeni">
        + Yeni Menü Oluştur
    </a>

    <a href="panel.php" class="buton geri">
        Admin Paneline Dön
    </a>

    <?php if (empty($menuler)): ?>

        <div class="bos">
            Henüz oluşturulmuş özel menü bulunmuyor.
        </div>

    <?php else: ?>

        <?php foreach ($menuler as $menu): ?>

            <?php
            $indirimOrani = (float)$menu["indirim_orani"];
            $baslangic = $menu["indirim_baslangic"];
            $bitis = $menu["indirim_bitis"];

            $indirimAktif = $indirimOrani > 0
                && !empty($baslangic)
                && !empty($bitis)
                && time() >= strtotime($baslangic)
                && time() <= strtotime($bitis);

            // Tarihler boşsa indirim süresiz kabul edilir.
            if (
                $indirimOrani > 0 &&
                empty($baslangic) &&
                empty($bitis)
            ) {
                $indirimAktif = true;
            }

            $indirimliFiyat = (float)$menu["menu_fiyati"]
                * (1 - $indirimOrani / 100);
            ?>

            <div class="menu-kart">

                <h3>
                    <?= htmlspecialchars($menu["menu_adi"], ENT_QUOTES, "UTF-8") ?>
                </h3>

                <div class="bilgi">
                    <strong>Menü fiyatı:</strong>
                    <?= tlYaz($menu["menu_fiyati"]) ?>
                    <br>

                    <strong>Ürün sayısı:</strong>
                    <?= (int)$menu["urun_sayisi"] ?>
                    <br>

                    <strong>İndirim oranı:</strong>
                    %<?= number_format($indirimOrani, 2, ',', '.') ?>
                    <br>

                    <strong>İndirim durumu:</strong>
                    <?php if ($indirimAktif): ?>
                        Aktif — İndirimli fiyat: <?= tlYaz($indirimliFiyat) ?>
                    <?php elseif ($indirimOrani <= 0): ?>
                        İndirim yok
                    <?php elseif (!empty($bitis) && time() > strtotime($bitis)): ?>
                        İndirim süresi dolmuş
                    <?php else: ?>
                        İndirim şu anda aktif değil
                    <?php endif; ?>
                    <br>

                    <strong>Başlangıç:</strong>
                    <?= !empty($baslangic)
                        ? htmlspecialchars($baslangic, ENT_QUOTES, "UTF-8")
                        : "Belirtilmedi" ?>
                    <br>

                    <strong>Bitiş:</strong>
                    <?= !empty($bitis)
                        ? htmlspecialchars($bitis, ENT_QUOTES, "UTF-8")
                        : "Belirtilmedi" ?>
                </div>

                <a
                    href="menu_guncelle.php?id=<?= (int)$menu["id"] ?>"
                    class="buton guncelle"
                >
                    Güncelle
                </a>

                <form
                    method="POST"
                    style="display:inline-block"
                    onsubmit="return confirm('Bu özel menüyü silmek istediğine emin misin? Menüdeki ürün kayıtları silinmeyecek.');"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>"
                    >

                    <input
                        type="hidden"
                        name="menu_id"
                        value="<?= (int)$menu["id"] ?>"
                    >

                    <button
                        type="submit"
                        name="menu_sil"
                        value="1"
                        class="buton sil"
                    >
                        Sil
                    </button>
                </form>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>
</body>
</html>
