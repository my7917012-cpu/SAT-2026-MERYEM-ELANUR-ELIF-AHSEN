
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

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    exit("Geçersiz ürün. <a href='urunler.php'>Geri dön</a>");
}

$sorgu = $db->prepare("
    SELECT id, kategori_id, urun_adi, aciklama, fiyat, fotograf
    FROM urunler
    WHERE id = ? AND restoran_id = ?
");

$sorgu->execute([
    $id,
    $_SESSION["restoran_id"]
]);

$urun = $sorgu->fetch(PDO::FETCH_ASSOC);

if (!$urun) {
    exit("Ürün bulunamadı. <a href='urunler.php'>Geri dön</a>");
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

$mevcutFotograf = $urun["fotograf"] ?? "";
$mevcutDosya = basename(str_replace("\\", "/", $mevcutFotograf));

$mevcutFotografGecerli = (
    $mevcutFotograf !== "" &&
    $havuzKlasoru !== false &&
    in_array($mevcutDosya, $gorseller, true)
);

$hata = "";
$basarili = "";

$urun_adi = $urun["urun_adi"];
$aciklama = $urun["aciklama"] ?? "";
$fiyat_girdi = (string)$urun["fiyat"];
$seciliFotograf = $mevcutFotografGecerli ? $mevcutDosya : "";

/*
|--------------------------------------------------------------------------
| ÜRÜN GÜNCELLEME
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
        $fiyat_girdi = str_replace(",", ".", trim($_POST["fiyat"] ?? ""));
        $fotografDosyasi = trim($_POST["fotograf"] ?? "");

        if ($urun_adi === "") {
            $hata = "Ürün adı boş bırakılamaz.";
        } elseif (
            $fiyat_girdi === "" ||
            !is_numeric($fiyat_girdi) ||
            !is_finite((float)$fiyat_girdi) ||
            (float)$fiyat_girdi < 0
        ) {
            $hata = "Geçerli, negatif olmayan bir fiyat gir.";
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

            $guncelle = $db->prepare("
                UPDATE urunler
                SET urun_adi = ?,
                    aciklama = ?,
                    fiyat = ?,
                    fotograf = ?
                WHERE id = ? AND restoran_id = ?
            ");

            $guncelle->execute([
                $urun_adi,
                $aciklama,
                $fiyat_girdi,
                $fotografYolu,
                $id,
                $_SESSION["restoran_id"]
            ]);

            header("Location: kategori_urunleri.php?id=" . (int)$urun["kategori_id"]);
            exit;
        }

        $seciliFotograf = $fotografDosyasi;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Ürün Güncelle</title>

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
    --metin: #352b2b;
    --soluk: #786d68;
}

body {
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
    background: var(--krem);
    color: var(--metin);
}

/* BAŞLIK */

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

.header-icerik {
    max-width: 750px;
    margin: auto;
    position: relative;
    z-index: 1;
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
    margin: 0 0 12px;
    font-size: 29px;
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

/* ANA KART */

main {
    max-width: 750px;
    margin: 35px auto;
    padding: 0 18px;
}

.kutu {
    background: white;
    padding: 28px;
    border-radius: 13px;
    border-top: 4px solid var(--bordo);
    box-shadow: 0 5px 20px rgba(65, 25, 25, .07);
}

.kutu h2 {
    color: var(--bordo);
    margin: 0 0 23px;
    font-size: 21px;
}

/* FORM */

label {
    display: block;
    margin: 17px 0 7px;
    font-size: 14px;
    font-weight: bold;
    color: #493b38;
}

input:not([type="radio"]),
textarea {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid #ded5cf;
    border-radius: 7px;
    background: #fffdfa;
    font: inherit;
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
    min-height: 100px;
    resize: vertical;
}

/* FOTOĞRAF ALANI */

.fotograf-baslik {
    margin-top: 30px;
    color: var(--bordo);
    font-size: 18px;
}

.gorsel-bilgi {
    color: var(--soluk);
    font-size: 14px;
    line-height: 1.6;
}

.mevcut-fotograf {
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
    margin: 15px 0 22px;
    padding: 14px;
    border: 1px solid #eee2d3;
    border-radius: 10px;
    background: #faf6f0;
}

.mevcut-fotograf img {
    width: 130px;
    height: 105px;
    object-fit: cover;
    border: 2px solid var(--altin);
    border-radius: 8px;
}

.mevcut-fotograf strong {
    color: var(--bordo);
}

.mevcut-fotograf p {
    margin-bottom: 0;
    color: var(--soluk);
    font-size: 13px;
}

.gorsel-havuzu {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(135px, 1fr));
    gap: 13px;
    margin: 16px 0;
}

.gorsel-secim {
    display: block;
    padding: 8px;
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
    margin: 0 5px 8px 0;
    accent-color: var(--bordo);
    cursor: pointer;
}

.gorsel-secim img {
    display: block;
    width: 100%;
    height: 100px;
    object-fit: cover;
    border-radius: 7px;
}

.gorsel-adi {
    display: block;
    margin-top: 7px;
    font-size: 12px;
    color: #655752;
    overflow-wrap: anywhere;
}

.fotografsiz-secim {
    display: inline-flex;
    align-items: center;
    width: auto;
    padding: 10px 14px;
    margin: 5px 0 10px;
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

.onizleme-alani {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin: 18px 0;
    padding: 14px;
    border-radius: 10px;
    background: #faf6f0;
    border: 1px solid #eee2d3;
}

.onizleme-alani img {
    width: 125px;
    height: 100px;
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

/* MESAJ */

.hata {
    padding: 13px 15px;
    border-radius: 7px;
    color: #a00020;
    background: #fff0f0;
    border-left: 4px solid var(--bordo);
    font-weight: bold;
}

/* BUTONLAR */

.kaydet-btn {
    width: 100%;
    padding: 14px;
    margin-top: 25px;
    border: none;
    border-radius: 7px;
    color: white;
    background: linear-gradient(120deg, var(--bordo), var(--bordo-koyu));
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
    transition: .2s;
}

.kaydet-btn:hover {
    background: var(--bordo-acik);
    box-shadow: 0 5px 14px rgba(128,0,32,.2);
    transform: translateY(-1px);
}

.alt-link {
    display: inline-block;
    margin-top: 22px;
    color: var(--bordo);
    text-decoration: none;
    font-weight: bold;
    font-size: 14px;
}

.alt-link:hover {
    color: var(--bordo-acik);
    text-decoration: underline;
}

/* MOBİL */

@media (max-width: 600px) {
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
        padding: 20px 16px;
    }

    .gorsel-havuzu {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .gorsel-secim img {
        height: 90px;
    }

    .mevcut-fotograf img,
    .onizleme-alani img {
        width: 105px;
        height: 85px;
    }
}
</style>
</head>

<body>

<header>
    <div class="header-icerik">
        <span class="ust-etiket">ÜRÜN YÖNETİMİ</span>
        <h1>Ürün Güncelle</h1>

        <a href="kategori_urunleri.php?id=<?= (int)$urun["kategori_id"] ?>">
            ← Ürün listesine dön
        </a>
    </div>
</header>

<main>

<section class="kutu">

    <h2>Ürün Bilgilerini Düzenle</h2>

    <?php if ($hata !== ""): ?>
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
            value="<?= htmlspecialchars($fiyat_girdi, ENT_QUOTES, "UTF-8") ?>"
        >

        <label for="aciklama">Açıklama</label>

        <textarea
            id="aciklama"
            name="aciklama"
        ><?= htmlspecialchars($aciklama, ENT_QUOTES, "UTF-8") ?></textarea>

        <h3 class="fotograf-baslik">Ürün Fotoğrafı</h3>

        <p class="gorsel-bilgi">
            Ürünün mevcut fotoğrafını görüntüleyebilir veya
            görsel havuzundan yeni bir fotoğraf seçebilirsin.
        </p>

        <?php if ($mevcutFotografGecerli): ?>
            <div class="mevcut-fotograf">
                <img
                    src="<?= htmlspecialchars($havuzUrl . rawurlencode($mevcutDosya), ENT_QUOTES, "UTF-8") ?>"
                    alt="Mevcut ürün fotoğrafı"
                >

                <div>
                    <strong>Mevcut ürün fotoğrafı</strong>
                    <p><?= htmlspecialchars($mevcutDosya, ENT_QUOTES, "UTF-8") ?></p>
                </div>
            </div>
        <?php else: ?>
            <p class="gorsel-bilgi">Bu ürün için kayıtlı bir fotoğraf bulunmuyor.</p>
        <?php endif; ?>

        <label class="fotografsiz-secim">
            <input
                type="radio"
                name="fotograf"
                value=""
                <?= $seciliFotograf === "" ? "checked" : "" ?>
            >
            Fotoğrafsız
        </label>

        <?php if (empty($gorseller)): ?>
            <p class="hata">
                Görsel havuzunda fotoğraf bulunamadı.
                uploads/gorseller/ klasörünü kontrol et.
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
                            <?= $seciliFotograf === $gorsel ? "checked" : "" ?>
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

        <div
            class="onizleme-alani"
            id="onizlemeAlani"
            hidden
        >
            <img
                id="seciliGorsel"
                src=""
                alt="Seçilen fotoğrafın önizlemesi"
            >

            <div>
                <strong>Yeni seçilen fotoğraf</strong>
                <p id="seciliGorselAdi"></p>
            </div>
        </div>

        <button type="submit" class="kaydet-btn">
            Değişiklikleri Kaydet
        </button>

    </form>

    <a
        class="alt-link"
        href="kategori_urunleri.php?id=<?= (int)$urun["kategori_id"] ?>"
    >
        ← Ürün listesine dön
    </a>

</section>

</main>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const secenekler = document.querySelectorAll(
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

    secenekler.forEach(function (secenek) {
        secenek.addEventListener("change", gorselOnizle);
    });

    // Sayfa açıldığında mevcut seçimi önizle
    gorselOnizle();

});
</script>

</body>
</html>
