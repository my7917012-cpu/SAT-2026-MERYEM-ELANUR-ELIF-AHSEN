
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

/*
|--------------------------------------------------------------------------
| GÖRSEL HAVUZU
|--------------------------------------------------------------------------
*/

$havuzKlasoru = realpath(__DIR__ . "/../uploads/gorseller");
$havuzUrl = "../uploads/gorseller/";

$gorseller = [];

if ($havuzKlasoru !== false && is_dir($havuzKlasoru)) {
    $uzantilar = ["jpg", "jpeg", "png", "webp", "gif"];

    foreach (scandir($havuzKlasoru) as $dosya) {
        if ($dosya === "." || $dosya === "..") {
            continue;
        }

        $tamYol = $havuzKlasoru . DIRECTORY_SEPARATOR . $dosya;

        if (
            is_file($tamYol) &&
            in_array(strtolower(pathinfo($dosya, PATHINFO_EXTENSION)), $uzantilar, true)
        ) {
            $gorseller[] = $dosya;
        }
    }

    natcasesort($gorseller);
    $gorseller = array_values($gorseller);
}

$mesaj = "";
$hata = "";

/*
|--------------------------------------------------------------------------
| YENİ ÜRÜN EKLEME
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {
        $hata = "Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar dene.";
    } else {
        $urun_adi = trim($_POST["urun_adi"] ?? "");
        $aciklama = trim($_POST["aciklama"] ?? "");
        $fiyat_girdi = trim($_POST["fiyat"] ?? "");
        $fiyat = str_replace(",", ".", $fiyat_girdi);
        $fotografDosyasi = trim($_POST["fotograf"] ?? "");

        if ($urun_adi === "") {
            $hata = "Ürün adı zorunludur.";
        } elseif (
            $fiyat === "" ||
            !is_numeric($fiyat) ||
            !is_finite((float)$fiyat) ||
            (float)$fiyat < 0
        ) {
            $hata = "Geçerli ve negatif olmayan bir fiyat gir.";
        } elseif (
            $fotografDosyasi !== "" &&
            (
                $havuzKlasoru === false ||
                !in_array($fotografDosyasi, $gorseller, true)
            )
        ) {
            $hata = "Lütfen görsel havuzunda bulunan bir fotoğraf seç.";
        } else {
            $fotografYolu = $fotografDosyasi !== ""
                ? "uploads/gorseller/" . $fotografDosyasi
                : null;

            $ekle = $db->prepare("
                INSERT INTO urunler
                (kategori_id, urun_adi, aciklama, fiyat, fotograf, restoran_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $ekle->execute([
                $kategori_id,
                $urun_adi,
                $aciklama,
                $fiyat,
                $fotografYolu,
                $_SESSION["restoran_id"]
            ]);

            $mesaj = "Ürün başarıyla eklendi.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| KATEGORİDEKİ ÜRÜNLERİ GETİR
|--------------------------------------------------------------------------
*/

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
    position: relative;
    overflow: hidden;
    padding: 30px 6%;
    color: white;
    background:
        radial-gradient(circle at 90% 20%, rgba(196,154,88,.16), transparent 25%),
        linear-gradient(120deg, #590014, #8b001e, #650017);
    border-bottom: 4px solid var(--altin);
}

header::after {
    content: "";
    position: absolute;
    width: 230px;
    height: 230px;
    right: 5%;
    top: -115px;
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 50%;
    box-shadow:
        0 0 0 20px rgba(255,255,255,.025),
        0 0 0 40px rgba(255,255,255,.02);
    pointer-events: none;
}

.header-icerik {
    position: relative;
    z-index: 1;
    max-width: 1150px;
    margin: auto;
}

.ust-etiket {
    display: inline-block;
    color: var(--altin);
    font-size: 12px;
    font-weight: bold;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 10px;
}

header h1 {
    margin: 0 0 14px;
    font-size: 30px;
    font-weight: 800;
}

header a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: white;
    text-decoration: none;
    font-weight: bold;
    font-size: 14px;
    transition: .2s;
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
    box-shadow: 0 5px 20px rgba(65, 25, 25, .07);
    transition: .2s;
}

.kutu:hover {
    box-shadow: 0 8px 25px rgba(65, 25, 25, .10);
}

.kutu h2 {
    color: var(--bordo);
    margin: 0 0 22px;
    font-size: 21px;
}

.kutu h3 {
    color: var(--bordo);
    margin-top: 28px;
    margin-bottom: 8px;
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
    transition: .2s;
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

button:disabled {
    opacity: .65;
    cursor: not-allowed;
}

form > button[type="submit"] {
    width: 100%;
    padding: 14px;
    margin-top: 10px;
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
    transform: translateY(-1px);
}

/* MESAJLAR */

.mesaj {
    padding: 13px 15px;
    border-radius: 7px;
    color: #176b39;
    background: #e9f7ee;
    border-left: 4px solid #27864b;
    font-weight: bold;
}

.hata {
    padding: 13px 15px;
    border-radius: 7px;
    color: #a00020;
    background: #fff0f0;
    border-left: 4px solid var(--bordo);
    font-weight: bold;
}

.gorsel-bilgi {
    margin: 5px 0 16px;
    color: var(--soluk);
    font-size: 14px;
    line-height: 1.6;
}

/* GÖRSEL HAVUZU */

.gorsel-havuzu {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
    gap: 15px;
    margin: 16px 0 22px;
}

.gorsel-secim {
    position: relative;
    display: block;
    padding: 9px;
    border: 2px solid #e5ddd7;
    border-radius: 10px;
    background: #fffdfa;
    cursor: pointer;
    transition: .2s;
}

.gorsel-secim:hover {
    border-color: var(--altin);
    transform: translateY(-2px);
}

.gorsel-secim:has(input:checked) {
    border-color: var(--bordo);
    background: #fff5f4;
    box-shadow: 0 0 0 2px rgba(128,0,32,.08);
}

.gorsel-secim input {
    width: auto;
    margin: 0 5px 9px 0;
    accent-color: var(--bordo);
    cursor: pointer;
}

.gorsel-secim img {
    display: block;
    width: 100%;
    height: 110px;
    object-fit: cover;
    border-radius: 7px;
}

.gorsel-adi {
    display: block;
    margin-top: 8px;
    color: #655752;
    font-size: 12px;
    overflow-wrap: anywhere;
}

/* FOTOĞRAFSIZ SEÇENEĞİ */

.fotografsiz-secim {
    display: inline-flex;
    align-items: center;
    width: auto;
    padding: 11px 15px;
    margin: 5px 0 15px;
    border: 1px solid #e0d5cd;
    border-radius: 8px;
    background: #faf7f3;
    cursor: pointer;
}

.fotografsiz-secim input {
    width: auto;
    margin: 0 8px 0 0;
    accent-color: var(--bordo);
}

/* GÖRSEL ÖNİZLEME */

.onizleme-alani {
    display: flex;
    align-items: center;
    gap: 17px;
    flex-wrap: wrap;
    margin: 15px 0 20px;
    padding: 15px;
    background: #faf6f0;
    border: 1px solid #eee2d3;
    border-radius: 10px;
}

.onizleme-alani img {
    width: 135px;
    height: 105px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid var(--altin);
}

.onizleme-alani strong {
    color: var(--bordo);
}

.onizleme-alani p {
    margin-bottom: 0;
    color: var(--soluk);
    overflow-wrap: anywhere;
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

tbody tr {
    transition: background .2s;
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
    transition: .2s;
}

.sil-formu button:hover {
    background: #a0002a;
}

/* ÖZEL SİLME MODALI */

.sil-modal {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    background: rgba(30, 5, 10, .72);
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
    animation: modalAc .2s ease-out;
}

@keyframes modalAc {
    from {
        opacity: 0;
        transform: scale(.92);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
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
    width: auto;
    margin: 0;
    padding: 13px;
    border-radius: 7px;
    font-weight: bold;
    border: none;
    transition: .2s;
}

.sil-modal-butonlar .sil-vazgec {
    background: #eee9e5;
    color: #493d38;
}

.sil-modal-butonlar .sil-vazgec:hover {
    background: #ded6d0;
}

.sil-modal-butonlar .sil-onayla {
    background: var(--bordo);
    color: white;
}

.sil-modal-butonlar .sil-onayla:hover {
    background: #a0002a;
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

    .gorsel-havuzu {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .gorsel-secim img {
        height: 95px;
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
            placeholder="Örn. Türk Kahvesi"
        >

        <label for="fiyat">Fiyat (TL)</label>
        <input
            id="fiyat"
            name="fiyat"
            type="number"
            min="0"
            step="0.01"
            required
            placeholder="Örn. 85"
        >

        <label for="aciklama">Açıklama</label>
        <textarea
            id="aciklama"
            name="aciklama"
            placeholder="Ürün hakkında kısa bilgi"
        ></textarea>

        <h3>Görsel Havuzundan Seç</h3>

        <p class="gorsel-bilgi">
            Üründe kullanmak istediğin fotoğrafı seç.
            Fotoğraf seçmek zorunlu değildir.
        </p>

        <label class="fotografsiz-secim">
            <input type="radio" name="fotograf" value="" checked>
            Fotoğrafsız
        </label>

        <?php if (empty($gorseller)): ?>
            <p class="hata">
                Görsel havuzunda fotoğraf bulunamadı.
                Klasör yolunu kontrol et: uploads/gorseller/
            </p>
        <?php else: ?>
            <div class="gorsel-havuzu">
                <?php foreach ($gorseller as $gorsel): ?>
                    <label class="gorsel-secim">
                        <input
                            type="radio"
                            name="fotograf"
                            value="<?= htmlspecialchars($gorsel, ENT_QUOTES, "UTF-8") ?>"
                            data-src="<?= htmlspecialchars($havuzUrl . rawurlencode($gorsel), ENT_QUOTES, "UTF-8") ?>"
                        >

                        <img
                            src="<?= htmlspecialchars($havuzUrl . rawurlencode($gorsel), ENT_QUOTES, "UTF-8") ?>"
                            alt="<?= htmlspecialchars($gorsel, ENT_QUOTES, "UTF-8") ?>"
                            loading="lazy"
                        >

                        <span class="gorsel-adi">
                            <?= htmlspecialchars($gorsel, ENT_QUOTES, "UTF-8") ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="onizleme-alani" id="onizlemeAlani" hidden>
            <img
                id="seciliGorsel"
                src=""
                alt="Seçilen fotoğrafın önizlemesi"
            >

            <div>
                <strong>Seçilen fotoğraf</strong>
                <p id="seciliGorselAdi"></p>
            </div>
        </div>

        <button type="submit">Ürünü Kaydet</button>
    </form>
</section>

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
                            $urunFotografi = $urun["fotograf"] ?? "";
                            $fotografDosyasi = basename(
                                str_replace("\\", "/", $urunFotografi)
                            );

                            $fotografGecerli = (
                                $urunFotografi !== "" &&
                                in_array($fotografDosyasi, $gorseller, true) &&
                                $havuzKlasoru !== false
                            );
                            ?>

                            <?php if ($fotografGecerli): ?>
                                <img
                                    class="urun-fotografi"
                                    src="<?= htmlspecialchars("../uploads/gorseller/" . rawurlencode($fotografDosyasi), ENT_QUOTES, "UTF-8") ?>"
                                    alt="<?= htmlspecialchars($urun["urun_adi"], ENT_QUOTES, "UTF-8") ?>"
                                    loading="lazy"
                                >
                            <?php else: ?>
                                <span>Fotoğraf yok</span>
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

    // GÖRSEL ÖNİZLEME

    const gorselSecenekleri = document.querySelectorAll(
        'input[name="fotograf"]'
    );

    const onizlemeAlani = document.getElementById("onizlemeAlani");
    const seciliGorsel = document.getElementById("seciliGorsel");
    const seciliGorselAdi = document.getElementById("seciliGorselAdi");

    function gorselOnizle() {
        const secilen = document.querySelector(
            'input[name="fotograf"]:checked'
        );

        if (!secilen || !secilen.value || !secilen.dataset.src) {
            onizlemeAlani.hidden = true;
            seciliGorsel.removeAttribute("src");
            seciliGorselAdi.textContent = "";
            return;
        }

        seciliGorsel.src = secilen.dataset.src;
        seciliGorselAdi.textContent = secilen.value;
        onizlemeAlani.hidden = false;
    }

    gorselSecenekleri.forEach(function (secenek) {
        secenek.addEventListener("change", gorselOnizle);
    });

    // ÖZEL ÜRÜN SİLME PENCERESİ

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
