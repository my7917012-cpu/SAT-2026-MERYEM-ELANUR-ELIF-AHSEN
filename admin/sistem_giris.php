
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
            "SELECT * FROM admin WHERE kullanici_adi = ?"
        );

        $sorgu->execute([$kullanici_adi]);

        $admin = $sorgu->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($sifre, $admin["sifre"])) {

            session_regenerate_id(true);

            $_SESSION["admin_id"] = $admin["id"];
            $_SESSION["admin_kullanici"] = $admin["kullanici_adi"];

            header("Location: sistem_paneli.php");
            exit;

        } else {

            $hata = "Kullanıcı adı veya şifre hatalı.";

        }
    }
}

?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sistem Yöneticisi Girişi</title>

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
        }

        .login-box {
            width: 380px;
            max-width: 92%;
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        h1 {
            text-align: center;
            color: #b71c1c;
            margin-bottom: 30px;
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

        button {
            width: 100%;
            padding: 12px;
            background: #b71c1c;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #8f1515;
        }

        .hata {
            background: #ffe5e5;
            color: #b71c1c;
            padding: 10px;
            margin-bottom: 18px;
            border-radius: 6px;
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="login-box">

        <h1>Sistem Yöneticisi Girişi</h1>

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
                placeholder="Kullanıcı adınızı girin"
                autocomplete="username"
                required
            >

            <label for="sifre">Şifre</label>

            <input
                type="password"
                id="sifre"
                name="sifre"
                placeholder="Şifrenizi girin"
                autocomplete="current-password"
                required
            >

            <button type="submit">Giriş Yap</button>

        </form>

    </div>

</body>
</html>
