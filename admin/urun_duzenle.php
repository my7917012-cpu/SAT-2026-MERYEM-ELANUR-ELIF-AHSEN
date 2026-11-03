
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

// Ürünü getir
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
    exit("Ürün bulunamadı.");
}

$hata = "";

$urun_adi = $urun["urun_adi"];
$aciklama = $urun["aciklama"] ?? "";
$fiyat_girdi = (string)$urun["fiyat"];
$fotograf = $urun["fotograf"] ?? "";

// URL doğrulama
function gecerliFotografUrl($url) {
    if ($url === "") {
        return true;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $parca = parse_url($url);

    return isset($parca["scheme"], $parca["host"])
        && in_array(strtolower($parca["scheme"]), ["http", "https"], true);
}

// ÜRÜN GÜNCELLEME
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {
        $hata = "Güvenlik doğrulaması başarısız. Sayfayı yenile.";
    } else {

        $urun_adi = trim($_POST["urun_adi"] ?? "");
        $aciklama = trim($_POST["aciklama"] ?? "");
        $fiyat_girdi = str_replace(",", ".", trim($_POST["fiyat"] ?? ""));
        $fotograf = trim($_POST["fotograf"] ?? "");

        if ($urun_adi === "") {
            $hata = "Ürün adı boş bırakılamaz.";
        } elseif (
            $fiyat_girdi === "" ||
            !is_numeric($fiyat_girdi) ||
            !is_finite((float)$fiyat_girdi) ||
            (float)$fiyat_girdi < 0
        ) {
            $hata = "Geçerli bir fiyat gir.";
        } elseif (strlen($fotograf) > 2048) {
            $hata = "Fotoğraf bağlantısı çok uzun.";
        } elseif (!gecerliFotografUrl($fotograf)) {
            $hata = "Lütfen geçerli bir http veya https fotoğraf bağlantısı gir.";
        } else {

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
                $fotograf !== "" ? $fotograf : null,
                $id,
                $_SESSION["restoran_id"]
            ]);

            header("Location: kategori_urunleri.php?id=" . (int)$urun["kategori_id"]);
            exit;
        }
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
    font-family: Arial, sans-serif;
    background: var(--krem);
    color: var(--metin);
}

header {
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
    box-shadow: 0 5px 20px rgba(65,25,25,.07);
}

.kutu h2 {
    color: var(--bordo);
    margin: 0 0 23px;
    font-size: 21px;
}

label {
    display: block;
    margin: 17px 0 7px;
    font-size: 14px;
    font-weight: bold;
    color: #493b38;
}

input,
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

.onizleme-alani {
    margin-top: 18px;
    padding: 15px;
    border-radius: 10px;
    background: #faf6f0;
    border: 1px solid #eee2d3;
}

.onizleme-alani img {
    display: block;
    width: 100%;
    max-width: 250px;
    height: 180px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid var(--altin);
    margin-top: 10px;
}

.onizleme-baslik {
    color: var(--bordo);
    font-weight: bold;
}

.onizleme-hata {
    color: #a00020;
    font-size: 13px;
    margin-top: 10px;
}

.hata {
    padding: 13px 15px;
    border-radius: 7px;
    color: #a00020;
    background: #fff0f0;
    border-left: 4px solid var(--bordo);
    font-weight: bold;
}

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

    .onizleme-alani img {
        max-width: 100%;
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
            İnternette bulduğun yemek fotoğrafının doğrudan bağlantısını
            buraya yapıştırabilirsin. Bilgisayarına fotoğraf indirmen gerekmez.
        </p>

        <label for="fotograf">Fotoğraf bağlantısı (URL)</label>

        <input
            type="url"
            id="fotograf"
            name="fotograf"
            maxlength="2048"
            placeholder="https://ornek-site.com/yemek-fotografi.jpg"
            value="<?= htmlspecialchars($fotograf, ENT_QUOTES, "UTF-8") ?>"
        >

        <div class="onizleme-alani" id="onizlemeAlani" hidden>
            <span class="onizleme-baslik">Fotoğraf önizlemesi</span>

            <img
                id="seciliGorsel"
                src=""
                alt="Ürün fotoğrafı önizlemesi"
                hidden
            >

            <p class="onizleme-hata" id="onizlemeHata" hidden>
                Fotoğraf yüklenemedi. Bağlantıyı kontrol et.
            </p>
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

    const fotografInput = document.getElementById("fotograf");
    const onizlemeAlani = document.getElementById("onizlemeAlani");
    const seciliGorsel = document.getElementById("seciliGorsel");
    const onizlemeHata = document.getElementById("onizlemeHata");

    let sonZamanlayici;

    function onizlemeGoster() {

        const url = fotografInput.value.trim();

        clearTimeout(sonZamanlayici);

        if (!url) {
            onizlemeAlani.hidden = true;
            seciliGorsel.hidden = true;
            seciliGorsel.removeAttribute("src");
            onizlemeHata.hidden = true;
            return;
        }

        onizlemeAlani.hidden = false;
        seciliGorsel.hidden = true;
        onizlemeHata.hidden = true;

        sonZamanlayici = setTimeout(function () {

            try {
                const adres = new URL(url);

                if (!["http:", "https:"].includes(adres.protocol)) {
                    throw new Error("Geçersiz adres");
                }

                seciliGorsel.onload = function () {
                    seciliGorsel.hidden = false;
                    onizlemeHata.hidden = true;
                };

                seciliGorsel.onerror = function () {
                    seciliGorsel.hidden = true;
                    onizlemeHata.hidden = false;
                };

                seciliGorsel.src = adres.href;

            } catch (error) {
                seciliGorsel.hidden = true;
                onizlemeHata.hidden = false;
            }

        }, 400);
    }

    fotografInput.addEventListener("input", onizlemeGoster);

    onizlemeGoster();

});
</script>

</body>
</html>
