
<?php
session_start();

require_once "../config/baglanti.php";

if (!isset($_SESSION["restoran_sahibi_id"], $_SESSION["restoran_id"])) {
    header("Location: restoran_giris.php");
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

        $sil = $db->prepare("
               DELETE FROM ozel_menuler
               WHERE id = ? AND restoran_id = ?
         ");
         $sil->execute([$menu_id, $_SESSION["restoran_id"]]);

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


// Yalnızca giriş yapan restoranın özel menülerini listele
$sorgu = $db->prepare("
    SELECT
        om.*,
        COUNT(omu.id) AS urun_sayisi
    FROM ozel_menuler om
    LEFT JOIN ozel_menu_urunleri omu ON omu.menu_id = om.id
    WHERE om.restoran_id = ?
    GROUP BY
        om.id,
        om.menu_adi,
        om.menu_fiyati,
        om.indirim_orani,
        om.indirim_baslangic,
        om.indirim_bitis,
        om.aktif,
        om.olusturma_tarihi,
        om.restoran_id
    ORDER BY om.olusturma_tarihi DESC
");

$sorgu->execute([$_SESSION["restoran_id"]]);

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

        .silme-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

         .silme-modal.aktif {
             display: flex;
         }

         .silme-modal-icerik {
             width: 100%;
             max-width: 420px;
             padding: 28px;
             background: white;
             border-radius: 12px;
             text-align: center;
             box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
        }

         .silme-modal-icerik h2 {
             color: #8b0000;
             margin-top: 0;
        }

         .silme-modal-icerik p {
             line-height: 1.6;
        }

         .silme-modal-icerik .uyari {
             color: #dc3545;
             font-size: 14px;
         }

         .silme-modal-butonlar {
             display: flex;
             justify-content: center;
             gap: 12px;
             margin-top: 22px;
        }

         .silme-modal-butonlar button {
             padding: 11px 18px;
             border: none;
             border-radius: 5px;
             color: white;
             cursor: pointer;
             font-size: 14px;
        }

         .iptal-buton {
             background: #555;
        }

         .onay-buton {
             background: #dc3545;
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

    <a href="restoran_paneli.php" class="buton geri">
    Restoran Paneline Dön
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

                
             <form method="POST" class="silme-formu" style="display:inline-block">
                   <input
                      type="hidden"
                      name="csrf_token"
                      value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>"
                    >

                     <input type="hidden" name="menu_id" value="<?= (int)$menu["id"] ?>">
                     <input type="hidden" name="menu_sil" value="1">

                     <button
                        type="button"
                        class="buton sil"
                        onclick="silmePenceresiniAc(this)"
                    >
                      Sil
                    </button>
             </form>


            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

<div id="silmePenceresi" class="silme-modal">
    <div class="silme-modal-icerik">
        <h2>Menüyü Sil</h2>

        <p>Bu özel menüyü silmek istediğinizden emin misiniz?</p>
        <p class="uyari">Bu işlem geri alınamaz.</p>

        <div class="silme-modal-butonlar">
            <button type="button" class="iptal-buton" onclick="silmePenceresiniKapat()">
                Vazgeç
            </button>

            <button type="button" class="onay-buton" onclick="menuyuSil()">
                Evet, Sil
            </button>
        </div>
    </div>
</div>

<script>
    let silinecekForm = null;

    function silmePenceresiniAc(buton) {
        silinecekForm = buton.closest("form");
        document.getElementById("silmePenceresi").classList.add("aktif");
    }

    function silmePenceresiniKapat() {
        document.getElementById("silmePenceresi").classList.remove("aktif");
        silinecekForm = null;
    }

    function menuyuSil() {
    if (!silinecekForm) {
        return;
    }

    const form = silinecekForm;
    silinecekForm = null;

    form.requestSubmit();
}

    document.getElementById("silmePenceresi").addEventListener("click", function (event) {
        if (event.target === this) {
            silmePenceresiniKapat();
        }
    });
</script>
</body>
</html>
