
<?php
session_start();

require_once __DIR__ . "/../config/baglanti.php";

if (!isset($_SESSION["restoran_sahibi_id"], $_SESSION["restoran_id"])) {
    header("Location: restoran_giris.php");
    exit;
}

if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$mesaj = "";
$hata = "";

// MENÜ SİLME
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

        $sil->execute([
            $menu_id,
            $_SESSION["restoran_id"]
        ]);

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

// MENÜLERİ LİSTELE
$sorgu = $db->prepare("
    SELECT
        om.*,
        COUNT(omu.id) AS urun_sayisi
    FROM ozel_menuler om
    LEFT JOIN ozel_menu_urunleri omu
        ON omu.menu_id = om.id
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
    font-family: Arial, sans-serif;
    background: #f4eee8;
    color: #333;
}

/* ÜST ALAN */

header {
    background: linear-gradient(120deg, #590014, #8b001e, #650017);
    color: white;
    padding: 27px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    border-bottom: 4px solid #c49a58;
    box-shadow: 0 5px 20px rgba(70,0,15,0.18);
}

header h1 {
    margin: 0;
    font-size: 27px;
    letter-spacing: 0.5px;
}

header a {
    color: #650017;
    background: white;
    padding: 12px 17px;
    border-radius: 7px;
    font-weight: bold;
    text-decoration: none;
    transition: 0.3s;
}

header a:hover {
    background: #c49a58;
    color: white;
}

/* ANA ALAN */

.container {
    max-width: 1100px;
    margin: 35px auto;
    padding: 0 18px;
}

.sayfa-baslik {
    color: #650017;
    margin-bottom: 22px;
}

/* BUTONLAR */

.buton {
    display: inline-block;
    padding: 12px 17px;
    border: none;
    border-radius: 7px;
    color: white;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
    transition: 0.3s;
}

.buton:hover {
    transform: translateY(-2px);
}

.yeni {
    background: #800020;
}

.yeni:hover {
    background: #a77c43;
}

.guncelle {
    background: #800020;
}

.guncelle:hover {
    background: #a77c43;
}

.sil {
    background: #a61b1b;
}

.sil:hover {
    background: #7f1414;
}

/* ÜST İŞLEM ALANI */

.islem-alani {
    background: white;
    padding: 22px;
    border-radius: 12px;
    margin-bottom: 28px;
    border-top: 4px solid #800020;
    box-shadow: 0 5px 18px rgba(75,20,30,0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

/* MENÜ KARTLARI */

.menu-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
}

.menu-kart {
    background: white;
    padding: 25px;
    border-radius: 13px;
    border: 1px solid #eadbd2;
    border-top: 4px solid #800020;
    box-shadow: 0 5px 18px rgba(75,20,30,0.08);
    transition: 0.3s;
}

.menu-kart:hover {
    transform: translateY(-5px);
    border-top-color: #c49a58;
    box-shadow: 0 10px 25px rgba(75,20,30,0.14);
}

.menu-kart h3 {
    color: #800020;
    font-size: 22px;
    margin-top: 0;
    margin-bottom: 20px;
}

/* MENÜ BİLGİLERİ */

.bilgi {
    line-height: 2;
    color: #555;
    font-size: 14px;
}

.bilgi strong {
    color: #650017;
}

.bilgi-satiri {
    border-bottom: 1px solid #f0e6df;
    padding: 5px 0;
}

.fiyat {
    color: #800020;
    font-weight: bold;
    font-size: 17px;
}

.indirim-aktif {
    display: inline-block;
    color: #16703a;
    background: #eaf7ee;
    padding: 5px 10px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 12px;
}

.indirim-pasif {
    display: inline-block;
    color: #94651d;
    background: #fff5df;
    padding: 5px 10px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 12px;
}

.indirim-yok {
    display: inline-block;
    color: #666;
    background: #eee;
    padding: 5px 10px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 12px;
}

.menu-islemler {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 20px;
}

/* MESAJLAR */

.mesaj, .hata {
    padding: 14px;
    margin: 18px 0;
    border-radius: 7px;
}

.mesaj {
    background: #eaf7ee;
    color: #16703a;
    border-left: 4px solid #238447;
}

.hata {
    background: #fff0f0;
    color: #9c1825;
    border-left: 4px solid #b71c1c;
}

/* BOŞ MENÜ */

.bos {
    background: white;
    padding: 30px;
    border-radius: 12px;
    border-top: 4px solid #c49a58;
    color: #777;
    text-align: center;
    box-shadow: 0 5px 18px rgba(75,20,30,0.08);
}

/* SİLME MODALI */

.silme-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(35,0,10,0.65);
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
    border-radius: 14px;
    text-align: center;
    border-top: 5px solid #800020;
    box-shadow: 0 15px 45px rgba(0,0,0,0.2);
}

.silme-modal-icerik h2 {
    color: #800020;
    margin-top: 0;
}

.silme-modal-icerik p {
    line-height: 1.6;
    color: #555;
}

.silme-modal-icerik .uyari {
    color: #a61b1b;
    font-size: 14px;
}

.silme-modal-butonlar {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 22px;
    flex-wrap: wrap;
}

.silme-modal-butonlar button {
    padding: 11px 18px;
    border: none;
    border-radius: 7px;
    color: white;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
}

.iptal-buton {
    background: #777;
}

.onay-buton {
    background: #800020;
}

/* MOBİL */

@media (max-width: 650px) {

    header {
        padding: 23px 18px;
    }

    header h1 {
        font-size: 22px;
    }

    .container {
        margin: 25px auto;
    }

    .menu-grid {
        grid-template-columns: 1fr;
    }

    .islem-alani {
        align-items: stretch;
        flex-direction: column;
    }

    .islem-alani .buton {
        text-align: center;
    }

    .menu-islemler {
        flex-direction: column;
    }

    .menu-islemler .buton {
        text-align: center;
        width: 100%;
    }
}

</style>

</head>

<body>

<header>

    <h1>Özel Menü Yönetimi</h1>

    <a href="restoran_paneli.php">
        Restoran Paneline Dön
    </a>

</header>

<main class="container">

    <h2 class="sayfa-baslik">Menüleriniz</h2>

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

    <section class="islem-alani">

        <div>
            <strong style="color:#650017;">Özel Menü İşlemleri</strong>
            <p style="color:#777; margin-bottom:0;">
                Yeni menüler oluşturabilir ve mevcut menülerini yönetebilirsin.
            </p>
        </div>

        <a href="menu_olustur.php" class="buton yeni">
            + Yeni Menü Oluştur
        </a>

    </section>

    <?php if (empty($menuler)): ?>

        <div class="bos">
            <h3 style="color:#800020;">Henüz özel menü bulunmuyor.</h3>
            <p>Yeni Menü Oluştur butonunu kullanarak ilk menünü oluşturabilirsin.</p>
        </div>

    <?php else: ?>

        <div class="menu-grid">

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

            <article class="menu-kart">

                <h3>
                    <?= htmlspecialchars($menu["menu_adi"], ENT_QUOTES, "UTF-8") ?>
                </h3>

                <div class="bilgi">

                    <div class="bilgi-satiri">
                        <strong>Menü fiyatı:</strong>
                        <span class="fiyat">
                            <?= tlYaz($menu["menu_fiyati"]) ?>
                        </span>
                    </div>

                    <div class="bilgi-satiri">
                        <strong>Ürün sayısı:</strong>
                        <?= (int)$menu["urun_sayisi"] ?> ürün
                    </div>

                    <div class="bilgi-satiri">
                        <strong>İndirim oranı:</strong>
                        %<?= number_format($indirimOrani, 2, ',', '.') ?>
                    </div>

                    <div class="bilgi-satiri">
                        <strong>İndirim durumu:</strong>

                        <?php if ($indirimAktif): ?>

                            <span class="indirim-aktif">Aktif</span>

                        <?php elseif ($indirimOrani <= 0): ?>

                            <span class="indirim-yok">İndirim yok</span>

                        <?php elseif (!empty($bitis) && time() > strtotime($bitis)): ?>

                            <span class="indirim-pasif">Süresi dolmuş</span>

                        <?php else: ?>

                            <span class="indirim-pasif">Henüz aktif değil</span>

                        <?php endif; ?>

                    </div>

                    <?php if ($indirimAktif): ?>

                        <div class="bilgi-satiri">
                            <strong>İndirimli fiyat:</strong>
                            <span class="fiyat">
                                <?= tlYaz($indirimliFiyat) ?>
                            </span>
                        </div>

                    <?php endif; ?>

                    <div class="bilgi-satiri">
                        <strong>Başlangıç:</strong>
                        <?= !empty($baslangic)
                            ? htmlspecialchars($baslangic, ENT_QUOTES, "UTF-8")
                            : "Belirtilmedi" ?>
                    </div>

                    <div class="bilgi-satiri">
                        <strong>Bitiş:</strong>
                        <?= !empty($bitis)
                            ? htmlspecialchars($bitis, ENT_QUOTES, "UTF-8")
                            : "Belirtilmedi" ?>
                    </div>

                </div>

                <div class="menu-islemler">

                    <a
                        href="menu_guncelle.php?id=<?= (int)$menu["id"] ?>"
                        class="buton guncelle"
                    >
                        Menüyü Güncelle
                    </a>

                    <form method="POST" class="silme-formu">

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

                        <input type="hidden" name="menu_sil" value="1">

                        <button
                            type="button"
                            class="buton sil"
                            onclick="silmePenceresiniAc(this)"
                        >
                            Menüyü Sil
                        </button>

                    </form>

                </div>

            </article>

        <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>

<!-- MENÜ SİLME PENCERESİ -->

<div id="silmePenceresi" class="silme-modal">

    <div class="silme-modal-icerik">

        <h2>Menüyü Sil</h2>

        <p>
            Bu özel menüyü silmek istediğinizden emin misiniz?
        </p>

        <p class="uyari">
            Bu işlem geri alınamaz.
        </p>

        <div class="silme-modal-butonlar">

            <button
                type="button"
                class="iptal-buton"
                onclick="silmePenceresiniKapat()"
            >
                Vazgeç
            </button>

            <button
                type="button"
                class="onay-buton"
                onclick="menuyuSil()"
            >
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

document.getElementById("silmePenceresi").addEventListener("click", function(event) {

    if (event.target === this) {
        silmePenceresiniKapat();
    }

});

</script>

</body>
</html>
