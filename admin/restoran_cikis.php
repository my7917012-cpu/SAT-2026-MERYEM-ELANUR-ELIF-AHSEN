
<?php

session_start();

// Restoran sahibi oturum bilgilerini temizle
unset($_SESSION["restoran_sahibi_id"]);
unset($_SESSION["restoran_id"]);
unset($_SESSION["restoran_kullanici"]);

// Restoran giriş sayfasına yönlendir
header("Location: restoran_giris.php");
exit;

?>
