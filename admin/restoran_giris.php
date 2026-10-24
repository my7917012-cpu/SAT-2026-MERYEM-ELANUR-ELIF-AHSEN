
<?php
session_start();

require_once __DIR__ . "/../config/baglanti.php";

$hata = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $kullanici_adi = trim($_POST["kullanici_adi"] ?? "");
    $sifre = $_POST["sifre"] ?? "";

    if ($kullanici_adi === "" || $sifre === "") {

        $hata = "Kullanıcı adı ve şifre boş bırakılamaz.";

    } else {

        $sorgu = $db->prepare(
            "SELECT id, restoran_id, kullanici_adi, sifre, eposta_dogrulandi, onay_durumu
             FROM restoran_sahipleri
             WHERE kullanici_adi = ?
             LIMIT 1"
        );

        $sorgu->execute([$kullanici_adi]);
        $sahip = $sorgu->fetch(PDO::FETCH_ASSOC);

        if (!$sahip || !password_verify($sifre, $sahip["sifre"])) {

            $hata = "Kullanıcı adı veya şifre hatalı.";

        } elseif ($sahip["onay_durumu"] !== "onaylandi") {

            $hata = "Hesabınız henüz yönetici tarafından onaylanmadı.";

        } elseif ((int)$sahip["eposta_dogrulandi"] !== 1) {

            $hata = "E-posta doğrulamanız henüz tamamlanmadı.";

        } else {

            session_regenerate_id(true);

            // Restoran sahibinin oturum bilgileri
            $_SESSION["restoran_sahibi_id"] = (int)$sahip["id"];
            $_SESSION["restoran_id"] = (int)$sahip["restoran_id"];
            $_SESSION["restoran_kullanici"] = $sahip["kullanici_adi"];

            header("Location: restoran_paneli.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restoran Sahibi Girişi</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .giris-kutusu {
            width: 380px;
            max-width: 100%;
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        h1 {
            text-align: center;
            color: #b71c1c;
            margin-bottom: 25px;
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
            margin-bottom: 18px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        button, .kayit-linki {
            display: block;
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            text-align: center;
            font-size: 16px;
            text-decoration: none;
            cursor: pointer;
        }

        button {
            background: #b71c1c;
            color: white;
            font-weight: bold;
        }

        button:hover {
            background: #8f1515;
        }

        .kayit-linki {
            margin-top: 12px;
            color: #b71c1c;
            border: 1px solid #b71c1c;
        }

        .hata {
            background: #ffe5e5;
            color: #b71c1c;
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 6px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="giris-kutusu">

        <h1>Restoran Sahibi Girişi</h1>

        <?php if ($hata !== ""): ?>
            <div class="hata">
                <?= htmlspecialchars($hata, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label for="kullanici_adi">Kullanıcı Adı</label>
            <input
                type="text"
                id="kullanici_adi"
                name="kullanici_adi"
                autocomplete="username"
                required
            >

            <label for="sifre">Şifre</label>
            <input
                type="password"
                id="sifre"
                name="sifre"
                autocomplete="current-password"
                required
            >

            <button type="submit">Giriş Yap</button>
        </form>

        <a class="kayit-linki" href="kayit.php">Restoran Kaydı Oluştur</a>

    </div>
</body>
</html>
