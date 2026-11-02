
<?php
session_start();

require_once __DIR__ . "/../config/baglanti.php";

// GİRİŞ KONTROLÜ
if (!isset($_SESSION["restoran_sahibi_id"], $_SESSION["restoran_id"])) {
    header("Location: restoran_giris.php");
    exit;
}

// CSRF TOKEN
if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$hata = "";
$mesaj = "";

$restoran_id = (int)$_SESSION["restoran_id"];

// MENÜ ID KONTROLÜ
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $menu_id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
} else {
    $menu_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
}

if (!$menu_id || $menu_id < 1) {
    die("Geçersiz menü ID.");
}

// SADECE BU RESTORANA AİT MENÜYÜ GETİR
$menuSorgu = $db->prepare("
    SELECT *
    FROM ozel_menuler
    WHERE id = ? AND restoran_id = ?
    LIMIT 1
");

$menuSorgu->execute([$menu_id, $restoran_id]);
$menu = $menuSorgu->fetch(PDO::FETCH_ASSOC);

if (!$menu) {
    die("Menü bulunamadı veya bu menüyü düzenleme yetkiniz yok.");
}

// FORM GÖNDERİLDİĞİNDE
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {

        // CSRF KONTROLÜ
        if (
            !isset($_POST["csrf_token"]) ||
            !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
        ) {
            throw new Exception("Güvenlik doğrulaması başarısız. Sayfayı yenileyin.");
        }

        $menu_adi = trim($_POST["menu_adi"] ?? "");
        $menu_fiyati = trim($_POST["menu_fiyati"] ?? "");
        $indirim_orani = trim($_POST["indirim_orani"] ?? "0");
        $baslangic = trim($_POST["indirim_baslangic"] ?? "");
        $bitis = trim($_POST["indirim_bitis"] ?? "");
        $secilen_urunler = $_POST["urunler"] ?? [];

        if ($menu_adi === "") {
            throw new Exception("Menü adı boş bırakılamaz.");
        }

        if (!is_numeric($menu_fiyati) || (float)$menu_fiyati < 0) {
            throw new Exception("Geçerli bir menü fiyatı girin.");
        }

        if (
            !is_numeric($indirim_orani) ||
            (float)$indirim_orani < 0 ||
            (float)$indirim_orani > 100
        ) {
            throw new Exception("İndirim oranı 0 ile 100 arasında olmalıdır.");
        }

        if (!is_array($secilen_urunler) || count($secilen_urunler) === 0) {
            throw new Exception("En az bir ürün seçmelisiniz.");
        }

        if (($baslangic === "") !== ($bitis === "")) {
            throw new Exception("İndirim başlangıç ve bitiş tarihlerini birlikte girin.");
        }

        if ($baslangic !== "") {

            $baslangic_zaman = strtotime($baslangic);
            $bitis_zaman = strtotime($bitis);

            if (
                $baslangic_zaman === false ||
                $bitis_zaman === false ||
                $baslangic_zaman >= $bitis_zaman
            ) {
                throw new Exception("İndirim bitişi, başlangıç tarihinden sonra olmalıdır.");
            }
        }

        // ÜRÜN ID'LERİNİ TEMİZLE
        $secilen_urunler = array_values(array_unique(
            array_filter(
                array_map("intval", $secilen_urunler),
                fn($id) => $id > 0
            )
        ));

        if (count($secilen_urunler) === 0) {
            throw new Exception("Geçerli ürün seçmelisiniz.");
        }

        // ÜRÜNLERİN BU RESTORANA AİT OLDUĞUNU KONTROL ET
        $yerler = implode(",", array_fill(0, count($secilen_urunler), "?"));

        $urunKontrol = $db->prepare("
            SELECT id
            FROM urunler
            WHERE aktif = 1
            AND restoran_id = ?
            AND id IN ($yerler)
        ");

        $urunKontrol->execute(array_merge(
            [$restoran_id],
            $secilen_urunler
        ));

        $gecerliUrunler = array_map(
            "intval",
            $urunKontrol->fetchAll(PDO::FETCH_COLUMN)
        );

        if (count($gecerliUrunler) !== count($secilen_urunler)) {
            throw new Exception("Seçilen ürünlerden biri bulunamadı veya aktif değil.");
        }

        // VERİTABANI İŞLEMLERİ
        $db->beginTransaction();

        // MENÜ BİLGİLERİNİ GÜNCELLE
        $guncelle = $db->prepare("
            UPDATE ozel_menuler
            SET menu_adi = ?,
                menu_fiyati = ?,
                indirim_orani = ?,
                indirim_baslangic = ?,
                indirim_bitis = ?
            WHERE id = ? AND restoran_id = ?
        ");

        $guncelle->execute([
            $menu_adi,
            (float)$menu_fiyati,
            (float)$indirim_orani,
            $baslangic !== "" ? date("Y-m-d H:i:s", strtotime($baslangic)) : null,
            $bitis !== "" ? date("Y-m-d H:i:s", strtotime($bitis)) : null,
            $menu_id,
            $restoran_id
        ]);

        // ESKİ ÜRÜN BAĞLANTILARINI SİL
        $sil = $db->prepare("
            DELETE FROM ozel_menu_urunleri
            WHERE menu_id = ?
        ");

        $sil->execute([$menu_id]);

        // YENİ ÜRÜNLERİ EKLE
        $urunEkle = $db->prepare("
            INSERT INTO ozel_menu_urunleri (menu_id, urun_id)
            VALUES (?, ?)
        ");

        foreach ($gecerliUrunler as $urun_id) {
            $urunEkle->execute([$menu_id, $urun_id]);
        }

        $db->commit();

        // TOKEN YENİLE
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

        header("Location: menu_guncelle.php?id=" . $menu_id . "&guncellendi=1");
        exit;

    } catch (PDOException $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $hata = "Menü güncellenemedi. Lütfen tekrar deneyin.";

    } catch (Exception $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $hata = $e->getMessage();
    }
}

// GÜNCEL MENÜ BİLGİLERİNİ TEKRAR GETİR
$menuSorgu->execute([$menu_id, $restoran_id]);
$menu = $menuSorgu->fetch(PDO::FETCH_ASSOC);

// SADECE BU RESTORANIN AKTİF ÜRÜNLERİNİ GETİR
$urunSorgu = $db->prepare("
    SELECT id, urun_adi, fiyat
    FROM urunler
    WHERE aktif = 1 AND restoran_id = ?
    ORDER BY urun_adi ASC
");

$urunSorgu->execute([$restoran_id]);
$urunler = $urunSorgu->fetchAll(PDO::FETCH_ASSOC);

// MENÜYE BAĞLI ÜRÜNLERİ GETİR
$bagliSorgu = $db->prepare("
    SELECT omu.urun_id
    FROM ozel_menu_urunleri omu
    INNER JOIN urunler u ON u.id = omu.urun_id
    WHERE omu.menu_id = ?
    AND u.restoran_id = ?
");

$bagliSorgu->execute([$menu_id, $restoran_id]);

$bagliUrunler = array_map(
    "intval",
    $bagliSorgu->fetchAll(PDO::FETCH_COLUMN)
);

// TARİHİ INPUT FORMATINA ÇEVİR
function tarihInputaCevir($tarih) {
    if (empty($tarih)) {
        return "";
    }

    return date("Y-m-d\TH:i", strtotime($tarih));
}

// HATA DURUMUNDA FORM DEĞERLERİNİ KORU
$form_menu_adi = $_POST["menu_adi"] ?? $menu["menu_adi"];
$form_menu_fiyati = $_POST["menu_fiyati"] ?? $menu["menu_fiyati"];
$form_indirim_orani = $_POST["indirim_orani"] ?? $menu["indirim_orani"];

$form_baslangic = isset($_POST["indirim_baslangic"])
    ? $_POST["indirim_baslangic"]
    : tarihInputaCevir($menu["indirim_baslangic"]);

$form_bitis = isset($_POST["indirim_bitis"])
    ? $_POST["indirim_bitis"]
    : tarihInputaCevir($menu["indirim_bitis"]);

if (isset($_POST["urunler"]) && is_array($_POST["urunler"])) {
    $seciliUrunler = array_values(array_unique(
        array_filter(
            array_map("intval", $_POST["urunler"]),
            fn($id) => $id > 0
        )
    ));
} else {
    $seciliUrunler = $bagliUrunler;
}

// BAŞARILI GÜNCELLEME MESAJI
if (isset($_GET["guncellendi"])) {
    $mesaj = "Menü başarıyla güncellendi.";
}

?>

<!DOCTYPE html>
<html lang="tr">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Özel Menüyü Güncelle</title>

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
    padding: 26px 5%;
    border-bottom: 4px solid #c49a58;
    box-shadow: 0 5px 20px rgba(70,0,15,0.18);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

header h1 {
    margin: 0;
    font-size: 26px;
}

header a {
    background: white;
    color: #650017;
    padding: 12px 17px;
    border-radius: 7px;
    text-decoration: none;
    font-weight: bold;
    transition: 0.3s;
}

header a:hover {
    background: #c49a58;
    color: white;
}

/* ANA ALAN */

.container {
    max-width: 850px;
    margin: 35px auto;
    padding: 0 18px;
}

.kutu {
    background: white;
    padding: 30px;
    border-radius: 13px;
    border-top: 5px solid #800020;
    box-shadow: 0 6px 22px rgba(75,20,30,0.09);
}

.kutu h2 {
    color: #800020;
    margin-top: 0;
    font-size: 24px;
}

.aciklama {
    color: #777;
    line-height: 1.6;
    margin-bottom: 25px;
}

/* FORM */

.form-grup {
    margin-bottom: 22px;
}

label.baslik {
    display: block;
    margin-bottom: 9px;
    color: #650017;
    font-weight: bold;
}

input[type="text"],
input[type="number"],
input[type="datetime-local"] {
    width: 100%;
    padding: 13px;
    border: 1px solid #d9c9c0;
    border-radius: 7px;
    font-size: 15px;
    background: #fffdfb;
    outline: none;
    transition: 0.3s;
}

input:focus {
    border-color: #800020;
    box-shadow: 0 0 0 3px rgba(128,0,32,0.08);
}

/* ÜRÜN LİSTESİ */

.urun-listesi {
    max-height: 320px;
    overflow-y: auto;
    padding: 10px 15px;
    border: 1px solid #e1d5ce;
    border-radius: 8px;
    background: #fffdfb;
}

.urun {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 5px;
    border-bottom: 1px solid #eee5df;
    transition: 0.2s;
}

.urun:hover {
    background: #faf5f0;
}

.urun:last-child {
    border-bottom: none;
}

.urun input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #800020;
    cursor: pointer;
    flex-shrink: 0;
}

.urun label {
    display: flex;
    flex: 1;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin: 0;
    cursor: pointer;
    font-weight: normal;
}

.urun-adi {
    color: #333;
}

.urun-fiyat {
    color: #800020;
    font-weight: bold;
    white-space: nowrap;
}

.urun-yok {
    padding: 15px;
    color: #777;
    text-align: center;
}

/* TOPLAM */

.toplam-kutu {
    margin-top: 15px;
    padding: 17px;
    background: #f8f1e9;
    border-left: 4px solid #c49a58;
    border-radius: 7px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.toplam-baslik {
    color: #654b37;
    font-weight: bold;
}

#toplam {
    color: #800020;
    font-size: 19px;
    font-weight: bold;
}

/* İNDİRİM ALANI */

.indirim-baslik {
    color: #800020;
    font-size: 18px;
    margin: 30px 0 18px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee5df;
}

.tarih-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

/* BUTONLAR */

.butonlar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 28px;
}

button.kaydet,
a.geri {
    display: inline-block;
    padding: 13px 20px;
    border: none;
    border-radius: 7px;
    font-size: 15px;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
    transition: 0.3s;
}

button.kaydet {
    background: #800020;
    color: white;
}

button.kaydet:hover {
    background: #a77c43;
    transform: translateY(-2px);
}

a.geri {
    background: #eee5df;
    color: #650017;
}

a.geri:hover {
    background: #c49a58;
    color: white;
}

/* MESAJLAR */

.mesaj,
.hata {
    padding: 14px;
    border-radius: 7px;
    margin-bottom: 20px;
    line-height: 1.5;
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

/* MOBİL */

@media (max-width: 600px) {

    header {
        padding: 22px 18px;
    }

    header h1 {
        font-size: 21px;
    }

    .container {
        margin: 22px auto;
    }

    .kutu {
        padding: 22px 17px;
    }

    .tarih-grid {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .butonlar {
        flex-direction: column;
    }

    .butonlar button,
    .butonlar a {
        width: 100%;
        text-align: center;
    }

    .urun label {
        font-size: 14px;
    }
}

</style>

</head>

<body>

<header>

    <h1>Özel Menüyü Güncelle</h1>

    <a href="menu_yonet.php">
        Menü Yönetimine Dön
    </a>

</header>

<main class="container">

    <section class="kutu">

        <h2>Menü Bilgilerini Düzenle</h2>

        <p class="aciklama">
            Menünüzün adını, ürünlerini, fiyatını ve indirim bilgilerini
            bu sayfadan güncelleyebilirsiniz.
        </p>

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

        <form method="POST">

            <input
                type="hidden"
                name="id"
                value="<?= (int)$menu_id ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>"
            >

            <div class="form-grup">

                <label class="baslik" for="menu_adi">
                    Menü Adı
                </label>

                <input
                    type="text"
                    id="menu_adi"
                    name="menu_adi"
                    maxlength="150"
                    required
                    value="<?= htmlspecialchars($form_menu_adi, ENT_QUOTES, "UTF-8") ?>"
                >

            </div>

            <div class="form-grup">

                <label class="baslik">
                    Menüye Eklenecek Ürünler
                </label>

                <div class="urun-listesi">

                    <?php if (count($urunler) > 0): ?>

                        <?php foreach ($urunler as $urun): ?>

                            <div class="urun">

                                <input
                                    type="checkbox"
                                    class="urun-sec"
                                    name="urunler[]"
                                    id="urun_<?= (int)$urun["id"] ?>"
                                    value="<?= (int)$urun["id"] ?>"
                                    data-fiyat="<?= htmlspecialchars((string)$urun["fiyat"], ENT_QUOTES, "UTF-8") ?>"
                                    <?= in_array(
                                        (int)$urun["id"],
                                        $seciliUrunler,
                                        true
                                    ) ? "checked" : "" ?>
                                >

                                <label for="urun_<?= (int)$urun["id"] ?>">

                                    <span class="urun-adi">
                                        <?= htmlspecialchars($urun["urun_adi"], ENT_QUOTES, "UTF-8") ?>
                                    </span>

                                    <span class="urun-fiyat">
                                        <?= number_format((float)$urun["fiyat"], 2, ",", ".") ?> TL
                                    </span>

                                </label>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="urun-yok">
                            Aktif ürün bulunamadı.
                        </div>

                    <?php endif; ?>

                </div>

                <div class="toplam-kutu">

                    <span class="toplam-baslik">
                        Seçilen Ürünlerin Toplamı:
                    </span>

                    <span id="toplam">
                        0,00 TL
                    </span>

                </div>

            </div>

            <div class="form-grup">

                <label class="baslik" for="menu_fiyati">
                    Menü Fiyatı (TL)
                </label>

                <input
                    type="number"
                    id="menu_fiyati"
                    name="menu_fiyati"
                    min="0"
                    step="0.01"
                    required
                    value="<?= htmlspecialchars((string)$form_menu_fiyati, ENT_QUOTES, "UTF-8") ?>"
                >

            </div>

            <h3 class="indirim-baslik">
                İndirim Ayarları
            </h3>

            <div class="form-grup">

                <label class="baslik" for="indirim_orani">
                    İndirim Oranı (%)
                </label>

                <input
                    type="number"
                    id="indirim_orani"
                    name="indirim_orani"
                    min="0"
                    max="100"
                    step="0.01"
                    required
                    value="<?= htmlspecialchars((string)$form_indirim_orani, ENT_QUOTES, "UTF-8") ?>"
                >

            </div>

            <div class="tarih-grid">

                <div class="form-grup">

                    <label class="baslik" for="indirim_baslangic">
                        İndirim Başlangıç Tarihi
                    </label>

                    <input
                        type="datetime-local"
                        id="indirim_baslangic"
                        name="indirim_baslangic"
                        value="<?= htmlspecialchars($form_baslangic, ENT_QUOTES, "UTF-8") ?>"
                    >

                </div>

                <div class="form-grup">

                    <label class="baslik" for="indirim_bitis">
                        İndirim Bitiş Tarihi
                    </label>

                    <input
                        type="datetime-local"
                        id="indirim_bitis"
                        name="indirim_bitis"
                        value="<?= htmlspecialchars($form_bitis, ENT_QUOTES, "UTF-8") ?>"
                    >

                </div>

            </div>

            <div class="butonlar">

                <button type="submit" class="kaydet">
                    Değişiklikleri Kaydet
                </button>

                <a href="menu_yonet.php" class="geri">
                    Menü Yönetimine Dön
                </a>

            </div>

        </form>

    </section>

</main>

<script>

const secimler = document.querySelectorAll(".urun-sec");
const toplamAlan = document.getElementById("toplam");

function toplamHesapla() {

    let toplam = 0;

    secimler.forEach(function(secim) {

        if (secim.checked) {
            toplam += Number(secim.dataset.fiyat);
        }

    });

    toplamAlan.textContent = toplam.toLocaleString("tr-TR", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }) + " TL";

}

secimler.forEach(function(secim) {
    secim.addEventListener("change", toplamHesapla);
});

toplamHesapla();

</script>

</body>
</html>
