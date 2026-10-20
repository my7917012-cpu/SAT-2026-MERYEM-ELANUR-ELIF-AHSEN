
<?php
session_start();

require_once __DIR__ . '/../config/baglanti.php';

// Admin girişi kontrolü
if (!isset($_SESSION['admin_id'])) {
    header('Location: giris.php');
    exit;
}

$mesaj = '';
$mesaj_tipi = '';

// CSRF güvenlik anahtarı
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// Görsellerin kaydedileceği klasör
$klasor = dirname(__DIR__) . '/uploads/gorseller/';

// Klasör yoksa oluştur
if (!is_dir($klasor)) {
    mkdir($klasor, 0755, true);
}

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gelen_token = $_POST['csrf_token'] ?? '';

    if (!is_string($gelen_token) ||
        !hash_equals($_SESSION['csrf_token'], $gelen_token)) {
        $mesaj = 'Güvenlik kontrolü başarısız. Sayfayı yenileyip tekrar dene.';
        $mesaj_tipi = 'hata';
    } else {
        $islem = $_POST['islem'] ?? '';

        // Bilgisayardan görsel yükleme
        if ($islem === 'yukle') {
            if (
                !isset($_FILES['gorsel']) ||
                $_FILES['gorsel']['error'] !== UPLOAD_ERR_OK
            ) {
                $mesaj = 'Görsel yüklenemedi. Bir dosya seçtiğinden emin ol.';
                $mesaj_tipi = 'hata';
            } elseif ($_FILES['gorsel']['size'] > 5 * 1024 * 1024) {
                $mesaj = 'Görsel en fazla 5 MB olabilir.';
                $mesaj_tipi = 'hata';
            } else {
                $gecici_dosya = $_FILES['gorsel']['tmp_name'];
                $bilgi = @getimagesize($gecici_dosya);

                $izinli_turler = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                    'image/gif'  => 'gif'
                ];

                $mime = $bilgi['mime'] ?? '';

                if (
                    !$bilgi ||
                    !isset($izinli_turler[$mime]) ||
                    !is_uploaded_file($gecici_dosya)
                ) {
                    $mesaj = 'Yalnızca JPG, PNG, WEBP veya GIF görseli yükleyebilirsin.';
                    $mesaj_tipi = 'hata';
                } else {
                    $uzanti = $izinli_turler[$mime];
                    $yeni_ad = bin2hex(random_bytes(16)) . '.' . $uzanti;
                    $hedef = $klasor . $yeni_ad;
                    $kayit_yolu = 'uploads/gorseller/' . $yeni_ad;

                    $gorsel_adi = trim(
                        pathinfo(
                            basename($_FILES['gorsel']['name']),
                            PATHINFO_FILENAME
                        )
                    );

                    if ($gorsel_adi === '') {
                        $gorsel_adi = 'Yeni görsel';
                    }

                    try {
                        if (move_uploaded_file($gecici_dosya, $hedef)) {
                            $sorgu = $db->prepare(
                                "INSERT INTO gorseller
                                (gorsel_adi, dosya_yolu, kaynak_tipi)
                                VALUES (?, ?, 'dosya')"
                            );

                            $sorgu->execute([$gorsel_adi, $kayit_yolu]);

                            $mesaj = 'Görsel başarıyla yüklendi.';
                            $mesaj_tipi = 'basari';
                        } else {
                            $mesaj = 'Dosya klasöre kaydedilemedi.';
                            $mesaj_tipi = 'hata';
                        }
                    } catch (Throwable $e) {
                        if (is_file($hedef)) {
                            unlink($hedef);
                        }

                        $mesaj = 'Görsel kaydedilemedi. Veritabanını kontrol et.';
                        $mesaj_tipi = 'hata';
                    }
                }
            }
        }

        // İnternet bağlantısından görsel ekleme
        elseif ($islem === 'internet_ekle') {
            $adres = trim($_POST['gorsel_url'] ?? '');
            $ad = trim($_POST['gorsel_adi'] ?? '');

            if (
                strlen($adres) > 1000 ||
                !filter_var($adres, FILTER_VALIDATE_URL) ||
                !in_array(
                    strtolower((string) parse_url($adres, PHP_URL_SCHEME)),
                    ['http', 'https'],
                    true
                )
            ) {
                $mesaj = 'Geçerli bir http veya https görsel bağlantısı gir.';
                $mesaj_tipi = 'hata';
            } elseif ($ad === '' || strlen($ad) > 255) {
                $mesaj = 'Görsel adı 1-255 karakter arasında olmalı.';
                $mesaj_tipi = 'hata';
            } else {
                try {
                    $sorgu = $db->prepare(
                        "INSERT INTO gorseller
                        (gorsel_adi, dosya_yolu, kaynak_tipi)
                        VALUES (?, ?, 'internet')"
                    );

                    $sorgu->execute([$ad, $adres]);

                    $mesaj = 'İnternet görseli havuza eklendi.';
                    $mesaj_tipi = 'basari';
                } catch (Throwable $e) {
                    $mesaj = 'Görsel eklenemedi. Veritabanını kontrol et.';
                    $mesaj_tipi = 'hata';
                }
            }
        }

        // Görsel silme
        elseif ($islem === 'sil') {
            $id = filter_input(INPUT_POST, 'gorsel_id', FILTER_VALIDATE_INT);

            try {
                if (!$id || $id < 1) {
                    throw new RuntimeException('Geçersiz görsel.');
                }

                $sorgu = $db->prepare(
                    'SELECT dosya_yolu, kaynak_tipi
                     FROM gorseller WHERE id = ?'
                );
                $sorgu->execute([$id]);
                $gorsel = $sorgu->fetch(PDO::FETCH_ASSOC);

                if (!$gorsel) {
                    throw new RuntimeException('Görsel bulunamadı.');
                }

                $sil = $db->prepare('DELETE FROM gorseller WHERE id = ?');
                $sil->execute([$id]);

                // Yalnızca havuzun kendi yüklediği dosyayı sil.
                if (
                    $gorsel['kaynak_tipi'] === 'dosya' &&
                    str_starts_with(
                        $gorsel['dosya_yolu'],
                        'uploads/gorseller/'
                    )
                ) {
                    $dosya = dirname(__DIR__) . '/' . $gorsel['dosya_yolu'];

                    if (is_file($dosya)) {
                        unlink($dosya);
                    }
                }

                $mesaj = 'Görsel havuzdan silindi.';
                $mesaj_tipi = 'basari';
            } catch (Throwable $e) {
                $mesaj = 'Görsel silinemedi veya bulunamadı.';
                $mesaj_tipi = 'hata';
            }
        }
    }
}

// Arama
$arama = trim($_GET['ara'] ?? '');

try {
    if ($arama !== '') {
        $sorgu = $db->prepare(
            'SELECT * FROM gorseller
             WHERE gorsel_adi LIKE ?
             ORDER BY id DESC'
        );
        $sorgu->execute(['%' . $arama . '%']);
    } else {
        $sorgu = $db->query(
            'SELECT * FROM gorseller ORDER BY id DESC'
        );
    }

    $gorseller = $sorgu->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $gorseller = [];
    $mesaj = 'Görseller listelenemedi. Önce gorseller tablosunu oluşturmalısın.';
    $mesaj_tipi = 'hata';
}

function h($deger) {
    return htmlspecialchars((string) $deger, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Görsel Havuzu - Restoran QR</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f4f5f7;
            color: #292929;
        }

        .sayfa {
            max-width: 1150px;
            margin: auto;
        }

        .ust {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 25px;
        }

        h1 {
            margin: 0 0 8px;
            color: #b71c1c;
        }

        .alt-yazi {
            color: #666;
            margin: 0;
        }

        .geri {
            display: inline-block;
            padding: 11px 15px;
            border-radius: 7px;
            background: #333;
            color: white;
            text-decoration: none;
        }

        .kart {
            background: white;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 22px;
            box-shadow: 0 3px 12px #0000000d;
        }

        h2 {
            margin-top: 0;
            font-size: 21px;
        }

        .form-alani {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="url"],
        input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 7px;
            margin-bottom: 13px;
            background: white;
        }

        button {
            border: 0;
            border-radius: 7px;
            padding: 11px 15px;
            cursor: pointer;
            font-weight: bold;
        }

        .ekle {
            background: #b71c1c;
            color: white;
        }

        .sil {
            background: #333;
            color: white;
        }

        .mesaj {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 18px;
        }

        .basari {
            background: #e4f7e8;
            color: #176b2c;
        }

        .hata {
            background: #fde8e8;
            color: #9d1515;
        }

        .arama {
            display: flex;
            gap: 10px;
        }

        .arama input {
            flex: 1;
            min-width: 0;
        }

        .gorsel-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 18px;
        }

        .gorsel-kart {
            overflow: hidden;
            border: 1px solid #e5e5e5;
            border-radius: 10px;
            background: white;
        }

        .gorsel-kart img {
            display: block;
            width: 100%;
            height: 165px;
            object-fit: cover;
            background: #eee;
        }

        .gorsel-bilgi {
            padding: 12px;
        }

        .gorsel-adi {
            overflow-wrap: anywhere;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .etiket {
            display: inline-block;
            padding: 5px 8px;
            margin-bottom: 10px;
            border-radius: 5px;
            background: #eee;
            color: #444;
            font-size: 12px;
        }

        .sil-formu {
            margin-top: 8px;
        }

        .bos {
            color: #666;
            padding: 15px 0;
        }

        @media (max-width: 650px) {
            body {
                padding: 14px;
            }

            .form-alani {
                grid-template-columns: 1fr;
            }

            .kart {
                padding: 16px;
            }
        }
    </style>
</head>

<body>
<div class="sayfa">

    <div class="ust">
        <div>
            <h1>Görsel Havuzu</h1>
            <p class="alt-yazi">
                Görsellerini yükle, internet bağlantısı ekle ve yönet.
            </p>
        </div>

        <a class="geri" href="panel.php">← Admin Paneline Dön</a>
    </div>

    <?php if ($mesaj !== ''): ?>
        <div class="mesaj <?= h($mesaj_tipi) ?>">
            <?= h($mesaj) ?>
        </div>
    <?php endif; ?>

    <div class="kart">
        <div class="form-alani">

            <div>
                <h2>Bilgisayardan Görsel Yükle</h2>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token"
                           value="<?= h($csrf_token) ?>">
                    <input type="hidden" name="islem" value="yukle">

                    <label for="gorsel">Görsel dosyası</label>
                    <input
                        type="file"
                        id="gorsel"
                        name="gorsel"
                        accept=".jpg,.jpeg,.png,.webp,.gif"
                        required
                    >

                    <small>JPG, PNG, WEBP veya GIF. En fazla 5 MB.</small>
                    <br><br>

                    <button class="ekle" type="submit">
                        Görseli Yükle
                    </button>
                </form>
            </div>

            <div>
                <h2>İnternetten Görsel Ekle</h2>

                <form method="POST">
                    <input type="hidden" name="csrf_token"
                           value="<?= h($csrf_token) ?>">
                    <input type="hidden" name="islem"
                           value="internet_ekle">

                    <label for="gorsel_adi">Görsel adı</label>
                    <input
                        type="text"
                        id="gorsel_adi"
                        name="gorsel_adi"
                        maxlength="255"
                        placeholder="Örn. Izgara köfte"
                        required
                    >

                    <label for="gorsel_url">Görsel bağlantısı</label>
                    <input
                        type="url"
                        id="gorsel_url"
                        name="gorsel_url"
                        maxlength="1000"
                        placeholder="https://ornek.com/gorsel.jpg"
                        required
                    >

                    <button class="ekle" type="submit">
                        Bağlantıyı Ekle
                    </button>
                </form>
            </div>

        </div>
    </div>

    <div class="kart">
        <h2>Kaydedilen Görseller</h2>

        <form method="GET" class="arama">
            <input
                type="text"
                name="ara"
                placeholder="Görsel adına göre ara..."
                value="<?= h($arama) ?>"
            >
            <button class="ekle" type="submit">Ara</button>
        </form>

        <p>Toplam bulunan görsel: <strong><?= count($gorseller) ?></strong></p>

        <?php if (count($gorseller) > 0): ?>
            <div class="gorsel-grid">

                <?php foreach ($gorseller as $gorsel): ?>
                    <?php
                    $yol = $gorsel['dosya_yolu'];

                    if ($gorsel['kaynak_tipi'] === 'internet') {
                        $resim_adresi = $yol;
                    } else {
                        $resim_adresi = '../' . ltrim($yol, '/');
                    }
                    ?>

                    <div class="gorsel-kart">
                        <a href="<?= h($resim_adresi) ?>"
                           target="_blank" rel="noopener noreferrer">
                            <img
                                src="<?= h($resim_adresi) ?>"
                                alt="<?= h($gorsel['gorsel_adi']) ?>"
                                loading="lazy"
                            >
                        </a>

                        <div class="gorsel-bilgi">
                            <div class="gorsel-adi">
                                <?= h($gorsel['gorsel_adi']) ?>
                            </div>

                            <span class="etiket">
                                <?= $gorsel['kaynak_tipi'] === 'dosya'
                                    ? 'Bilgisayardan yüklendi'
                                    : 'İnternet bağlantısı' ?>
                            </span>

                            <form method="POST" class="sil-formu"
                                  onsubmit="return confirm('Bu görseli havuzdan silmek istediğine emin misin?');">

                                <input type="hidden" name="csrf_token"
                                       value="<?= h($csrf_token) ?>">
                                <input type="hidden" name="islem" value="sil">
                                <input type="hidden" name="gorsel_id"
                                       value="<?= (int) $gorsel['id'] ?>">

                                <button class="sil" type="submit">
                                    Görseli Sil
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php else: ?>
            <p class="bos">
                Henüz görsel eklenmemiş veya aramana uygun görsel bulunamadı.
            </p>
        <?php endif; ?>
    </div>

</div>
</body>
</html>