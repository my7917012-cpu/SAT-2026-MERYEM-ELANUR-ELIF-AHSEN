
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

                $db->beginTransaction();

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
            min-height: 100vh;
            padding: 30px 15px;
            font-family: Arial, sans-serif;
            background:
                radial-gradient(circle at 15% 20%, rgba(196,154,88,0.12), transparent 30%),
                radial-gradient(circle at 85% 80%, rgba(128,0,32,0.12), transparent 30%),
                #f4eee8;
        }

        .kayit-kutusu {
            width: 100%;
            max-width: 450px;
            margin: 20px auto;
            padding: 30px;
            background: #fff;
            border-radius: 12px;
            border-top: 5px solid #c49a58;
            box-shadow: 0 8px 25px rgba(70,0,20,0.13);
        }

        .logo {
            width: 55px;
            height: 55px;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: #650017;
            color: #c49a58;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
        }

        h1 {
            margin: 0 0 8px;
            color: #650017;
            text-align: center;
            font-size: 24px;
        }

        .alt-baslik {
            text-align: center;
            color: #777;
            font-size: 13px;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #4a2630;
            font-size: 14px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 16px;
            border: 1px solid #ddd2ca;
            border-radius: 7px;
            background: #fffdfa;
            font-size: 14px;
            outline: none;
            transition: 0.2s;
        }

        input:focus {
            border-color: #8b001e;
            box-shadow: 0 0 0 3px rgba(139,0,30,0.08);
        }

        button {
            width: 100%;
            padding: 13px;
            margin-top: 5px;
            border: none;
            border-radius: 7px;
            background: linear-gradient(120deg, #590014, #8b001e, #650017);
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        button:hover {
            filter: brightness(1.12);
            transform: translateY(-1px);
        }

        .mesaj {
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 7px;
            font-size: 13px;
            line-height: 1.6;
        }

        .hata {
            color: #a00020;
            background: #fff0f0;
            border: 1px solid #f2caca;
        }

        .basari {
            color: #176b32;
            background: #e4f7e9;
            border: 1px solid #c4e8ce;
        }

        .giris-linki {
            display: block;
            margin-top: 16px;
            padding: 11px;
            text-align: center;
            color: #650017;
            background: #fffdfa;
            border: 1px solid #c49a58;
            border-radius: 7px;
            text-decoration: none;
            font-size: 14px;
            transition: 0.2s;
        }

        .giris-linki:hover {
            background: #f8f0e5;
        }

        .alt-bilgi {
            margin-top: 22px;
            text-align: center;
            color: #999;
            font-size: 11px;
        }

        @media (max-width: 480px) {
            .kayit-kutusu {
                padding: 25px 20px;
            }
        }
    </style>
</head>

<body>

    <div class="kayit-kutusu">

        <div class="logo">♨</div>

        <h1>Restoran Kaydı</h1>

        <div class="alt-baslik">
            QR Menü sistemine katılın ve restoranınızı yönetin.
        </div>

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
                placeholder="Adınızı ve soyadınızı girin"
                value="<?= htmlspecialchars($ad_soyad, ENT_QUOTES, "UTF-8") ?>"
                autocomplete="name"
                required
            >

            <label for="restoran_adi">Restoran Adı *</label>
            <input
                type="text"
                id="restoran_adi"
                name="restoran_adi"
                placeholder="Restoranınızın adını girin"
                value="<?= htmlspecialchars($restoran_adi, ENT_QUOTES, "UTF-8") ?>"
                required
            >

            <label for="eposta">E-posta *</label>
            <input
                type="email"
                id="eposta"
                name="eposta"
                placeholder="ornek@mail.com"
                value="<?= htmlspecialchars($eposta, ENT_QUOTES, "UTF-8") ?>"
                autocomplete="email"
                required
            >

            <label for="kullanici_adi">Kullanıcı Adı *</label>
            <input
                type="text"
                id="kullanici_adi"
                name="kullanici_adi"
                placeholder="Kullanıcı adınızı belirleyin"
                value="<?= htmlspecialchars($kullanici_adi, ENT_QUOTES, "UTF-8") ?>"
                autocomplete="username"
                required
            >

            <label for="sifre">Şifre (En az 8 karakter) *</label>
            <input
                type="password"
                id="sifre"
                name="sifre"
                placeholder="Şifrenizi belirleyin"
                minlength="8"
                autocomplete="new-password"
                required
            >

            <label for="telefon">Telefon</label>
            <input
                type="tel"
                id="telefon"
                name="telefon"
                placeholder="05XX XXX XX XX"
                value="<?= htmlspecialchars($telefon, ENT_QUOTES, "UTF-8") ?>"
                autocomplete="tel"
            >

            <button type="submit">Kayıt Ol</button>

        </form>

        <a class="giris-linki" href="restoran_giris.php">
            Zaten hesabım var – Giriş Yap
        </a>

        <div class="alt-bilgi">
            QR Menü Yönetim Sistemi
        </div>

    </div>

</body>
</html>
