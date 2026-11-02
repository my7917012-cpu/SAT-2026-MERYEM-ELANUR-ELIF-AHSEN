
<?php

session_start();

// Restoran sahibi oturum bilgilerini temizle
unset($_SESSION["restoran_sahibi_id"]);
unset($_SESSION["restoran_id"]);
unset($_SESSION["restoran_kullanici"]);

// Oturum çerezini de temizle
if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Oturumu tamamen kapat
session_destroy();

// Restoran giriş sayfasına yönlendir
header("Location: restoran_giris.php");
exit;

?>
