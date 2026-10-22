
<?php
require_once __DIR__ . '/config/baglanti.php';

try {
    $menuSorgu = $db->query("
        SELECT *
        FROM ozel_menuler
        WHERE aktif = 1
        ORDER BY olusturma_tarihi DESC
    ");

    $menuler = $menuSorgu->fetchAll(PDO::FETCH_ASSOC);

    $urunSorgu = $db->prepare("
        SELECT u.id, u.urun_adi, u.aciklama, u.fiyat, u.fotograf
        FROM ozel_menu_urunleri omu
        INNER JOIN urunler u ON u.id = omu.urun_id
        WHERE omu.menu_id = ? AND u.aktif = 1
        ORDER BY u.urun_adi ASC
    ");

    $menuUrunleri = [];

    foreach ($menuler as $menu) {
        $urunSorgu->execute([$menu['id']]);
        $menuUrunleri[$menu['id']] = $urunSorgu->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Veritabanı hatası oluştu. Lütfen daha sonra tekrar deneyin.");
}

function tl($fiyat) {
    return number_format((float)$fiyat, 2, ',', '.') . ' TL';
}

function indirimAktifMi($menu) {
    $oran = (float)($menu['indirim_orani'] ?? 0);

    if ($oran <= 0) {
        return false;
    }

    $baslangic = $menu['indirim_baslangic'] ?? null;
    $bitis = $menu['indirim_bitis'] ?? null;

    // Tarih girilmemişse indirim sürekli geçerlidir.
    if (empty($baslangic) && empty($bitis)) {
        return true;
    }

    // İki tarih de girilmiş olmalıdır.
    if (empty($baslangic) || empty($bitis)) {
        return false;
    }

    $simdi = time();

    return $simdi >= strtotime($baslangic)
        && $simdi <= strtotime($bitis);
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

        .menu-kart {
            margin-bottom: 25px;
            padding: 20px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .menu-baslik {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #b71c1c;
        }

        .urunler {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        .urun-kart {
            padding: 15px;
            border-radius: 8px;
            background: #f9f9f9;
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

        .eski-fiyat {
            color: #777;
            text-decoration: line-through;
            font-size: 15px;
            margin-right: 8px;
        }

        .indirim {
            display: inline-block;
            padding: 5px 9px;
            background: #ffebee;
            color: #b71c1c;
            border-radius: 5px;
            font-weight: bold;
            font-size: 13px;
        }

        .menu-fiyat {
            margin: 15px 0;
            font-size: 22px;
            font-weight: bold;
            color: #b71c1c;
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
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .menu-kart {
                padding: 14px;
            }

            .urun-kart {
                padding: 12px;
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

    <?php if (empty($menuler)): ?>

        <div class="mesaj">
            <h2>Henüz özel menü bulunmuyor.</h2>
            <p>Menülerimiz hazırlandığında burada görüntülenecek.</p>
        </div>

    <?php else: ?>

        <?php foreach ($menuler as $menu): ?>
            <?php
            $urunler = $menuUrunleri[$menu['id']] ?? [];
            $indirimVar = indirimAktifMi($menu);
            $normalFiyat = (float)$menu['menu_fiyati'];
            $indirimOrani = (float)$menu['indirim_orani'];

            $gosterilenFiyat = $indirimVar
                ? $normalFiyat * (1 - $indirimOrani / 100)
                : $normalFiyat;
            ?>

            <section class="menu-kart">

                <h2 class="menu-baslik">
                    <?= htmlspecialchars($menu['menu_adi'], ENT_QUOTES, 'UTF-8') ?>
                </h2>

                <?php if ($indirimVar): ?>

                    <p>
                        <span class="eski-fiyat">
                            <?= tl($normalFiyat) ?>
                        </span>

                        <span class="indirim">
                            %<?= rtrim(rtrim(number_format($indirimOrani, 2, '.', ''), '0'), '.') ?> indirim
                        </span>
                    </p>

                    <p class="menu-fiyat">
                        <?= tl($gosterilenFiyat) ?>
                    </p>

                <?php else: ?>

                    <p class="menu-fiyat">
                        <?= tl($normalFiyat) ?>
                    </p>

                <?php endif; ?>

                <?php if (!empty($urunler)): ?>

                    <h3>Menü İçeriği</h3>

                    <div class="urunler">

                        <?php foreach ($urunler as $urun): ?>

                            <article class="urun-kart">

                                <h3>
                                    <?= htmlspecialchars($urun['urun_adi'], ENT_QUOTES, 'UTF-8') ?>
                                </h3>

                                <?php if (!empty($urun['fotograf'])): ?>
                                    <img
                                        src="<?= htmlspecialchars($urun['fotograf'], ENT_QUOTES, 'UTF-8') ?>"
                                        alt="<?= htmlspecialchars($urun['urun_adi'], ENT_QUOTES, 'UTF-8') ?>"
                                        style="width:100%; max-height:200px; object-fit:cover; border-radius:6px;"
                                    >
                                <?php endif; ?>

                                <?php if (!empty($urun['aciklama'])): ?>
                                    <p class="aciklama">
                                        <?= htmlspecialchars($urun['aciklama'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                <?php endif; ?>

                                <p class="fiyat">
                                    <?= tl($urun['fiyat']) ?>
                                </p>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <p>Bu menüye bağlı aktif ürün bulunmuyor.</p>

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
