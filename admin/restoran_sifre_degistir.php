<?php
session_start();

require_once __DIR__ . "/../config/baglanti.php";

// Giriş kontrolü
if (
    !isset($_SESSION["restoran_sahibi_id"]) ||
    !isset($_SESSION["restoran_id"])
) {
    header("Location: restoran_giris.php");
    exit;
}

$hata = "";
$mesaj = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $mevcut_sifre = $_POST["mevcut_sifre"] ?? "";
    $yeni_sifre = $_POST["yeni_sifre"] ?? "";
    $yeni_sifre_tekrar = $_POST["yeni_sifre_tekrar"] ?? "";

    if (
        $mevcut_sifre === "" ||
        $yeni_sifre === "" ||
        $yeni_sifre_tekrar === ""
    ) {
        $hata = "Tüm alanları doldurmalısınız.";

    } elseif (strlen($yeni_sifre) < 8) {
        $hata = "Yeni şifre en az 8 karakter olmalıdır.";

    } elseif ($yeni_sifre !== $yeni_sifre_tekrar) {
        $hata = "Yeni şifreler birbiriyle eşleşmiyor.";

    } else {

        $sorgu = $db->prepare(
            "SELECT sifre FROM restoran_sahipleri
             WHERE id = ? AND restoran_id = ?
             LIMIT 1"
        );

        $sorgu->execute([
            $_SESSION["restoran_sahibi_id"],
            $_SESSION["restoran_id"]
        ]);

        $sahip = $sorgu->fetch(PDO::FETCH_ASSOC);

        if (!$sahip || !password_verify($mevcut_sifre, $sahip["sifre"])) {

            $hata = "Mevcut şifreniz hatalı.";

        } else {

            $yeni_sifre_hash = password_hash(
                $yeni_sifre,
                PASSWORD_DEFAULT
            );

            $guncelle = $db->prepare(
                "UPDATE restoran_sahipleri
                 SET sifre = ?
                 WHERE id = ? AND restoran_id = ?"
            );

            $guncelle->execute([
                $yeni_sifre_hash,
                $_SESSION["restoran_sahibi_id"],
                $_SESSION["restoran_id"]
            ]);

            $mesaj = "Şifreniz başarıyla değiştirildi.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Şifre Değiştir</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f4f4f4;
        }

        .kutu {
            max-width: 450px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #b71c1c;
            text-align: center;
            font-size: 25px;
        }

        label {
            display: block;
            margin-top: 18px;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        button, .geri {
            display: block;
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            border: none;
            border-radius: 6px;
            text-align: center;
            font-size: 15px;
            text-decoration: none;
            cursor: pointer;
        }

        button {
            background: #b71c1c;
            color: white;
            font-weight: bold;
        }

        .geri {
            background: #555;
            color: white;
        }

        .hata, .mesaj {
            padding: 12px;
            border-radius: 6px;
            text-align: center;
        }

        .hata {
            background: #ffe5e5;
            color: #b71c1c;
        }

        .mesaj {
            background: #e5ffe9;
            color: #176b2c;
        }
    </style>
</head>

<body>

<div class="kutu">

    <h1>Şifre Değiştir</h1>

    <?php if ($hata !== ""): ?>
        <div class="hata">
            <?= htmlspecialchars($hata, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <?php if ($mesaj !== ""): ?>
        <div class="mesaj">
            <?= htmlspecialchars($mesaj, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <form method="POST">

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
            minlength="8"
            autocomplete="new-password"
            required
        >

        <label for="yeni_sifre_tekrar">Yeni Şifre Tekrar</label>
        <input
            type="password"
            id="yeni_sifre_tekrar"
            name="yeni_sifre_tekrar"
            minlength="8"
            autocomplete="new-password"
            required
        >

        <button type="submit">Şifreyi Değiştir</button>

        <a href="restoran_paneli.php" class="geri">
            Panele Dön
        </a>

    </form>

</div>

</body>
</html>