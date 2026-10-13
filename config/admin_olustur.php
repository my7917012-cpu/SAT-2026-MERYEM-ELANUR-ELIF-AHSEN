
<?php

require_once "baglanti.php";

$kullanici_adi = "admin";
$sifre = "200601";

$hashli_sifre = password_hash($sifre, PASSWORD_DEFAULT);

// Admin var mı kontrol et
$kontrol = $db->prepare(
    "SELECT id FROM admin WHERE kullanici_adi = ?"
);

$kontrol->execute([$kullanici_adi]);

$admin = $kontrol->fetch(PDO::FETCH_ASSOC);

if ($admin) {

    // Mevcut adminin şifresini güncelle
    $guncelle = $db->prepare(
        "UPDATE admin SET sifre = ? WHERE kullanici_adi = ?"
    );

    $guncelle->execute([
        $hashli_sifre,
        $kullanici_adi
    ]);

    echo "Admin şifresi başarıyla 200601 olarak güncellendi.";

} else {

    // Admin yoksa oluştur
    $ekle = $db->prepare(
        "INSERT INTO admin (kullanici_adi, sifre) VALUES (?, ?)"
    );

    $ekle->execute([
        $kullanici_adi,
        $hashli_sifre
    ]);

    echo "Admin hesabı başarıyla oluşturuldu.";
}

?>


