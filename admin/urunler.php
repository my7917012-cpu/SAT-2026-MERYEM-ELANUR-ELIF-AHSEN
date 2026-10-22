
<?php
session_start();

require_once "../config/baglanti.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: giris.php");
    exit;
}

$mesaj = "";
$hata = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // KATEGORİ SİLME
    if (isset($_POST["kategori_sil_id"])) {
        $kategori_id = (int) $_POST["kategori_sil_id"];

        try {
            $kontrol = $db->prepare(
                "SELECT COUNT(*) FROM urunler WHERE kategori_id = ?"
            );
            $kontrol->execute([$kategori_id]);

            if ((int) $kontrol->fetchColumn() > 0) {
                $hata = "Bu kategoride ürünler var. Silmeden önce ürünleri başka kategoriye taşımalısın.";
            } else {
                $sil = $db->prepare(
                    "DELETE FROM kategoriler WHERE id = ?"
                );
                $sil->execute([$kategori_id]);

                if ($sil->rowCount() > 0) {
                    $mesaj = "Kategori başarıyla silindi.";
                } else {
                    $hata = "Kategori bulunamadı veya silinemedi.";
                }
            }
        } catch (PDOException $e) {
            $hata = "Kategori silinemedi. Veritabanında bu kategoriye bağlı başka kayıtlar olabilir.";
        }

    // KATEGORİ EKLEME
    } elseif (isset($_POST["kategori_adi"])) {
        $kategori_adi = trim($_POST["kategori_adi"] ?? "");

        if ($kategori_adi === "") {
            $hata = "Kategori adı boş bırakılamaz.";
        } else {
            try {
                $ekle = $db->prepare(
                    "INSERT INTO kategoriler (kategori_adi) VALUES (?)"
                );
                $ekle->execute([$kategori_adi]);

                $mesaj = "Kategori başarıyla eklendi.";
            } catch (PDOException $e) {
                if ($e->getCode() === "23000") {
                    $hata = "Bu kategori zaten mevcut.";
                } else {
                    $hata = "Kategori eklenirken bir hata oluştu.";
                }
            }
        }
    }
}

$sorgu = $db->query("
    SELECT k.id, k.kategori_adi,
           COUNT(u.id) AS urun_sayisi
    FROM kategoriler k
    LEFT JOIN urunler u ON u.kategori_id = k.id
    GROUP BY k.id, k.kategori_adi
    ORDER BY k.kategori_adi
");

$kategoriler = $sorgu->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ürünleri Yönet</title>
<style>
* { box-sizing: border-box; }
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
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
header h1 { margin: 0; font-size: 24px; }
a { text-decoration: none; }
header a {
    color: #b71c1c;
    background: white;
    padding: 10px 14px;
    border-radius: 6px;
}
main { max-width: 1100px; margin: 30px auto; padding: 0 18px; }
.kutu, .kart {
    background: white;
    border-radius: 10px;
    padding: 22px;
    box-shadow: 0 3px 12px #0000000d;
}
.kutu { margin-bottom: 25px; }
input, button {
    padding: 12px;
    border-radius: 6px;
    font-size: 15px;
}
input {
    width: 100%;
    border: 1px solid #ccc;
    margin: 8px 0 12px;
}
button, .buton {
    display: inline-block;
    border: 0;
    background: #b71c1c;
    color: white;
    padding: 11px 16px;
    border-radius: 6px;
    cursor: pointer;
}
.grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}
.kart h2 { color: #b71c1c; margin-top: 0; }
.mesaj { color: #16703a; }
.hata { color: #b71c1c; }
@media (max-width: 600px) {
    .grid { grid-template-columns: 1fr; }
}
.sil-butonu {
    background: #a61b1b;
    color: white;
    border: 0;
    padding: 11px 16px;
    border-radius: 7px;
    cursor: pointer;
    transition: background 0.2s;
}
.sil-butonu:hover { background: #7f1414; }
.modal-arka-plan {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-kutu {
    background: white;
    width: 100%;
    max-width: 420px;
    border-radius: 14px;
    padding: 28px;
    text-align: center;
    box-shadow: 0 15px 45px #0003;
}
.modal-kutu h2 { margin-top: 0; color: #b71c1c; }
.modal-kutu p { color: #555; line-height: 1.6; }
.modal-dugmeler {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 24px;
    flex-wrap: wrap;
}
.vazgec-butonu { background: #e5e5e5; color: #333; }
.onayla-butonu { background: #b71c1c; color: white; }
</style>
</head>
<body>
<header>
    <h1>Ürün Yönetimi</h1>
    <a href="panel.php">Admin Paneline Dön</a>
</header>

<main>
    <section class="kutu">
        <h2>Yeni Kategori Ekle</h2>

        <?php if ($mesaj): ?>
            <p class="mesaj"><?= htmlspecialchars($mesaj) ?></p>
        <?php endif; ?>

        <?php if ($hata): ?>
            <p class="hata"><?= htmlspecialchars($hata) ?></p>
        <?php endif; ?>

        <form method="POST">
            <label for="kategori_adi">Kategori adı</label>
            <input id="kategori_adi" name="kategori_adi"
                   maxlength="100" required
                   placeholder="Örn. Tatlılar">
            <button type="submit">Kategori Ekle</button>
        </form>
    </section>

    <h2>Kategoriler</h2>

    <div class="grid">
        <?php foreach ($kategoriler as $kategori): ?>
            <article class="kart">
                <h2><?= htmlspecialchars($kategori["kategori_adi"]) ?></h2>
                <p>
                    Ürün sayısı:
                    <?= (int)$kategori["urun_sayisi"] ?>
                </p>

                <a class="buton"
                   href="kategori_urunleri.php?id=<?= (int)$kategori["id"] ?>">
                    Kategoriyi Aç
                </a>

                <form method="POST" class="silme-form" style="margin-top: 10px;">
                    <input type="hidden"
                           name="kategori_sil_id"
                           value="<?= (int)$kategori["id"] ?>">

                    <button type="button"
                            class="sil-butonu"
                            onclick='silmePenceresiAc(this.form, <?= json_encode($kategori["kategori_adi"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>
                        Kategori Sil
                    </button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</main>

<div class="modal-arka-plan" id="silmeModal">
    <div class="modal-kutu">
        <h2>Kategoriyi Sil</h2>
        <p id="silmeMesaji">Bu kategoriyi silmek istediğine emin misin?</p>

        <div class="modal-dugmeler">
            <button type="button"
                    class="vazgec-butonu"
                    onclick="silmePenceresiKapat()">
                Vazgeç
            </button>

            <button type="button"
                    class="onayla-butonu"
                    onclick="silmeIsleminiOnayla()">
                Evet, Kategoriyi Sil
            </button>
        </div>
    </div>
</div>

<script>
let silinecekForm = null;

function silmePenceresiAc(form, kategoriAdi) {
    silinecekForm = form;
    document.getElementById("silmeMesaji").textContent =
        '"' + kategoriAdi + '" kategorisini silmek istiyor musun?';

    document.getElementById("silmeModal").style.display = "flex";
}

function silmePenceresiKapat() {
    document.getElementById("silmeModal").style.display = "none";
    silinecekForm = null;
}

function silmeIsleminiOnayla() {
    if (silinecekForm) {
        silinecekForm.submit();
    }
}
</script>
</body>
</html>
