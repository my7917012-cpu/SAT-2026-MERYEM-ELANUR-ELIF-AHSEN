<?php
session_start();

require_once "../config/baglanti.php";

// Giriş kontrolü
if (
    !isset($_SESSION["restoran_sahibi_id"]) ||
    !isset($_SESSION["restoran_id"])
) {
    header("Location: restoran_giris.php");
    exit;
}

$mesaj = "";
$hata = "";

// Restoran bilgilerini getir
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

// Form gönderildiğinde
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $restoran_adi = trim($_POST["restoran_adi"] ?? "");
    $adres = trim($_POST["adres"] ?? "");
    $telefon = trim($_POST["telefon"] ?? "");

    if ($restoran_adi === "") {
        $hata = "Restoran adı boş bırakılamaz.";
    } else {
        try {
            $db->beginTransaction();

            // Restoran bilgilerini güncelle
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

            // Panelde görünen restoran adını da güncelle
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

            // Güncel bilgileri tekrar getir
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
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 30px 15px;
        }

        .kutu {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        h1 {
            margin-top: 0;
            color: #333;
        }

        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 15px;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        button,
        .geri {
            display: inline-block;
            margin-top: 20px;
            padding: 11px 16px;
            border: none;
            border-radius: 5px;
            background: #b22222;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 15px;
        }

        .geri {
            background: #555;
            margin-left: 5px;
        }

        .mesaj {
            color: green;
            margin-bottom: 15px;
        }

        .hata {
            color: #b22222;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>

<div class="kutu">

    <h1>Restoran Ayarları</h1>

    <?php if ($mesaj !== ""): ?>
        <p class="mesaj">
            <?= htmlspecialchars($mesaj, ENT_QUOTES, "UTF-8") ?>
        </p>
    <?php endif; ?>

    <?php if ($hata !== ""): ?>
        <p class="hata">
            <?= htmlspecialchars($hata, ENT_QUOTES, "UTF-8") ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <label for="restoran_adi">Restoran Adı</label>
        <input
            type="text"
            id="restoran_adi"
            name="restoran_adi"
            value="<?= htmlspecialchars($restoran["restoran_adi"] ?? "", ENT_QUOTES, "UTF-8") ?>"
            required
        >

        <label for="adres">Adres</label>
        <textarea
            id="adres"
            name="adres"
        ><?= htmlspecialchars($restoran["adres"] ?? "", ENT_QUOTES, "UTF-8") ?></textarea>

        <label for="telefon">Telefon</label>
        <input
            type="text"
            id="telefon"
            name="telefon"
            value="<?= htmlspecialchars($restoran["telefon"] ?? "", ENT_QUOTES, "UTF-8") ?>"
        >

        <button type="submit">Bilgileri Kaydet</button>

        <a href="restoran_paneli.php" class="geri">Panele Dön</a>

    </form>

</div>

</body>
</html>