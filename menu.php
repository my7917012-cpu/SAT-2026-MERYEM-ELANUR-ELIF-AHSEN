<?php
require_once __DIR__ . '/config/baglanti.php';

try {
    $kategoriSorgu = $db->query("SELECT * FROM kategoriler");
    $kategoriler = $kategoriSorgu->fetchAll(PDO::FETCH_ASSOC);

    $urunSorgu = $db->query("SELECT * FROM urunler");
    $urunler = $urunSorgu->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Veritabanı hatası: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restoran Menüsü</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            color: #222;
        }

        header {
            background: #b71c1c;
            color: white;
            text-align: center;
            padding: 25px 15px;
        }

        header h1 {
            margin: 0 0 8px;
        }

        .container {
            max-width: 1100px;
            margin: 25px auto;
            padding: 0 15px;
        }

        .kategoriler {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 25px;
        }

        .kategoriler a {
            padding: 10px 16px;
            border: 1px solid #b71c1c;
            border-radius: 22px;
            background: white;
            color: #b71c1c;
            text-decoration: none;
            font-weight: bold;
        }

        .kategoriler a:hover {
            background: #b71c1c;
            color: white;
        }

        .kategori-baslik {
            margin-top: 30px;
            padding-bottom: 8px;
            border-bottom: 2px solid #b71c1c;
        }

        .urunler {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        .urun-kart {
            padding: 15px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow-wrap: anywhere;
        }

        .urun-kart h3 {
            margin-top: 0;
        }

        .aciklama {
            color: #666;
            line-height: 1.5;
        }

        .fiyat {
            color: #b71c1c;
            font-weight: bold;
            font-size: 18px;
        }

        .mesaj {
            padding: 20px;
            background: white;
            border-radius: 10px;
            text-align: center;
        }

        footer {
            margin-top: 35px;
            padding: 20px;
            text-align: center;
            color: #777;
        }

        @media (max-width: 480px) {
            .urunler {
                gap: 10px;
            }

            .urun-kart {
                padding: 12px;
            }

            .urun-kart h3 {
                font-size: 16px;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>Restoran Menümüz</h1>
    <p>Lezzetlerimizi keşfedin</p>
</header>

<main class="container">

    <?php if (!empty($kategoriler)): ?>
        <nav class="kategoriler">
            <?php foreach ($kategoriler as $kategori): ?>
                <?php
                $kategoriId = $kategori['id'] ?? $kategori['kategori_id'] ?? '';
                $kategoriAdi = $kategori['kategori_adi'] ?? $kategori['ad'] ?? 'Kategori';
                ?>
                <a href="#kategori-<?= htmlspecialchars((string)$kategoriId) ?>">
                    <?= htmlspecialchars((string)$kategoriAdi) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if (empty($urunler)): ?>

        <div class="mesaj">
            <h2>Henüz ürün bulunmuyor.</h2>
            <p>Ürünler eklendiğinde burada görüntülenecek.</p>
        </div>

    <?php else: ?>

        <?php foreach ($kategoriler as $kategori): ?>
            <?php
            $kategoriId = $kategori['id'] ?? $kategori['kategori_id'] ?? '';
            $kategoriAdi = $kategori['kategori_adi'] ?? $kategori['ad'] ?? 'Kategori';

            $kategoriUrunleri = [];

            foreach ($urunler as $urun) {
                $urunKategoriId = $urun['kategori_id'] ?? '';

                if (
                    (string)$urunKategoriId === (string)$kategoriId &&
                    (string)$kategoriId !== ''
                ) {
                    $kategoriUrunleri[] = $urun;
                }
            }
            ?>

            <section id="kategori-<?= htmlspecialchars((string)$kategoriId) ?>">

                <h2 class="kategori-baslik">
                    <?= htmlspecialchars((string)$kategoriAdi) ?>
                </h2>

                <?php if (!empty($kategoriUrunleri)): ?>
                    <div class="urunler">

                        <?php foreach ($kategoriUrunleri as $urun): ?>
                            <?php
                            $urunAdi = $urun['urun_adi'] ?? $urun['ad'] ?? 'Ürün';
                            $urunFiyati = $urun['fiyat'] ?? '';
                            $urunAciklama = $urun['aciklama'] ?? '';
                            ?>

                            <article class="urun-kart">

                                <h3>
                                    <?= htmlspecialchars((string)$urunAdi) ?>
                                </h3>

                                <?php if ($urunAciklama !== ''): ?>
                                    <p class="aciklama">
                                        <?= htmlspecialchars((string)$urunAciklama) ?>
                                    </p>
                                <?php endif; ?>

                                <?php if ($urunFiyati !== ''): ?>
                                    <p class="fiyat">
                                        <?= htmlspecialchars((string)$urunFiyati) ?> TL
                                    </p>
                                <?php endif; ?>

                            </article>
                        <?php endforeach; ?>

                    </div>
                <?php else: ?>
                    <p>Bu kategoride henüz ürün bulunmuyor.</p>
                <?php endif; ?>

            </section>

        <?php endforeach; ?>

    <?php endif; ?>

</main>

<footer>
    Afiyet olsun!
</footer>

</body>
</html>