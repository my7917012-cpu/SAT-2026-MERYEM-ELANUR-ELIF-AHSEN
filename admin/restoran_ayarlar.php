
<?php
session_start();

require_once __DIR__ . "/../config/baglanti.php";

// GİRİŞ KONTROLÜ
if (
    !isset($_SESSION["restoran_sahibi_id"]) ||
    !isset($_SESSION["restoran_id"])
) {
    header("Location: restoran_giris.php");
    exit;
}

$mesaj = "";
$hata = "";

// RESTORAN BİLGİLERİNİ GETİR
$sorgu = $db->prepare("
    SELECT id, restoran_adi, adres, telefon
    FROM restoranlar
    WHERE id = ?
");

$sorgu->execute([$_SESSION["restoran_id"]]);

$restoran = $sorgu->fetch(PDO::FETCH_ASSOC);

if (!$restoran) {
    die("Restoran bilgileri bulunamadı.");
}

// FORM GÖNDERİLDİĞİNDE
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $restoran_adi = trim($_POST["restoran_adi"] ?? "");
    $adres = trim($_POST["adres"] ?? "");
    $telefon = trim($_POST["telefon"] ?? "");

    if ($restoran_adi === "") {

        $hata = "Restoran adı boş bırakılamaz.";

    } else {

        try {

            $db->beginTransaction();

            // RESTORAN BİLGİLERİNİ GÜNCELLE
            $guncelle = $db->prepare("
                UPDATE restoranlar
                SET restoran_adi = ?, adres = ?, telefon = ?
                WHERE id = ?
            ");

            $guncelle->execute([
                $restoran_adi,
                $adres,
                $telefon,
                $_SESSION["restoran_id"]
            ]);

            // RESTORAN SAHİBİ TABLOSUNDAKİ ADI GÜNCELLE
            $sahipGuncelle = $db->prepare("
                UPDATE restoran_sahipleri
                SET restoran_adi = ?
                WHERE restoran_id = ?
            ");

            $sahipGuncelle->execute([
                $restoran_adi,
                $_SESSION["restoran_id"]
            ]);

            $db->commit();

            $mesaj = "Restoran bilgileriniz başarıyla güncellendi.";

            // GÜNCEL BİLGİLERİ TEKRAR GETİR
            $sorgu->execute([$_SESSION["restoran_id"]]);

            $restoran = $sorgu->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $hata = "Bilgiler güncellenirken bir hata oluştu.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Restoran Ayarları</title>

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
    max-width: 750px;
    margin: 40px auto;
    padding: 0 18px;
}

.kutu {
    background: white;
    padding: 32px;
    border-radius: 13px;
    border-top: 5px solid #800020;
    box-shadow: 0 6px 22px rgba(75,20,30,0.09);
}

.kutu h2 {
    color: #800020;
    margin-top: 0;
    margin-bottom: 10px;
    font-size: 23px;
}

.aciklama {
    color: #777;
    line-height: 1.6;
    margin-bottom: 25px;
}

/* FORM ALANLARI */

.form-grup {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: bold;
    color: #650017;
}

input,
textarea {
    width: 100%;
    padding: 13px;
    border: 1px solid #d9c9c0;
    border-radius: 7px;
    font-size: 15px;
    font-family: Arial, sans-serif;
    background: #fffdfb;
    color: #333;
    outline: none;
    transition: 0.3s;
}

input:focus,
textarea:focus {
    border-color: #800020;
    box-shadow: 0 0 0 3px rgba(128,0,32,0.08);
}

textarea {
    resize: vertical;
    min-height: 110px;
}

/* BUTONLAR */

.butonlar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 25px;
}

button,
.geri {
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

button {
    background: #800020;
    color: white;
}

button:hover {
    background: #a77c43;
    transform: translateY(-2px);
}

.geri {
    background: #eee5df;
    color: #650017;
}

.geri:hover {
    background: #c49a58;
    color: white;
}

/* MESAJLAR */

.mesaj {
    background: #eaf7ee;
    color: #16703a;
    border-left: 4px solid #238447;
    padding: 14px;
    border-radius: 6px;
    margin-bottom: 22px;
}

.hata {
    background: #fff0f0;
    color: #9c1825;
    border-left: 4px solid #b71c1c;
    padding: 14px;
    border-radius: 6px;
    margin-bottom: 22px;
}

/* MOBİL */

@media (max-width: 600px) {

    header {
        padding: 23px 18px;
    }

    header h1 {
        font-size: 22px;
    }

    .container {
        margin: 25px auto;
    }

    .kutu {
        padding: 22px 18px;
    }

    .butonlar {
        flex-direction: column;
    }

    .butonlar button,
    .butonlar a {
        width: 100%;
        text-align: center;
    }
}

</style>

</head>

<body>

<header>

    <h1>Restoran Ayarları</h1>

    <a href="restoran_paneli.php">
        Restoran Paneline Dön
    </a>

</header>

<main class="container">

    <section class="kutu">

        <h2>Restoran Bilgilerini Düzenle</h2>

        <p class="aciklama">
            Restoranınızın temel bilgilerini bu sayfadan güncelleyebilirsiniz.
            Yaptığınız değişiklikler kaydedildiğinde panelde de güncellenecektir.
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

            <div class="form-grup">

                <label for="restoran_adi">
                    Restoran Adı
                </label>

                <input
                    type="text"
                    id="restoran_adi"
                    name="restoran_adi"
                    maxlength="150"
                    value="<?= htmlspecialchars($restoran["restoran_adi"] ?? "", ENT_QUOTES, "UTF-8") ?>"
                    placeholder="Restoranınızın adını girin"
                    required
                >

            </div>

            <div class="form-grup">

                <label for="adres">
                    Restoran Adresi
                </label>

                <textarea
                    id="adres"
                    name="adres"
                    placeholder="Restoranınızın adresini girin"
                ><?= htmlspecialchars($restoran["adres"] ?? "", ENT_QUOTES, "UTF-8") ?></textarea>

            </div>

            <div class="form-grup">

                <label for="telefon">
                    Telefon Numarası
                </label>

                <input
                    type="text"
                    id="telefon"
                    name="telefon"
                    maxlength="30"
                    value="<?= htmlspecialchars($restoran["telefon"] ?? "", ENT_QUOTES, "UTF-8") ?>"
                    placeholder="Örn. 0555 555 55 55"
                >

            </div>

            <div class="butonlar">

                <button type="submit">
                    Bilgileri Kaydet
                </button>

                <a href="restoran_paneli.php" class="geri">
                    Panele Dön
                </a>

            </div>

        </form>

    </section>

</main>

</body>
</html>
