
<?php
session_start();

require_once "../config/baglanti.php";

// Giriş yapılmamışsa giriş sayfasına gönder
if (!isset($_SESSION["admin_id"])) {
    header("Location: giris.php");
    exit;
}

$mesaj = "";
$hata = "";
$sifre_mesaj = "";
$sifre_hata = "";

// Restoran bilgilerini getir
$sorgu = $db->query("SELECT * FROM ayarlar LIMIT 1");
$ayarlar = $sorgu->fetch(PDO::FETCH_ASSOC);

// Form gönderildiyse
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Restoran bilgilerini kaydetme formu
    if (isset($_POST["restoran_kaydet"])) {

        $restoran_adi = trim($_POST["restoran_adi"] ?? "");
        $adres = trim($_POST["adres"] ?? "");
        $telefon = trim($_POST["telefon"] ?? "");

        if ($restoran_adi === "") {
            $hata = "Restoran adı boş bırakılamaz.";
        } else {

            if ($ayarlar) {
                $guncelle = $db->prepare("
                    UPDATE ayarlar
                    SET restoran_adi = ?, adres = ?, telefon = ?
                    WHERE id = ?
                ");

                $guncelle->execute([
                    $restoran_adi,
                    $adres,
                    $telefon,
                    $ayarlar["id"]
                ]);

            } else {
                $ekle = $db->prepare("
                    INSERT INTO ayarlar (restoran_adi, adres, telefon)
                    VALUES (?, ?, ?)
                ");

                $ekle->execute([
                    $restoran_adi,
                    $adres,
                    $telefon
                ]);
            }

            $mesaj = "Restoran bilgileri başarıyla kaydedildi.";

            $sorgu = $db->query("SELECT * FROM ayarlar LIMIT 1");
            $ayarlar = $sorgu->fetch(PDO::FETCH_ASSOC);
        }
    }

    // Şifre değiştirme formu
    if (isset($_POST["sifre_degistir"])) {

        $mevcut_sifre = $_POST["mevcut_sifre"] ?? "";
        $yeni_sifre = $_POST["yeni_sifre"] ?? "";
        $yeni_sifre_tekrar = $_POST["yeni_sifre_tekrar"] ?? "";

        $sorgu = $db->prepare("
            SELECT sifre
            FROM admin
            WHERE id = ?
        ");
        $sorgu->execute([$_SESSION["admin_id"]]);
        $admin = $sorgu->fetch(PDO::FETCH_ASSOC);

        if (!$admin || !password_verify($mevcut_sifre, $admin["sifre"])) {
            $sifre_hata = "Mevcut şifreniz yanlış.";
        } elseif (strlen($yeni_sifre) < 6) {
            $sifre_hata = "Yeni şifre en az 6 karakter olmalıdır.";
        } elseif ($yeni_sifre !== $yeni_sifre_tekrar) {
            $sifre_hata = "Yeni şifreler birbiriyle eşleşmiyor.";
        } else {
            $yeni_sifre_hash = password_hash($yeni_sifre, PASSWORD_DEFAULT);

            $guncelle = $db->prepare("
                UPDATE admin
                SET sifre = ?
                WHERE id = ?
            ");

            $guncelle->execute([
                $yeni_sifre_hash,
                $_SESSION["admin_id"]
            ]);

            $sifre_mesaj = "Şifreniz başarıyla değiştirildi.";
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
            padding: 25px 15px;
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .container {
            max-width: 650px;
            margin: 20px auto;
            padding: 25px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin-top: 0;
            color: #b71c1c;
        }

        label {
            display: block;
            margin-top: 18px;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input,
        textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font: inherit;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .buton {
            display: inline-block;
            margin-top: 20px;
            padding: 11px 18px;
            background: #b71c1c;
            color: white;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            font-size: 15px;
        }

        .buton:hover {
            background: #8f1515;
        }

        .mesaj {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
            background: #e8f5e9;
            color: #256029;
        }

        .hata {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
            background: #ffebee;
            color: #b71c1c;
        }

        .geri {
            margin-left: 8px;
            background: #555;
        }

        .geri:hover {
            background: #333;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Restoran Ayarları</h1>

    <p>Restoranınızın bilgilerini bu sayfadan güncelleyebilirsiniz.</p>

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

        <label for="restoran_adi">Restoran Adı *</label>

        <input
            type="text"
            id="restoran_adi"
            name="restoran_adi"
            maxlength="150"
            value="<?= htmlspecialchars($ayarlar["restoran_adi"] ?? "", ENT_QUOTES, "UTF-8") ?>"
            required
        >

        <label for="adres">Adres</label>

        <textarea
            id="adres"
            name="adres"
        ><?= htmlspecialchars($ayarlar["adres"] ?? "", ENT_QUOTES, "UTF-8") ?></textarea>

        <label for="telefon">Telefon</label>

        <input
            type="tel"
            id="telefon"
            name="telefon"
            maxlength="20"
            value="<?= htmlspecialchars($ayarlar["telefon"] ?? "", ENT_QUOTES, "UTF-8") ?>"
        >

       <input type="hidden" name="restoran_kaydet" value="1">
       
        <button type="submit" class="buton">
            Bilgileri Kaydet
        </button>

        <a href="panel.php" class="buton geri">
            Panele Dön
        </a>

    </form>

    
<hr style="margin-top: 30px; border: 0; border-top: 1px solid #ddd;">

<h2 style="color: #b71c1c;">Şifre Değiştir</h2>

<p>Hesap güvenliğiniz için mevcut şifrenizi girmeniz gerekir.</p>

<?php if ($sifre_mesaj !== ""): ?>
    <div class="mesaj">
        <?= htmlspecialchars($sifre_mesaj, ENT_QUOTES, "UTF-8") ?>
    </div>
<?php endif; ?>

<?php if ($sifre_hata !== ""): ?>
    <div class="hata">
        <?= htmlspecialchars($sifre_hata, ENT_QUOTES, "UTF-8") ?>
    </div>
<?php endif; ?>

<form method="POST">

    <input type="hidden" name="sifre_degistir" value="1">

    <label for="mevcut_sifre">Mevcut Şifre</label>
    <input
        type="password"
        id="mevcut_sifre"
        name="mevcut_sifre"
        autocomplete="current-password"
        required
    >

    <label for="yeni_sifre">Yeni Şifre</label>
    <input
        type="password"
        id="yeni_sifre"
        name="yeni_sifre"
        minlength="6"
        autocomplete="new-password"
        required
    >

    <label for="yeni_sifre_tekrar">Yeni Şifre Tekrarı</label>
    <input
        type="password"
        id="yeni_sifre_tekrar"
        name="yeni_sifre_tekrar"
        minlength="6"
        autocomplete="new-password"
        required
    >

    <button type="submit" class="buton">
        Şifreyi Değiştir
    </button>

</form>

</div>

</body>
</html>
