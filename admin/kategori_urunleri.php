
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

// KATEGORİ KONTROLÜ
$kategori_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$kategori_id || $kategori_id < 1) {
    exit("Geçersiz kategori. <a href='urunler.php'>Geri dön</a>");
}

$kontrol = $db->prepare("
    SELECT id, kategori_adi
    FROM kategoriler
    WHERE id = ? AND restoran_id = ?
");

$kontrol->execute([
    $kategori_id,
    $_SESSION["restoran_id"]
]);

$kategori = $kontrol->fetch(PDO::FETCH_ASSOC);

if (!$kategori) {
    exit("Kategori bulunamadı. <a href='urunler.php'>Geri dön</a>");
}

$mesaj = "";
$hata = "";

// FOTOĞRAF BAĞLANTISI KONTROLÜ
function gecerliFotografUrl($url)
{
    if (!is_string($url) || $url === "" || strlen($url) > 2048) {
        return false;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $parca = parse_url($url);

    if (!$parca || !isset($parca["scheme"], $parca["host"])) {
        return false;
    }

    return in_array(
        strtolower($parca["scheme"]),
        ["http", "https"],
        true
    );
}

// FORM DEĞERLERİ
$urun_adi = "";
$fiyat = "";
$aciklama = "";
$fotograf_url = "";

// YENİ ÜRÜN EKLEME
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {

        $hata = "Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.";

    } else {

        $urun_adi = trim($_POST["urun_adi"] ?? "");
        $aciklama = trim($_POST["aciklama"] ?? "");
        $fiyat_girdi = trim($_POST["fiyat"] ?? "");
        $fiyat = str_replace(",", ".", $fiyat_girdi);
        $fotograf_url = trim($_POST["fotograf_url"] ?? "");

        if ($urun_adi === "") {

            $hata = "Ürün adı zorunludur.";

        } elseif (
            $fiyat === "" ||
            !is_numeric($fiyat) ||
            !is_finite((float)$fiyat) ||
            (float)$fiyat < 0
        ) {

            $hata = "Geçerli ve negatif olmayan bir fiyat girin.";

        } elseif (
            $fotograf_url !== "" &&
            !gecerliFotografUrl($fotograf_url)
        ) {

            $hata = "Geçerli bir fotoğraf bağlantısı girin. Bağlantı http:// veya https:// ile başlamalıdır.";

        } else {

            try {

                $fotograf = $fotograf_url !== ""
                    ? $fotograf_url
                    : null;

                $ekle = $db->prepare("
                    INSERT INTO urunler
                    (
                        kategori_id,
                        urun_adi,
                        aciklama,
                        fiyat,
                        fotograf,
                        restoran_id
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $ekle->execute([
                    $kategori_id,
                    $urun_adi,
                    $aciklama,
                    $fiyat,
                    $fotograf,
                    $_SESSION["restoran_id"]
                ]);

                $mesaj = "Ürün başarıyla eklendi.";

                // Formu temizle
                $urun_adi = "";
                $fiyat = "";
                $aciklama = "";
                $fotograf_url = "";

                // CSRF TOKEN YENİLE
                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            } catch (PDOException $e) {

                error_log("Ürün ekleme hatası: " . $e->getMessage());

                $hata = "Ürün kaydedilemedi. Veritabanı hatasını kontrol edin.";
            }
        }
    }
}

// KATEGORİDEKİ ÜRÜNLERİ GETİR
$liste = $db->prepare("
    SELECT id, urun_adi, aciklama, fiyat, fotograf
    FROM urunler
    WHERE kategori_id = ? AND restoran_id = ?
    ORDER BY id DESC
");

$liste->execute([
    $kategori_id,
    $_SESSION["restoran_id"]
]);

$urunler = $liste->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="tr">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= htmlspecialchars($kategori["kategori_adi"], ENT_QUOTES, "UTF-8") ?> - Ürün Yönetimi</title>

<style>

* {
    box-sizing: border-box;
}

:root {
    --bordo: #800020;
    --bordo-koyu: #590014;
    --bordo-acik: #a0002a;
    --altin: #c49a58;
    --krem: #f4eee8;
    --beyaz: #ffffff;
    --metin: #352b2b;
    --soluk: #786d68;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: var(--krem);
    color: var(--metin);
}

/* ÜST ALAN */

header {
    padding: 30px 6%;
    color: white;
    background:
        radial-gradient(circle at 90% 20%, rgba(196,154,88,.16), transparent 25%),
        linear-gradient(120deg, #590014, #8b001e, #650017);
    border-bottom: 4px solid var(--altin);
}

.header-icerik {
    max-width: 1150px;
    margin: auto;
}

.ust-etiket {
    display: inline-block;
    color: var(--altin);
    font-size: 12px;
    font-weight: bold;
    letter-spacing: 2px;
    margin-bottom: 10px;
}

header h1 {
    margin: 0 0 14px;
    font-size: 30px;
}

header a {
    color: white;
    text-decoration: none;
    font-weight: bold;
    font-size: 14px;
}

header a:hover {
    color: var(--altin);
}

/* ANA ALAN */

main {
    max-width: 1150px;
    margin: 30px auto;
    padding: 0 18px;
}

/* KARTLAR */

.kutu {
    background: var(--beyaz);
    padding: 26px;
    border-radius: 13px;
    margin-bottom: 25px;
    border-top: 4px solid var(--bordo);
    box-shadow: 0 5px 20px rgba(65,25,25,.07);
}

.kutu h2 {
    color: var(--bordo);
    margin: 0 0 22px;
    font-size: 21px;
}

/* FORM */

label {
    display: block;
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 5px;
    color: #493b38;
}

input,
textarea,
button {
    font: inherit;
}

input:not([type="radio"]),
textarea {
    width: 100%;
    padding: 13px 14px;
    margin: 7px 0 18px;
    border: 1px solid #ded5cf;
    border-radius: 7px;
    background: #fffdfa;
    color: var(--metin);
    outline: none;
}

input:focus,
textarea:focus {
    border-color: var(--altin);
    box-shadow: 0 0 0 3px rgba(196,154,88,.13);
}

textarea {
    min-height: 95px;
    resize: vertical;
}

button {
    cursor: pointer;
}

/* FOTOĞRAF BAĞLANTISI */

.fotograf-bolumu {
    margin-top: 25px;
    padding: 20px;
    background: #faf6f0;
    border: 1px solid #eee2d3;
    border-radius: 10px;
}

.fotograf-bolumu h3 {
    margin: 0 0 10px;
    color: var(--bordo);
    font-size: 19px;
}

.fotograf-aciklama {
    color: var(--soluk);
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 17px;
}

.fotograf-ekle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 17px;
    border: 1px solid var(--altin);
    border-radius: 8px;
    background: white;
    color: var(--bordo);
    font-weight: bold;
    cursor: pointer;
    transition: .2s;
}

.fotograf-ekle:hover {
    background: var(--bordo);
    color: white;
    border-color: var(--bordo);
}

.baglanti-alani {
    display: none;
    margin-top: 20px;
}

.baglanti-alani.acik {
    display: block;
}

.baglanti-alani label {
    color: var(--bordo);
}

.baglanti-butonlari {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.onizle-buton,
.temizle-buton {
    padding: 10px 15px;
    border: none;
    border-radius: 7px;
    font-weight: bold;
}

.onizle-buton {
    background: var(--bordo);
    color: white;
}

.temizle-buton {
    background: #e9e1db;
    color: #493b38;
}

.onizleme-alani {
    display: none;
    align-items: center;
    gap: 17px;
    flex-wrap: wrap;
    margin-top: 18px;
    padding: 15px;
    background: white;
    border: 1px solid #eee2d3;
    border-radius: 10px;
}

.onizleme-alani.goster {
    display: flex;
}

.onizleme-alani img {
    width: 135px;
    height: 105px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid var(--altin);
    background: #f4eee8;
}

.onizleme-yazi {
    flex: 1;
    min-width: 180px;
}

.onizleme-yazi strong {
    color: var(--bordo);
}

.onizleme-yazi p {
    color: var(--soluk);
    font-size: 13px;
    overflow-wrap: anywhere;
}

.fotograf-hata {
    display: none;
    color: #a00020;
    font-size: 13px;
    margin-top: 10px;
}

.fotograf-hata.goster {
    display: block;
}

/* KAYDET BUTONU */

form > button[type="submit"] {
    width: 100%;
    padding: 15px;
    margin-top: 20px;
    border: none;
    border-radius: 7px;
    color: white;
    background: linear-gradient(120deg, var(--bordo), var(--bordo-koyu));
    font-weight: bold;
    transition: .2s;
}

form > button[type="submit"]:hover {
    background: var(--bordo-acik);
    box-shadow: 0 5px 14px rgba(128,0,32,.2);
}

/* MESAJLAR */

.mesaj,
.hata {
    padding: 13px 15px;
    border-radius: 7px;
    font-weight: bold;
    margin-bottom: 18px;
}

.mesaj {
    color: #176b39;
    background: #e9f7ee;
    border-left: 4px solid #27864b;
}

.hata {
    color: #a00020;
    background: #fff0f0;
    border-left: 4px solid var(--bordo);
}

/* ÜRÜN TABLOSU */

.tablo {
    width: 100%;
    overflow-x: auto;
    border-radius: 9px;
    border: 1px solid #eee5df;
}

table {
    width: 100%;
    min-width: 700px;
    border-collapse: collapse;
    background: white;
}

th,
td {
    text-align: left;
    padding: 14px 12px;
    border-bottom: 1px solid #eee7e1;
    vertical-align: middle;
}

th {
    color: var(--bordo);
    background: #faf5f0;
    font-size: 13px;
}

td {
    font-size: 14px;
}

tbody tr:hover {
    background: #fffaf5;
}

tbody tr:last-child td {
    border-bottom: none;
}

td a {
    display: inline-block;
    color: var(--bordo);
    margin: 4px 8px 8px 0;
    font-weight: bold;
    text-decoration: none;
}

td a:hover {
    color: var(--bordo-acik);
    text-decoration: underline;
}

.urun-fotografi {
    width: 68px;
    height: 58px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #eee0d4;
    background: #f4eee8;
}

.fotograf-yok {
    color: #999;
    font-size: 12px;
}

/* SİLME BUTONU */

.sil-formu {
    display: inline-block;
    margin: 0;
}

.sil-formu button {
    width: auto;
    min-width: 75px;
    margin: 4px 0;
    padding: 9px 14px;
    color: white;
    background: var(--bordo);
    border: none;
    border-radius: 6px;
    font-weight: bold;
}

.sil-formu button:hover {
    background: #a0002a;
}

/* SİLME MODALI */

.sil-modal {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    background: rgba(30,5,10,.72);
    backdrop-filter: blur(3px);
}

.sil-modal.gizli {
    display: none;
}

.sil-modal-kutu {
    width: 100%;
    max-width: 430px;
    padding: 30px;
    background: white;
    border-radius: 15px;
    border-top: 5px solid var(--bordo);
    box-shadow: 0 18px 55px rgba(0,0,0,.3);
    text-align: center;
}

.sil-modal-simge {
    width: 62px;
    height: 62px;
    margin: 0 auto 17px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #fff0f0;
    color: var(--bordo);
    border: 2px solid #f0d2d5;
    font-size: 30px;
    font-weight: bold;
}

.sil-modal-kutu h2 {
    margin: 0 0 12px;
    color: var(--bordo);
    font-size: 22px;
}

.sil-modal-kutu p {
    color: #716662;
    line-height: 1.7;
    font-size: 14px;
}

.sil-modal-butonlar {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}

.sil-modal-butonlar button {
    flex: 1;
    padding: 13px;
    border-radius: 7px;
    font-weight: bold;
    border: none;
}

.sil-vazgec {
    background: #eee9e5;
    color: #493d38;
}

.sil-onayla {
    background: var(--bordo);
    color: white;
}

/* MOBİL */

@media (max-width: 650px) {

    header {
        padding: 25px 20px;
    }

    header h1 {
        font-size: 24px;
    }

    main {
        margin: 20px auto;
        padding: 0 12px;
    }

    .kutu {
        padding: 19px 15px;
        border-radius: 10px;
    }

    .kutu h2 {
        font-size: 19px;
    }

    .onizleme-alani img {
        width: 105px;
        height: 85px;
    }

    .sil-modal-kutu {
        padding: 23px 18px;
    }

    .sil-modal-butonlar {
        flex-direction: column;
    }
}

</style>

</head>

<body>

<header>

    <div class="header-icerik">

        <span class="ust-etiket">ÜRÜN YÖNETİMİ</span>

        <h1>
            <?= htmlspecialchars($kategori["kategori_adi"], ENT_QUOTES, "UTF-8") ?>
        </h1>

        <a href="urunler.php">← Kategorilere Dön</a>

    </div>

</header>

<main>

    <!-- YENİ ÜRÜN EKLEME -->

    <section class="kutu">

        <h2>Yeni Ürün Ekle</h2>

        <?php if ($mesaj): ?>
            <p class="mesaj">
                <?= htmlspecialchars($mesaj, ENT_QUOTES, "UTF-8") ?>
            </p>
        <?php endif; ?>

        <?php if ($hata): ?>
            <p class="hata">
                <?= htmlspecialchars($hata, ENT_QUOTES, "UTF-8") ?>
            </p>
        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>"
            >

            <label for="urun_adi">Ürün adı</label>

            <input
                id="urun_adi"
                name="urun_adi"
                maxlength="150"
                required
                placeholder="Örn. Adana Kebap"
                value="<?= htmlspecialchars($urun_adi, ENT_QUOTES, "UTF-8") ?>"
            >

            <label for="fiyat">Fiyat (TL)</label>

            <input
                id="fiyat"
                name="fiyat"
                type="number"
                min="0"
                step="0.01"
                required
                placeholder="Örn. 250"
                value="<?= htmlspecialchars($fiyat, ENT_QUOTES, "UTF-8") ?>"
            >

            <label for="aciklama">Açıklama</label>

            <textarea
                id="aciklama"
                name="aciklama"
                placeholder="Ürün hakkında kısa bilgi"
            ><?= htmlspecialchars($aciklama, ENT_QUOTES, "UTF-8") ?></textarea>

            <!-- FOTOĞRAF BAĞLANTISI -->

            <div class="fotograf-bolumu">

                <h3>Ürün Fotoğrafı</h3>

                <p class="fotograf-aciklama">
                    Bilgisayarından fotoğraf yüklemene gerek yok.
                    İnternetteki yemek fotoğrafının doğrudan bağlantısını buraya ekleyebilirsin.
                    Fotoğraf eklemek zorunlu değildir.
                </p>

                <button
                    type="button"
                    class="fotograf-ekle"
                    id="fotografAc"
                >
                    + Fotoğraf bağlantısı ekle
                </button>

                <div class="baglanti-alani" id="baglantiAlani">

                    <label for="fotograf_url">
                        Fotoğraf bağlantısını buraya yapıştır
                    </label>

                    <input
                        type="url"
                        id="fotograf_url"
                        name="fotograf_url"
                        maxlength="2048"
                        placeholder="https://ornek-site.com/yemek-fotografi.jpg"
                        value="<?= htmlspecialchars($fotograf_url, ENT_QUOTES, "UTF-8") ?>"
                    >

                    <div class="baglanti-butonlari">

                        <button
                            type="button"
                            class="onizle-buton"
                            id="onizleButon"
                        >
                            Fotoğrafı Önizle
                        </button>

                        <button
                            type="button"
                            class="temizle-buton"
                            id="temizleButon"
                        >
                            Bağlantıyı Temizle
                        </button>

                    </div>

                    <p class="fotograf-hata" id="fotografHata">
                        Fotoğraf yüklenemedi. Doğrudan fotoğraf bağlantısı kullandığından emin ol.
                    </p>

                </div>

                <div class="onizleme-alani" id="onizlemeAlani">

                    <img
                        id="seciliGorsel"
                        src=""
                        alt="Ürün fotoğrafı önizlemesi"
                    >

                    <div class="onizleme-yazi">

                        <strong>Fotoğraf önizlemesi</strong>

                        <p id="seciliGorselAdi"></p>

                    </div>

                </div>

            </div>

            <button type="submit">
                Ürünü Kaydet
            </button>

        </form>

    </section>

    <!-- ÜRÜN LİSTESİ -->

    <section class="kutu">

        <h2>Bu Kategorideki Ürünler</h2>

        <?php if (!$urunler): ?>

            <p>Bu kategoride henüz ürün yok.</p>

        <?php else: ?>

            <div class="tablo">

                <table>

                    <thead>

                        <tr>
                            <th>Fotoğraf</th>
                            <th>Ürün</th>
                            <th>Açıklama</th>
                            <th>Fiyat</th>
                            <th>İşlemler</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($urunler as $urun): ?>

                        <tr>

                            <td>

                                <?php
                                $urunFotografi = trim($urun["fotograf"] ?? "");
                                ?>

                                <?php if (gecerliFotografUrl($urunFotografi)): ?>

                                    <img
                                        class="urun-fotografi"
                                        src="<?= htmlspecialchars($urunFotografi, ENT_QUOTES, "UTF-8") ?>"
                                        alt="<?= htmlspecialchars($urun["urun_adi"], ENT_QUOTES, "UTF-8") ?>"
                                        loading="lazy"
                                    >

                                <?php else: ?>

                                    <span class="fotograf-yok">
                                        Fotoğraf yok
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= htmlspecialchars($urun["urun_adi"], ENT_QUOTES, "UTF-8") ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($urun["aciklama"] ?? "", ENT_QUOTES, "UTF-8") ?>
                            </td>

                            <td>
                                <?= number_format((float)$urun["fiyat"], 2, ",", ".") ?> TL
                            </td>

                            <td>

                                <a href="urun_duzenle.php?id=<?= (int)$urun["id"] ?>">
                                    Güncelle
                                </a>

                                <form
                                    action="urun_islem.php"
                                    method="POST"
                                    class="sil-formu"
                                >

                                    <input
                                        type="hidden"
                                        name="urun_id"
                                        value="<?= (int)$urun["id"] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="islem"
                                        value="sil"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>"
                                    >

                                    <button type="submit">Sil</button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</main>

<!-- ÜRÜN SİLME PENCERESİ -->

<div
    id="silModal"
    class="sil-modal gizli"
    role="dialog"
    aria-modal="true"
    aria-labelledby="silBaslik"
>

    <div class="sil-modal-kutu">

        <div class="sil-modal-simge">!</div>

        <h2 id="silBaslik">Ürünü silmek istiyor musun?</h2>

        <p>
            Bu ürün menüden kalıcı olarak silinecek.
            Bu işlem geri alınamaz.
        </p>

        <div class="sil-modal-butonlar">

            <button
                type="button"
                class="sil-vazgec"
                id="silVazgec"
            >
                Vazgeç
            </button>

            <button
                type="button"
                class="sil-onayla"
                id="silOnayla"
            >
                Evet, ürünü sil
            </button>

        </div>

    </div>

</div>

<script>

document.addEventListener("DOMContentLoaded", function () {

    // FOTOĞRAF BAĞLANTISI ALANINI AÇMA

    const fotografAc = document.getElementById("fotografAc");
    const baglantiAlani = document.getElementById("baglantiAlani");
    const fotografInput = document.getElementById("fotograf_url");

    const onizlemeButon = document.getElementById("onizleButon");
    const temizleButon = document.getElementById("temizleButon");

    const onizlemeAlani = document.getElementById("onizlemeAlani");
    const seciliGorsel = document.getElementById("seciliGorsel");
    const seciliGorselAdi = document.getElementById("seciliGorselAdi");

    const fotografHata = document.getElementById("fotografHata");

    fotografAc.addEventListener("click", function () {

        baglantiAlani.classList.toggle("acik");

        if (baglantiAlani.classList.contains("acik")) {
            fotografInput.focus();
        }

    });

    // ÖNİZLEME

    function fotografOnizle() {

        const url = fotografInput.value.trim();

        fotografHata.classList.remove("goster");

        if (url === "") {

            onizlemeAlani.classList.remove("goster");
            seciliGorsel.removeAttribute("src");

            return;
        }

        try {

            const adres = new URL(url);

            if (!["http:", "https:"].includes(adres.protocol)) {
                throw new Error("Geçersiz bağlantı");
            }

        } catch (error) {

            fotografHata.textContent = "Geçerli bir fotoğraf bağlantısı gir.";
            fotografHata.classList.add("goster");
            onizlemeAlani.classList.remove("goster");

            return;
        }

        seciliGorsel.onload = function () {

            onizlemeAlani.classList.add("goster");
            fotografHata.classList.remove("goster");

            seciliGorselAdi.textContent = url;

        };

        seciliGorsel.onerror = function () {

            onizlemeAlani.classList.remove("goster");

            fotografHata.textContent =
                "Fotoğraf açılamadı. Doğrudan görsel bağlantısı kullandığından emin ol.";

            fotografHata.classList.add("goster");

        };

        seciliGorsel.src = url;

    }

    onizlemeButon.addEventListener("click", fotografOnizle);

    fotografInput.addEventListener("keydown", function (event) {

        if (event.key === "Enter") {
            event.preventDefault();
            fotografOnizle();
        }

    });

    // BAĞLANTIYI TEMİZLE

    temizleButon.addEventListener("click", function () {

        fotografInput.value = "";

        seciliGorsel.removeAttribute("src");

        onizlemeAlani.classList.remove("goster");
        fotografHata.classList.remove("goster");

        fotografInput.focus();

    });

    // SİLME MODALI

    const modal = document.getElementById("silModal");
    const vazgec = document.getElementById("silVazgec");
    const onayla = document.getElementById("silOnayla");

    let secilenForm = null;

    document.querySelectorAll(".sil-formu").forEach(function (form) {

        form.addEventListener("submit", function (event) {

            event.preventDefault();

            secilenForm = form;

            modal.classList.remove("gizli");

            vazgec.focus();

        });

    });

    vazgec.addEventListener("click", function () {

        modal.classList.add("gizli");

        secilenForm = null;

    });

    onayla.addEventListener("click", function () {

        if (secilenForm) {

            onayla.disabled = true;
            onayla.textContent = "Siliniyor...";

            secilenForm.submit();

        }

    });

    modal.addEventListener("click", function (event) {

        if (event.target === modal) {

            modal.classList.add("gizli");

            secilenForm = null;

        }

    });

    document.addEventListener("keydown", function (event) {

        if (
            event.key === "Escape" &&
            !modal.classList.contains("gizli")
        ) {

            modal.classList.add("gizli");

            secilenForm = null;

        }

    });

});

</script>

</body>
</html>
