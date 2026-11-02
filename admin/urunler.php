
<?php
session_start();

require_once __DIR__ . "/../config/baglanti.php";

if (!isset($_SESSION["restoran_sahibi_id"], $_SESSION["restoran_id"])) {
    header("Location: restoran_giris.php");
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
                "SELECT COUNT(*)
                 FROM urunler
                 WHERE kategori_id = ? AND restoran_id = ?"
            );

            $kontrol->execute([
                $kategori_id,
                $_SESSION["restoran_id"]
            ]);

            if ((int) $kontrol->fetchColumn() > 0) {

                $hata = "Bu kategoride ürünler var. Silmeden önce ürünleri başka kategoriye taşımalısın.";

            } else {

                $sil = $db->prepare(
                    "DELETE FROM kategoriler
                     WHERE id = ? AND restoran_id = ?"
                );

                $sil->execute([
                    $kategori_id,
                    $_SESSION["restoran_id"]
                ]);

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
                    "INSERT INTO kategoriler (kategori_adi, restoran_id)
                     VALUES (?, ?)"
                );

                $ekle->execute([
                    $kategori_adi,
                    $_SESSION["restoran_id"]
                ]);

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

// KATEGORİLERİ LİSTELE
$sorgu = $db->prepare("
    SELECT k.id, k.kategori_adi,
           COUNT(u.id) AS urun_sayisi
    FROM kategoriler k
    LEFT JOIN urunler u
        ON u.kategori_id = k.id
        AND u.restoran_id = ?
    WHERE k.restoran_id = ?
    GROUP BY k.id, k.kategori_adi
    ORDER BY k.kategori_adi
");

$sorgu->execute([
    $_SESSION["restoran_id"],
    $_SESSION["restoran_id"]
]);

$kategoriler = $sorgu->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Ürün Yönetimi</title>

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

a {
    text-decoration: none;
}

header a {
    color: #650017;
    background: #fff;
    padding: 12px 17px;
    border-radius: 7px;
    font-weight: bold;
    transition: 0.3s;
}

header a:hover {
    background: #c49a58;
    color: white;
}

/* ANA ALAN */

main {
    max-width: 1100px;
    margin: 35px auto;
    padding: 0 18px;
}

.kutu {
    background: white;
    border-radius: 13px;
    padding: 27px;
    margin-bottom: 35px;
    border-top: 4px solid #800020;
    box-shadow: 0 5px 18px rgba(75,20,30,0.08);
}

.kutu h2 {
    color: #800020;
    margin-top: 0;
    margin-bottom: 22px;
}

/* FORM */

label {
    font-weight: bold;
    color: #555;
}

input {
    width: 100%;
    border: 1px solid #d9c9c0;
    padding: 13px;
    margin: 10px 0 15px;
    border-radius: 7px;
    font-size: 15px;
    outline: none;
}

input:focus {
    border-color: #800020;
    box-shadow: 0 0 0 3px rgba(128,0,32,0.08);
}

/* BUTONLAR */

button,
.buton {
    display: inline-block;
    border: 0;
    background: #800020;
    color: white;
    padding: 12px 17px;
    border-radius: 7px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
    transition: 0.3s;
}

button:hover,
.buton:hover {
    background: #a77c43;
    color: white;
}

/* KATEGORİLER */

.baslik {
    color: #650017;
    margin-bottom: 20px;
    font-size: 23px;
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.kart {
    background: white;
    border-radius: 13px;
    padding: 25px;
    border: 1px solid #eadbd2;
    border-top: 4px solid #800020;
    box-shadow: 0 5px 18px rgba(75,20,30,0.08);
    transition: 0.3s;
}

.kart:hover {
    transform: translateY(-5px);
    border-top-color: #c49a58;
    box-shadow: 0 10px 25px rgba(75,20,30,0.14);
}

.kart h2 {
    color: #800020;
    margin-top: 0;
    font-size: 21px;
}

.kart p {
    color: #777;
    margin: 18px 0;
}

.urun-sayisi {
    display: inline-block;
    background: #f6eee8;
    color: #800020;
    padding: 7px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 15px;
}

.kart-islemler {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

/* SİLME BUTONU */

.sil-butonu {
    background: #a61b1b;
}

.sil-butonu:hover {
    background: #7f1414;
}

/* MESAJLAR */

.mesaj {
    color: #16703a;
    background: #eaf7ee;
    border-left: 4px solid #238447;
    padding: 12px;
    border-radius: 5px;
}

.hata {
    color: #9c1825;
    background: #fff0f0;
    border-left: 4px solid #b71c1c;
    padding: 12px;
    border-radius: 5px;
}

/* SİLME MODALI */

.modal-arka-plan {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(35,0,10,0.65);
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
    border-top: 5px solid #800020;
    box-shadow: 0 15px 45px #0003;
}

.modal-kutu h2 {
    margin-top: 0;
    color: #800020;
}

.modal-kutu p {
    color: #555;
    line-height: 1.6;
}

.modal-dugmeler {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 24px;
    flex-wrap: wrap;
}

.vazgec-butonu {
    background: #e5e5e5;
    color: #333;
}

.vazgec-butonu:hover {
    background: #ccc;
    color: #333;
}

.onayla-butonu {
    background: #800020;
}

/* MOBİL */

@media (max-width: 600px) {

    header {
        padding: 23px 18px;
    }

    header h1 {
        font-size: 22px;
    }

    main {
        margin: 25px auto;
    }

    .grid {
        grid-template-columns: 1fr;
    }

    .kart-islemler {
        flex-direction: column;
        align-items: stretch;
    }

    .kart-islemler a,
    .kart-islemler button {
        width: 100%;
        text-align: center;
    }
}

</style>
</head>

<body>

<header>
    <h1>Ürün Yönetimi</h1>
    <a href="restoran_paneli.php">Restoran Paneline Dön</a>
</header>

<main>

    <section class="kutu">

        <h2>Yeni Kategori Ekle</h2>

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

            <label for="kategori_adi">Kategori Adı</label>

            <input
                id="kategori_adi"
                name="kategori_adi"
                maxlength="100"
                required
                placeholder="Örn. Tatlılar"
            >

            <button type="submit">+ Kategori Ekle</button>

        </form>

    </section>

    <h2 class="baslik">Kategoriler</h2>

    <div class="grid">

        <?php foreach ($kategoriler as $kategori): ?>

            <article class="kart">

                <h2>
                    <?= htmlspecialchars($kategori["kategori_adi"], ENT_QUOTES, "UTF-8") ?>
                </h2>

                <span class="urun-sayisi">
                    <?= (int)$kategori["urun_sayisi"] ?> Ürün
                </span>

                <p>
                    Bu kategorideki ürünleri görüntüleyebilir ve yönetebilirsin.
                </p>

                <div class="kart-islemler">

                    <a
                        class="buton"
                        href="kategori_urunleri.php?id=<?= (int)$kategori["id"] ?>"
                    >
                        Kategoriyi Aç
                    </a>

                    <form method="POST" class="silme-form">

                        <input
                            type="hidden"
                            name="kategori_sil_id"
                            value="<?= (int)$kategori["id"] ?>"
                        >

                        <button
                            type="button"
                            class="sil-butonu"
                            onclick='silmePenceresiAc(
                                this.form,
                                <?= json_encode(
                                    $kategori["kategori_adi"],
                                    JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP
                                ) ?>
                            )'
                        >
                            Kategori Sil
                        </button>

                    </form>

                </div>

            </article>

        <?php endforeach; ?>

    </div>

    <?php if (empty($kategoriler)): ?>
        <section class="kutu">
            <p>Henüz kategori eklenmemiş. İlk kategorini yukarıdan oluşturabilirsin.</p>
        </section>
    <?php endif; ?>

</main>

<!-- KATEGORİ SİLME MODALI -->

<div class="modal-arka-plan" id="silmeModal">

    <div class="modal-kutu">

        <h2>Kategoriyi Sil</h2>

        <p id="silmeMesaji">
            Bu kategoriyi silmek istediğine emin misin?
        </p>

        <div class="modal-dugmeler">

            <button
                type="button"
                class="vazgec-butonu"
                onclick="silmePenceresiKapat()"
            >
                Vazgeç
            </button>

            <button
                type="button"
                class="onayla-butonu"
                onclick="silmeIsleminiOnayla()"
            >
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

document.getElementById("silmeModal").addEventListener("click", function(e) {

    if (e.target === this) {
        silmePenceresiKapat();
    }

});

</script>

</body>
</html>
