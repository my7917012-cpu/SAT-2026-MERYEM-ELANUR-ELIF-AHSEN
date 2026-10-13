<?php
session_start();

require_once __DIR__ . '/../config/baglanti.php';

// Yalnızca giriş yapmış yönetici işlem yapabilir.
if (!isset($_SESSION['admin_id'])) {
    header('Location: giris.php');
    exit;
}

// İşlemler yalnızca POST isteğiyle yapılır.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: urunler.php');
    exit;
}

// CSRF güvenlik anahtarını kontrol et.
$token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($token) ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {
    http_response_code(403);
    exit('Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
}

// İşlem ve ürün numarasını al.
$islem = $_POST['islem'] ?? '';
$urunId = filter_input(INPUT_POST, 'urun_id', FILTER_VALIDATE_INT);

if (!$urunId || $urunId < 1) {
    http_response_code(400);
    exit('Geçersiz ürün numarası.');
}

try {
    // Ürünün varlığını ve bağlı olduğu kategoriyi kontrol et.
    $sorgu = $db->prepare(
        'SELECT kategori_id FROM urunler WHERE id = ?'
    );
    $sorgu->execute([$urunId]);

    $kategoriId = $sorgu->fetchColumn();

    if ($kategoriId === false) {
        http_response_code(404);
        exit('Ürün bulunamadı.');
    }

    if ($islem === 'sil') {
        // Ürünü sil.
        $sil = $db->prepare(
            'DELETE FROM urunler WHERE id = ?'
        );
        $sil->execute([$urunId]);

        $_SESSION['urun_mesaj'] = 'Ürün başarıyla silindi.';

    } elseif ($islem === 'guncelle') {
        // Güncelleme bilgilerini al ve doğrula.
        $urunAdi = trim($_POST['urun_adi'] ?? '');
        $aciklama = trim($_POST['aciklama'] ?? '');
        $fiyat = str_replace(
            ',',
            '.',
            trim($_POST['fiyat'] ?? '')
        );

        if ($urunAdi === '') {
            http_response_code(400);
            exit('Ürün adı boş bırakılamaz.');
        }

        if (
            $fiyat === '' ||
            !is_numeric($fiyat) ||
            !is_finite((float)$fiyat) ||
            (float)$fiyat < 0
        ) {
            http_response_code(400);
            exit('Geçerli ve negatif olmayan bir fiyat girin.');
        }

        // Ürün bilgilerini güncelle.
        $guncelle = $db->prepare(
            'UPDATE urunler
             SET urun_adi = ?, fiyat = ?, aciklama = ?
             WHERE id = ?'
        );

        $guncelle->execute([
            $urunAdi,
            $fiyat,
            $aciklama,
            $urunId
        ]);

        $_SESSION['urun_mesaj'] = 'Ürün başarıyla güncellendi.';

    } else {
        http_response_code(400);
        exit('Geçersiz işlem.');
    }

    // İşlem yapılan ürünün kategorisine geri dön.
    header(
        'Location: kategori_urunleri.php?id=' . (int)$kategoriId
    );
    exit;

} catch (PDOException $e) {
    error_log('Ürün işlemi hatası: ' . $e->getMessage());

    http_response_code(500);
    exit('İşlem sırasında bir hata oluştu. Lütfen tekrar deneyin.');
}