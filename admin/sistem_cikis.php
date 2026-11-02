
<?php

session_start();

// Oturumdaki tüm bilgileri temizle
$_SESSION = [];

// Oturum çerezini temizle
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

// Oturumu tamamen sonlandır
session_destroy();

// Sistem yöneticisi giriş ekranına dön
header("Location: sistem_giris.php");
exit;

?>

