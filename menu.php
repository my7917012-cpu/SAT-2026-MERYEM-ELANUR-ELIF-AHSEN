
<?php
session_start();

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

    if (empty($baslangic) && empty($bitis)) {
        return true;
    }

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
            background: #f4eee8;
            color: #333;
        }

        /* ÜST ALAN */

        header {
            background: linear-gradient(120deg, #590014, #8b001e, #650017);
            color: white;
            padding: 25px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            border-bottom: 4px solid #c49a58;
            box-shadow: 0 5px 20px rgba(70,0,15,0.18);
        }

        header h1 {
            margin: 0;
            font-size: 27px;
            letter-spacing: 0.5px;
        }

        header p {
            margin: 6px 0 0;
            color: #ead5bd;
            font-size: 14px;
        }

        .panel-buton {
            background: white;
            color: #650017;
            padding: 12px 17px;
            border-radius: 7px;
            text-decoration: none;
            font-weight: bold;
            transition: 0.3s;
            white-space: nowrap;
        }

        .panel-buton:hover {
            background: #c49a58;
            color: white;
        }

        /* ANA ALAN */

        .container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 18px;
        }

        .sayfa-aciklama {
            margin-bottom: 22px;
            color: #76665d;
            line-height: 1.5;
        }

        .sayfa-aciklama h2 {
            color: #800020;
            font-size: 22px;
            margin: 0 0 7px;
        }

        /* MENÜ KARTI */

        .menu-kart {
            background: white;
            padding: 18px;
            margin-bottom: 20px;
            border-radius: 11px;
            border-top: 4px solid #800020;
            box-shadow: 0 5px 18px rgba(75,20,30,0.08);
        }

        /* KATEGORİ KUTUSU GİBİ KÜÇÜK MENÜ BAŞLIĞI */

        .menu-baslik {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: auto;
            min-width: 145px;
            max-width: 100%;
            padding: 9px 18px;
            margin: 0 0 14px;
            background: #800020;
            color: white;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            line-height: 1.3;
            border: none;
        }

        /* FİYAT VE İNDİRİM */

        .menu-fiyat {
            margin: 10px 0 16px;
            color: #800020;
            font-size: 22px;
            font-weight: bold;
        }

        .eski-fiyat {
            color: #888;
            text-decoration: line-through;
            font-size: 15px;
            margin-right: 7px;
        }

        .indirim {
            display: inline-block;
            padding: 5px 9px;
            background: #800020;
            color: white;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }

        .indirim-tarih {
            color: #806f65;
            font-size: 13px;
            margin: -7px 0 15px;
        }

        .icerik-baslik {
            color: #650017;
            font-size: 16px;
            margin: 16px 0 12px;
        }

        /* ÜRÜNLER */

        .urunler {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 13px;
        }

        .urun-kart {
            display: flex;
            gap: 13px;
            align-items: flex-start;
            padding: 12px;
            background: #faf6f1;
            border: 1px solid #eee1d7;
            border-radius: 8px;
            transition: 0.25s;
            min-width: 0;
        }

        .urun-kart:hover {
            border-color: #c49a58;
            box-shadow: 0 4px 12px rgba(75,20,30,0.08);
        }

        .urun-resim {
            width: 105px;
            height: 105px;
            flex-shrink: 0;
            object-fit: cover;
            border-radius: 7px;
            background: #eee5df;
        }

        .urun-bilgi {
            min-width: 0;
            flex: 1;
        }

        .urun-kart h3 {
            margin: 2px 0 7px;
            color: #650017;
            font-size: 16px;
            overflow-wrap: anywhere;
        }

        .aciklama {
            margin: 0 0 8px;
            color: #766b64;
            font-size: 13px;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .fiyat {
            margin: 0;
            color: #800020;
            font-size: 16px;
            font-weight: bold;
        }

        /* MESAJ */

        .mesaj {
            background: white;
            padding: 25px 18px;
            border-radius: 10px;
            border-top: 4px solid #800020;
            box-shadow: 0 5px 18px rgba(75,20,30,0.08);
            text-align: center;
        }

        .mesaj h2 {
            color: #800020;
            font-size: 20px;
        }

        .mesaj p {
            color: #766b64;
        }

        /* ALT ALAN */

        footer {
            margin-top: 35px;
            padding: 20px;
            text-align: center;
            background: #650017;
            color: #ead5bd;
            border-top: 4px solid #c49a58;
        }

        footer p {
            margin: 0;
        }

        /* MOBİL */

        @media (max-width: 650px) {
            header {
                padding: 22px 18px;
            }

            header h1 {
                font-size: 22px;
            }

            .panel-buton {
                width: 100%;
                text-align: center;
            }

            .container {
                margin: 22px auto;
                padding: 0 13px;
            }

            .menu-kart {
                padding: 15px;
                margin-bottom: 17px;
            }

            .menu-baslik {
                min-width: 130px;
                padding: 8px 14px;
                font-size: 15px;
            }

            .urunler {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .urun-kart {
                padding: 10px;
                gap: 10px;
            }

            .urun-resim {
                width: 90px;
                height: 90px;
            }

            .urun-kart h3 {
                font-size: 15px;
            }

            .menu-fiyat {
                font-size: 20px;
            }
        }

        @media (max-width: 360px) {
            .urun-resim {
                width: 75px;
                height: 75px;
            }
        }
    </style>
</head>

<body>

<header>
    <div>
        <h1>Restoran Menümüz</h1>
        <p>Lezzetlerimizi keşfedin</p>
    </div>

    <a href="admin/restoran_paneli.php" class="panel-buton">
        Restoran Paneline Dön
    </a>
</header>

<main class="container">

    <div class="sayfa-aciklama">
        <h2>Menülerimiz</h2>
        <p>Özenle hazırlanan menülerimizi ve lezzetlerimizi inceleyebilirsiniz.</p>
    </div>

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

                    <?php if (!empty($menu['indirim_baslangic']) && !empty($menu['indirim_bitis'])): ?>
                        <p class="indirim-tarih">
                            İndirim geçerlilik tarihi:
                            <?= date('d.m.Y H:i', strtotime($menu['indirim_baslangic'])) ?>
                            -
                            <?= date('d.m.Y H:i', strtotime($menu['indirim_bitis'])) ?>
                        </p>
                    <?php endif; ?>

                <?php else: ?>

                    <p class="menu-fiyat">
                        <?= tl($normalFiyat) ?>
                    </p>

                <?php endif; ?>

                <?php if (!empty($urunler)): ?>

                    <h3 class="icerik-baslik">Menü İçeriği</h3>

                    <div class="urunler">

                        <?php foreach ($urunler as $urun): ?>

                            <article class="urun-kart">

                                <?php if (!empty($urun['fotograf'])): ?>
                                    <img
                                        class="urun-resim"
                                        src="<?= htmlspecialchars($urun['fotograf'], ENT_QUOTES, 'UTF-8') ?>"
                                        alt="<?= htmlspecialchars($urun['urun_adi'], ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                <?php endif; ?>

                                <div class="urun-bilgi">

                                    <h3>
                                        <?= htmlspecialchars($urun['urun_adi'], ENT_QUOTES, 'UTF-8') ?>
                                    </h3>

                                    <?php if (!empty($urun['aciklama'])): ?>
                                        <p class="aciklama">
                                            <?= htmlspecialchars($urun['aciklama'], ENT_QUOTES, 'UTF-8') ?>
                                        </p>
                                    <?php endif; ?>

                                    <p class="fiyat">
                                        <?= tl($urun['fiyat']) ?>
                                    </p>

                                </div>

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
    <p>Afiyet olsun!</p>
</footer>

</body>
</html>
