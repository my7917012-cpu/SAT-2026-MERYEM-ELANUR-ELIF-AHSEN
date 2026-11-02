
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

    } elseif ($mevcut_sifre === $yeni_sifre) {

        $hata = "Yeni şifreniz mevcut şifrenizden farklı olmalıdır.";

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
    min-height: 100vh;
    font-family: Arial, sans-serif;
    background: #f4eee8;
    color: #333;
}

/* ÜST ALAN */

header {
    background: linear-gradient(120deg, #590014, #8b001e, #650017);
    color: white;
    padding: 25px 5%;
    border-bottom: 4px solid #c49a58;
    box-shadow: 0 5px 20px rgba(70,0,15,0.18);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

header h1 {
    margin: 0;
    font-size: 25px;
}

header a {
    background: white;
    color: #650017;
    padding: 11px 16px;
    border-radius: 7px;
    text-decoration: none;
    font-weight: bold;
    transition: 0.3s;
}

header a:hover {
    background: #c49a58;
    color: white;
}

/* ANA KUTU */

.container {
    width: 100%;
    max-width: 520px;
    margin: 45px auto;
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
    text-align: center;
    color: #800020;
    margin-top: 0;
    font-size: 25px;
}

.aciklama {
    color: #777;
    text-align: center;
    line-height: 1.6;
    font-size: 14px;
    margin-bottom: 25px;
}

/* FORM */

.form-grup {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    color: #650017;
    font-weight: bold;
}

.sifre-alani {
    position: relative;
}

input {
    width: 100%;
    padding: 13px 65px 13px 12px;
    border: 1px solid #d9c9c0;
    border-radius: 7px;
    font-size: 15px;
    outline: none;
    background: #fffdfb;
    transition: 0.3s;
}

input:focus {
    border-color: #800020;
    box-shadow: 0 0 0 3px rgba(128,0,32,0.08);
}

.goster {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: #800020;
    font-size: 12px;
    font-weight: bold;
    cursor: pointer;
    padding: 6px;
}

/* BİLGİ */

.bilgi {
    background: #f8f1e9;
    border-left: 4px solid #c49a58;
    padding: 13px;
    border-radius: 6px;
    color: #654b37;
    font-size: 13px;
    line-height: 1.6;
    margin-top: 22px;
}

/* BUTONLAR */

button.kaydet,
a.geri {
    display: block;
    width: 100%;
    padding: 13px;
    border: none;
    border-radius: 7px;
    text-align: center;
    font-size: 15px;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
    transition: 0.3s;
    margin-top: 18px;
}

button.kaydet {
    background: #800020;
    color: white;
}

button.kaydet:hover {
    background: #a77c43;
    transform: translateY(-2px);
}

a.geri {
    background: #eee5df;
    color: #650017;
}

a.geri:hover {
    background: #c49a58;
    color: white;
}

/* MESAJLAR */

.hata,
.mesaj {
    padding: 13px;
    border-radius: 6px;
    margin-bottom: 20px;
    text-align: center;
    font-size: 14px;
}

.hata {
    background: #fff0f0;
    color: #9c1825;
    border-left: 4px solid #b71c1c;
}

.mesaj {
    background: #eaf7ee;
    color: #16703a;
    border-left: 4px solid #238447;
}

/* MOBİL */

@media (max-width: 600px) {

    header {
        padding: 22px 18px;
    }

    header h1 {
        font-size: 21px;
    }

    .container {
        margin: 25px auto;
    }

    .kutu {
        padding: 24px 18px;
    }
}

</style>

</head>

<body>

<header>

    <h1>Güvenlik Ayarları</h1>

    <a href="restoran_paneli.php">
        Panele Dön
    </a>

</header>

<main class="container">

    <section class="kutu">

        <h2>Şifre Değiştir</h2>

        <p class="aciklama">
            Hesabınızın güvenliği için mevcut şifrenizi doğrulayarak
            yeni bir şifre belirleyebilirsiniz.
        </p>

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

            <div class="form-grup">

                <label for="mevcut_sifre">Mevcut Şifre</label>

                <div class="sifre-alani">

                    <input
                        type="password"
                        id="mevcut_sifre"
                        name="mevcut_sifre"
                        autocomplete="current-password"
                        placeholder="Mevcut şifrenizi girin"
                        required
                    >

                    <button
                        type="button"
                        class="goster"
                        onclick="sifreGoster('mevcut_sifre', this)"
                    >Göster</button>

                </div>

            </div>

            <div class="form-grup">

                <label for="yeni_sifre">Yeni Şifre</label>

                <div class="sifre-alani">

                    <input
                        type="password"
                        id="yeni_sifre"
                        name="yeni_sifre"
                        minlength="8"
                        autocomplete="new-password"
                        placeholder="En az 8 karakter"
                        required
                    >

                    <button
                        type="button"
                        class="goster"
                        onclick="sifreGoster('yeni_sifre', this)"
                    >Göster</button>

                </div>

            </div>

            <div class="form-grup">

                <label for="yeni_sifre_tekrar">Yeni Şifre Tekrar</label>

                <div class="sifre-alani">

                    <input
                        type="password"
                        id="yeni_sifre_tekrar"
                        name="yeni_sifre_tekrar"
                        minlength="8"
                        autocomplete="new-password"
                        placeholder="Yeni şifrenizi tekrar girin"
                        required
                    >

                    <button
                        type="button"
                        class="goster"
                        onclick="sifreGoster('yeni_sifre_tekrar', this)"
                    >Göster</button>

                </div>

            </div>

            <div class="bilgi">
                <strong>Güvenlik hatırlatması:</strong>
                Yeni şifreniz en az 8 karakter olmalıdır.
                Güvenliğiniz için tahmin edilmesi zor bir şifre seçin.
            </div>

            <button type="submit" class="kaydet">
                Şifreyi Değiştir
            </button>

            <a href="restoran_paneli.php" class="geri">
                Restoran Paneline Dön
            </a>

        </form>

    </section>

</main>

<script>

function sifreGoster(id, buton) {

    const alan = document.getElementById(id);

    if (alan.type === "password") {
        alan.type = "text";
        buton.textContent = "Gizle";
    } else {
        alan.type = "password";
        buton.textContent = "Göster";
    }

}

</script>

</body>
</html>
