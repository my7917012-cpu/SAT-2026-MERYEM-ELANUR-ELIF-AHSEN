
<?php

session_start();

// Oturumdaki bilgileri temizle
$_SESSION = [];

// Oturumu sonlandır
session_destroy();

// Sistem yöneticisi giriş ekranına dön
header("Location: sistem_giris.php");
exit;

?>
