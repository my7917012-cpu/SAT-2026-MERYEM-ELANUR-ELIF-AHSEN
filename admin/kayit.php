
<?php
session_start();

require_once __DIR__ . "/../config/baglanti.php";

$hata = "";
$basari = "";

$ad_soyad = "";
$restoran_adi = "";
$eposta = "";
$kullanici_adi = "";
$telefon = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $ad_soyad = trim($_POST["ad_soyad"] ?? "");
    $restoran_adi = trim($_POST["restoran_adi"] ?? "");
    $eposta = trim($_POST["eposta"] ?? "");
    $kullanici_adi = trim($_POST["kullanici_adi"] ?? "");
    $sifre = $_POST["sifre"] ?? "";
    $telefon = trim($_POST["telefon"] ?? "");

    if (
        $ad_soyad === "" ||
        $restoran_adi === "" ||
        $eposta === "" ||
        $kullanici_adi === "" ||
        $sifre === ""
    ) {
        $hata = "Lütfen zorunlu alanların tamamını doldurun.";

    } elseif (!filter_var($eposta, FILTER_VALIDATE_EMAIL)) {
        $hata = "Lütfen geçerli bir e-posta adresi girin.";

    } elseif (strlen($sifre) < 8) {
        $hata = "Şifreniz en az 8 karakter olmalıdır.";

    } else {

        try {

            // E-posta ve kullanıcı adı daha önce kullanılmış mı?
            $kontrol = $db->prepare(
                "SELECT id
                 FROM restoran_sahipleri
                 WHERE eposta = ? OR kullanici_adi = ?
                 LIMIT 1"
            );

            $kontrol->execute([
                $eposta,
                $kullanici_adi
            ]);

            if ($kontrol->fetch(PDO::FETCH_ASSOC)) {

                $hata = "Bu e-posta adresi veya kullanıcı adı zaten kayıtlı.";

            } else {

                // Restoran ve sahibi kayıtlarını birlikte kaydet.
                $db->beginTransaction();

                // Önce restoranı oluştur.
                $restoranEkle = $db->prepare(
                    "INSERT INTO restoranlar
                     (restoran_adi, telefon)
                     VALUES (?, ?)"
                );

                $restoranEkle->execute([
                    $restoran_adi,
                    $telefon !== "" ? $telefon : null
                ]);

                $restoran_id = (int) $db->lastInsertId();

                // Ardından restoran sahibini oluştur.
                $sahipEkle = $db->prepare(
                    "INSERT INTO restoran_sahipleri
                    (
                        ad_soyad,
                        restoran_adi,
                        eposta,
                        kullanici_adi,
                        sifre,
                        telefon,
                        restoran_id,
                        eposta_dogrulandi,
                        onay_durumu
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'beklemede')"
                );

                $sahipEkle->execute([
                    $ad_soyad,
                    $restoran_adi,
                    $eposta,
                    $kullanici_adi,
                    password_hash($sifre, PASSWORD_DEFAULT),
                    $telefon !== "" ? $telefon : null,
                    $restoran_id
                ]);

                $db->commit();

                $basari = "Kaydınız alındı. Hesabınız, yönetici onayı ve e-posta doğrulaması tamamlanana kadar kullanıma açılmayacaktır.";

                // Form alanlarını temizle.
                $ad_soyad = "";
                $restoran_adi = "";
                $eposta = "";
                $kullanici_adi = "";
                $telefon = "";
            }

        } catch (PDOException $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $hata = "Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Restoran Sahibi Kaydı</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px 15px;
            font-family: Arial, sans-serif;
            background: #f4f4f4;
        }

        .kayit-kutusu {
            width: 100%;
            max-width: 450px;
            margin: 20px auto;
            padding: 30px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
        }

        h1 {
            margin-top: 0;
            margin-bottom: 25px;
            color: #b71c1c;
            text-align: center;
            font-size: 25px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 17px;
            border: 1px solid #cccccc;
            border-radius: 6px;
            font-size: 15px;
        }

        input:focus {
            outline: 2px solid #e8a0a0;
            border-color: #b71c1c;
        }

        button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 6px;
            background: #b71c1c;
            color: #ffffff;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #8f1515;
        }

        .mesaj {
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 6px;
            line-height: 1.5;
        }

        .hata {
            color: #b71c1c;
            background: #ffe5e5;
        }

        .basari {
            color: #176b32;
            background: #e4f7e9;
        }
    </style>
</head>

<body>

    <div class="kayit-kutusu">

        <h1>Restoran Sahibi Kaydı</h1>

        <?php if ($hata !== ""): ?>
            <div class="mesaj hata">
                <?= htmlspecialchars($hata, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <?php if ($basari !== ""): ?>
            <div class="mesaj basari">
                <?= htmlspecialchars($basari, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <label for="ad_soyad">Ad Soyad *</label>
            <input
                type="text"
                id="ad_soyad"
                name="ad_soyad"
                value="<?= htmlspecialchars($ad_soyad, ENT_QUOTES, "UTF-8") ?>"
                required
            >

            <label for="restoran_adi">Restoran Adı *</label>
            <input
                type="text"
                id="restoran_adi"
                name="restoran_adi"
                value="<?= htmlspecialchars($restoran_adi, ENT_QUOTES, "UTF-8") ?>"
                required
            >

            <label for="eposta">E-posta *</label>
            <input
                type="email"
                id="eposta"
                name="eposta"
                value="<?= htmlspecialchars($eposta, ENT_QUOTES, "UTF-8") ?>"
                required
            >

            <label for="kullanici_adi">Kullanıcı Adı *</label>
            <input
                type="text"
                id="kullanici_adi"
                name="kullanici_adi"
                value="<?= htmlspecialchars($kullanici_adi, ENT_QUOTES, "UTF-8") ?>"
                required
            >

            <label for="sifre">Şifre (en az 8 karakter) *</label>
            <input
                type="password"
                id="sifre"
                name="sifre"
                minlength="8"
                autocomplete="new-password"
                required
            >

            <label for="telefon">Telefon</label>
            <input
                type="tel"
                id="telefon"
                name="telefon"
                value="<?= htmlspecialchars($telefon, ENT_QUOTES, "UTF-8") ?>"
            >

            <button type="submit">Kayıt Ol</button>

        </form>

    </div>

</body>
</html>
