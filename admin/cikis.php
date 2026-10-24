
<?php

session_start();

// Oturumdaki tüm bilgileri temizle
$_SESSION = [];

// Oturumu sonlandır
session_destroy();

// Giriş ekranına yönlendir
header("Location: giris.php");
exit;

?>
