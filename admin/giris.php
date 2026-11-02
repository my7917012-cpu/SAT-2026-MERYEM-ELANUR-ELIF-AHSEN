
<?php
session_start();

require_once "../config/baglanti.php";

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

            header("Location: panel.php");
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

    <title>Admin Girişi</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background:
                radial-gradient(circle at 15% 20%, rgba(196,154,88,0.12), transparent 30%),
                radial-gradient(circle at 85% 80%, rgba(128,0,32,0.12), transparent 30%),
                #f4eee8;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-box {
            width: 100%;
            max-width: 390px;
            background: #fff;
            padding: 35px 32px;
            border-radius: 12px;
            border-top: 5px solid #c49a58;
            box-shadow: 0 8px 25px rgba(70, 0, 20, 0.13);
        }

        .logo {
            width: 58px;
            height: 58px;
            margin: 0 auto 16px;
            border-radius: 50%;
            background: #650017;
            color: #c49a58;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 27px;
        }

        h1 {
            text-align: center;
            color: #650017;
            font-size: 25px;
            margin: 0 0 8px;
        }

        .alt-baslik {
            text-align: center;
            color: #777;
            font-size: 13px;
            margin-bottom: 28px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #4a2630;
            font-weight: bold;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 13px;
            margin-bottom: 20px;
            border: 1px solid #ddd2ca;
            border-radius: 7px;
            font-size: 14px;
            outline: none;
            background: #fffdfa;
            transition: 0.2s;
        }

        input:focus {
            border-color: #8b001e;
            box-shadow: 0 0 0 3px rgba(139,0,30,0.08);
        }

        button {
            width: 100%;
            padding: 13px;
            background: linear-gradient(120deg, #590014, #8b001e, #650017);
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        button:hover {
            filter: brightness(1.12);
            transform: translateY(-1px);
        }

        .hata {
            background: #fff0f0;
            color: #a00020;
            border: 1px solid #f2caca;
            padding: 11px;
            margin-bottom: 20px;
            border-radius: 7px;
            text-align: center;
            font-size: 13px;
        }

        .alt-bilgi {
            text-align: center;
            color: #999;
            font-size: 11px;
            margin-top: 24px;
        }

        @media (max-width: 480px) {
            .login-box {
                padding: 28px 22px;
            }
        }
    </style>
</head>

<body>

    <div class="login-box">

        <div class="logo">♨</div>

        <h1>Admin Girişi</h1>

        <div class="alt-baslik">
            Yönetim paneline giriş yapın
        </div>

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
                value="<?= htmlspecialchars($_POST["kullanici_adi"] ?? "", ENT_QUOTES, "UTF-8") ?>"
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

        <div class="alt-bilgi">
            QR Menü Yönetim Sistemi
        </div>

    </div>

</body>
</html>
