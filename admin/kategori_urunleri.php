
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

$kategori_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$kategori_id || $kategori_id < 1) {
    exit("Geçersiz kategori. <a href='urunler.php'>Geri dön</a>");
}

$kontrol = $db->prepare(
    "SELECT id, kategori_adi FROM kategoriler WHERE id = ?"
);
$kontrol->execute([$kategori_id]);
$kategori = $kontrol->fetch(PDO::FETCH_ASSOC);

if (!$kategori) {
    exit("Kategori bulunamadı. <a href='urunler.php'>Geri dön</a>");
}

/*
|--------------------------------------------------------------------------
| GÖRSEL HAVUZU
|--------------------------------------------------------------------------
| Proje klasörü: restoran-QR
| Görseller: uploads/gorseller/
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
                    (kategori_id, urun_adi, aciklama, fiyat, fotograf)
                VALUES (?, ?, ?, ?, ?)
            ");

            $ekle->execute([
                $kategori_id,
                $urun_adi,
                $aciklama,
                $fiyat,
                $fotografYolu
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
    WHERE kategori_id = ?
    ORDER BY id DESC
");
$liste->execute([$kategori_id]);
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

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f5f5f5;
    color: #333;
}

header {
    background: #b71c1c;
    color: white;
    padding: 20px 5%;
}

header h1 {
    margin-top: 0;
}

header a {
    color: white;
    text-decoration: none;
}

header a:hover {
    text-decoration: underline;
}

main {
    max-width: 1100px;
    margin: 28px auto;
    padding: 0 16px;
}

.kutu {
    background: white;
    padding: 22px;
    border-radius: 10px;
    margin-bottom: 22px;
    box-shadow: 0 3px 12px rgba(0, 0, 0, .05);
}

input,
textarea,
button {
    width: 100%;
    padding: 12px;
    margin: 8px 0 14px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font: inherit;
}

textarea {
    min-height: 85px;
    resize: vertical;
}

button {
    background: #b71c1c;
    color: white;
    border: 0;
    cursor: pointer;
}

button:hover {
    background: #941616;
}

.mesaj {
    color: #16703a;
    font-weight: bold;
}

.hata {
    color: #b71c1c;
    font-weight: bold;
}

.gorsel-bilgi {
    margin: 5px 0 12px;
    color: #666;
    font-size: 14px;
}

.gorsel-havuzu {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(135px, 1fr));
    gap: 14px;
    margin: 14px 0 20px;
}

.gorsel-secim {
    position: relative;
    border: 2px solid #ddd;
    border-radius: 10px;
    padding: 8px;
    cursor: pointer;
    transition: border-color .2s, background .2s;
}

.gorsel-secim:hover {
    border-color: #b71c1c;
}

.gorsel-secim:has(input:checked) {
    border-color: #b71c1c;
    background: #fff2f2;
}

.gorsel-secim input {
    width: auto;
    margin: 0 5px 8px 0;
    accent-color: #b71c1c;
}

.gorsel-secim img {
    display: block;
    width: 100%;
    height: 105px;
    object-fit: cover;
    border-radius: 6px;
}

.gorsel-adi {
    display: block;
    margin-top: 7px;
    font-size: 12px;
    overflow-wrap: anywhere;
}

.onizleme-alani {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin: 12px 0 20px;
    padding: 12px;
    background: #f8f8f8;
    border-radius: 8px;
}

.onizleme-alani img {
    width: 130px;
    height: 100px;
    object-fit: cover;
    border-radius: 8px;
}

.tablo {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

th,
td {
    text-align: left;
    padding: 12px;
    border-bottom: 1px solid #ddd;
    vertical-align: middle;
}

th {
    background: #f8f8f8;
}

td a {
    display: inline-block;
    color: #b71c1c;
    margin: 4px 8px 8px 0;
    font-weight: bold;
    text-decoration: none;
}

td a:hover {
    text-decoration: underline;
}

.urun-fotografi {
    width: 65px;
    height: 55px;
    object-fit: cover;
    border-radius: 7px;
}

.sil-formu {
    display: inline-block;
    margin: 0;
}

.sil-formu button {
    width: auto;
    min-width: 75px;
    margin: 4px 0;
    padding: 8px 14px;
    background: #b71c1c;
    border-radius: 6px;
}

.sil-modal {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    background: rgba(0, 0, 0, .6);
}

.sil-modal.gizli {
    display: none;
}

.sil-modal-kutu {
    width: 100%;
    max-width: 420px;
    padding: 28px;
    background: white;
    border-radius: 14px;
    box-shadow: 0 15px 45px rgba(0, 0, 0, .25);
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
    width: 58px;
    height: 58px;
    margin: 0 auto 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #ffebee;
    color: #b71c1c;
    font-size: 30px;
    font-weight: bold;
}

.sil-modal-kutu h2 {
    margin: 0 0 12px;
    color: #333;
    font-size: 22px;
}

.sil-modal-kutu p {
    color: #666;
    line-height: 1.6;
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
    padding: 12px;
    border-radius: 7px;
    font-weight: bold;
}

.sil-modal-butonlar .sil-vazgec {
    background: #eee;
    color: #333;
}

.sil-modal-butonlar .sil-onayla {
    background: #b71c1c;
    color: white;
}

@media (max-width: 600px) {
    .kutu {
        padding: 16px;
    }

    th, td {
        padding: 9px;
        font-size: 14px;
    }

    .sil-modal-kutu {
        padding: 22px;
    }

    .sil-modal-butonlar {
        flex-direction: column;
    }
}
</style>
</head>

<body>

<header>
    <h1><?= htmlspecialchars($kategori["kategori_adi"], ENT_QUOTES, "UTF-8") ?></h1>
    <a href="urunler.php">← Kategorilere Dön</a>
</header>

<main>

<section class="kutu">
    <h2>Yeni Ürün Ekle</h2>

    <?php if ($mesaj): ?>
        <p class="mesaj"><?= htmlspecialchars($mesaj, ENT_QUOTES, "UTF-8") ?></p>
    <?php endif; ?>

    <?php if ($hata): ?>
        <p class="hata"><?= htmlspecialchars($hata, ENT_QUOTES, "UTF-8") ?></p>
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
            Üründe kullanmak istediğin fotoğrafı seç. Fotoğraf seçmek zorunlu değildir.
        </p>

        <label class="gorsel-secim" style="display:inline-block; width:auto; padding:10px;">
            <input type="radio" name="fotograf" value="" checked>
            Fotoğrafsız
        </label>

        <?php if (empty($gorseller)): ?>
            <p class="hata">
                Görsel havuzunda fotoğraf bulunamadı. Klasör yolunu kontrol et:
                uploads/gorseller/
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
            <img id="seciliGorsel" src="" alt="Seçilen fotoğrafın önizlemesi">
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
                            $fotografDosyasi = basename(str_replace("\\", "/", $urunFotografi));
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

                        <td><?= htmlspecialchars($urun["urun_adi"], ENT_QUOTES, "UTF-8") ?></td>

                        <td><?= htmlspecialchars($urun["aciklama"] ?? "", ENT_QUOTES, "UTF-8") ?></td>

                        <td><?= number_format((float)$urun["fiyat"], 2, ",", ".") ?> TL</td>

                        <td>
                            <a href="urun_duzenle.php?id=<?= (int)$urun["id"] ?>">
                                Güncelle
                            </a>

                            <form
                                action="urun_islem.php"
                                method="POST"
                                class="sil-formu"
                            >
                                <input type="hidden" name="urun_id" value="<?= (int)$urun["id"] ?>">
                                <input type="hidden" name="islem" value="sil">
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
            <button type="button" class="sil-vazgec" id="silVazgec">
                Vazgeç
            </button>

            <button type="button" class="sil-onayla" id="silOnayla">
                Evet, ürünü sil
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // Seçilen görselin önizlemesi
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

    // Özel ürün silme penceresi
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
        if (event.key === "Escape" && !modal.classList.contains("gizli")) {
            modal.classList.add("gizli");
            secilenForm = null;
        }
    });
});
</script>

</body>
</html>