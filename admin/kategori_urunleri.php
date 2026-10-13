
<?php
session_start();

require_once "../config/baglanti.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: giris.php");
    exit;
}

// CSRF güvenlik anahtarı
if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$kategori_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$kategori_id || $kategori_id < 1) {
    exit("Geçersiz kategori. <a href='urunler.php'>Geri dön</a>");
}

// Kategoriyi bul
$kontrol = $db->prepare(
    "SELECT id, kategori_adi FROM kategoriler WHERE id = ?"
);
$kontrol->execute([$kategori_id]);
$kategori = $kontrol->fetch(PDO::FETCH_ASSOC);

if (!$kategori) {
    exit("Kategori bulunamadı. <a href='urunler.php'>Geri dön</a>");
}

$mesaj = "";
$hata = "";

// Yeni ürün ekleme
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

        if ($urun_adi === "") {
            $hata = "Ürün adı zorunludur.";
        } elseif (
            $fiyat === "" ||
            !is_numeric($fiyat) ||
            !is_finite((float)$fiyat) ||
            (float)$fiyat < 0
        ) {
            $hata = "Geçerli ve negatif olmayan bir fiyat gir.";
        } else {
            $ekle = $db->prepare("
                INSERT INTO urunler
                    (kategori_id, urun_adi, aciklama, fiyat)
                VALUES (?, ?, ?, ?)
            ");

            $ekle->execute([
                $kategori_id,
                $urun_adi,
                $aciklama,
                $fiyat
            ]);

            $mesaj = "Ürün başarıyla eklendi.";
        }
    }
}

// Kategorideki ürünleri getir
$liste = $db->prepare("
    SELECT id, urun_adi, aciklama, fiyat
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

<title><?= htmlspecialchars($kategori["kategori_adi"]) ?> - Ürün Yönetimi</title>

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
    max-width: 950px;
    margin: 28px auto;
    padding: 0 16px;
}

.kutu {
    background: white;
    padding: 22px;
    border-radius: 10px;
    margin-bottom: 22px;
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.05);
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

/* Ürün silme formu */
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
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

.sil-formu button:hover {
    background: #8e1515;
}

/* Özel silme penceresinin arka planı */
.sil-modal {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    background: rgba(0, 0, 0, 0.6);
}

/* Pencere kapalıyken görünmesin */
.sil-modal.gizli {
    display: none;
}

/* Beyaz onay kutusu */
.sil-modal-kutu {
    width: 100%;
    max-width: 420px;
    padding: 28px;
    background: white;
    border-radius: 14px;
    box-shadow: 0 15px 45px rgba(0, 0, 0, 0.25);
    text-align: center;
    animation: modalAc 0.2s ease-out;
}

@keyframes modalAc {
    from {
        opacity: 0;
        transform: scale(0.92);
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
    border: none;
    border-radius: 7px;
    font-weight: bold;
    cursor: pointer;
}

.sil-modal-butonlar .sil-vazgec {
    background: #eee;
    color: #333;
}

.sil-modal-butonlar .sil-vazgec:hover {
    background: #ddd;
}

.sil-modal-butonlar .sil-onayla {
    background: #b71c1c;
    color: white;
}

.sil-modal-butonlar .sil-onayla:hover {
    background: #8e1515;
}

@media (max-width: 600px) {
    .kutu {
        padding: 16px;
    }

    th,
    td {
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
    <h1><?= htmlspecialchars($kategori["kategori_adi"]) ?></h1>
    <a href="urunler.php">← Kategorilere Dön</a>
</header>

<main>

    <!-- YENİ ÜRÜN EKLEME -->
    <section class="kutu">
        <h2>Yeni Ürün Ekle</h2>

        <?php if ($mesaj): ?>
            <p class="mesaj"><?= htmlspecialchars($mesaj) ?></p>
        <?php endif; ?>

        <?php if ($hata): ?>
            <p class="hata"><?= htmlspecialchars($hata) ?></p>
        <?php endif; ?>

        <form method="POST">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"
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

            <button type="submit">Ürünü Kaydet</button>
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
                                <?= htmlspecialchars($urun["urun_adi"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($urun["aciklama"] ?? "") ?>
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
                                        value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"
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

<!-- ÖZEL ÜRÜN SİLME PENCERESİ -->
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
    const modal = document.getElementById("silModal");
    const vazgec = document.getElementById("silVazgec");
    const onayla = document.getElementById("silOnayla");

    let secilenForm = null;

    if (!modal || !vazgec || !onayla) {
        console.error("Silme penceresinin elemanları bulunamadı.");
        return;
    }

    // Sil butonuna basılınca gerçek silme işlemini durdur
    // ve özel tasarımlı pencereyi aç.
    document.querySelectorAll(".sil-formu").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            event.preventDefault();

            secilenForm = form;
            modal.classList.remove("gizli");
            vazgec.focus();
        });
    });

    // Vazgeç butonu: pencereyi kapat, ürünü silme.
    vazgec.addEventListener("click", function () {
        modal.classList.add("gizli");
        secilenForm = null;
    });

    // Onay butonu: yalnızca seçilen ürünü sil.
    onayla.addEventListener("click", function () {
        if (secilenForm) {
            onayla.disabled = true;
            onayla.textContent = "Siliniyor...";
            secilenForm.submit();
        }
    });

    // Pencerenin dışındaki karanlık alana tıklanınca kapat.
    modal.addEventListener("click", function (event) {
        if (event.target === modal) {
            modal.classList.add("gizli");
            secilenForm = null;
        }
    });

    // Escape tuşuyla pencereyi kapat.
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

