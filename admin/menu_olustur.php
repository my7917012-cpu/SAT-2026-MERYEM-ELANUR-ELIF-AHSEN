
<?php
session_start();

require_once "../config/baglanti.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: giris.php");
    exit;
}

if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$mesaj = "";
$hata = "";

// Aktif ürünleri getir
$sorgu = $db->query("
    SELECT id, urun_adi, fiyat
    FROM urunler
    WHERE aktif = 1
    ORDER BY urun_adi ASC
");
$urunler = $sorgu->fetchAll();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        if (
            !isset($_POST["csrf_token"]) ||
            !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
        ) {
            throw new Exception("Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.");
        }

        $menu_adi = trim($_POST["menu_adi"] ?? "");
        $menu_fiyati = trim($_POST["menu_fiyati"] ?? "");
        $indirim_orani = trim($_POST["indirim_orani"] ?? "0");
        $baslangic = trim($_POST["indirim_baslangic"] ?? "");
        $bitis = trim($_POST["indirim_bitis"] ?? "");

        $secilen_urunler = $_POST["urunler"] ?? [];

        if ($menu_adi === "") {
            throw new Exception("Menü adı boş bırakılamaz.");
        }

        if (
            !is_numeric($menu_fiyati) ||
            (float)$menu_fiyati < 0
        ) {
            throw new Exception("Geçerli bir menü fiyatı girin.");
        }

        if (
            !is_numeric($indirim_orani) ||
            (float)$indirim_orani < 0 ||
            (float)$indirim_orani > 100
        ) {
            throw new Exception("İndirim oranı 0 ile 100 arasında olmalıdır.");
        }

        if (!is_array($secilen_urunler) || count($secilen_urunler) === 0) {
            throw new Exception("En az bir ürün seçmelisiniz.");
        }

        if (($baslangic === "") !== ($bitis === "")) {
            throw new Exception("İndirim için başlangıç ve bitiş tarihlerini birlikte girin.");
        }

        if ($baslangic !== "" && strtotime($baslangic) >= strtotime($bitis)) {
            throw new Exception("İndirim bitişi, başlangıç tarihinden sonra olmalıdır.");
        }

        // Ürün ID'lerini doğrula
        $secilen_urunler = array_values(array_unique(
            array_filter(
                array_map("intval", $secilen_urunler),
                fn($id) => $id > 0
            )
        ));

        if (count($secilen_urunler) === 0) {
            throw new Exception("Geçerli bir ürün seçmelisiniz.");
        }

        $yer_tutucular = implode(",", array_fill(0, count($secilen_urunler), "?"));

        $urun_sorgu = $db->prepare("
            SELECT id
            FROM urunler
            WHERE aktif = 1 AND id IN ($yer_tutucular)
        ");
        $urun_sorgu->execute($secilen_urunler);
        $gecerli_idler = array_map("intval", $urun_sorgu->fetchAll(PDO::FETCH_COLUMN));

        if (count($gecerli_idler) !== count($secilen_urunler)) {
            throw new Exception("Seçilen ürünlerden biri bulunamadı veya artık aktif değil.");
        }

        $db->beginTransaction();

        // Özel menüyü kaydet
        $ekle = $db->prepare("
            INSERT INTO ozel_menuler
            (menu_adi, menu_fiyati, indirim_orani, indirim_baslangic, indirim_bitis, aktif)
            VALUES (?, ?, ?, ?, ?, 1)
        ");

        $ekle->execute([
            $menu_adi,
            (float)$menu_fiyati,
            (float)$indirim_orani,
            $baslangic !== "" ? date("Y-m-d H:i:s", strtotime($baslangic)) : null,
            $bitis !== "" ? date("Y-m-d H:i:s", strtotime($bitis)) : null
        ]);

        $menu_id = $db->lastInsertId();

        // Seçilen ürünleri menüye bağla
        $urun_ekle = $db->prepare("
            INSERT INTO ozel_menu_urunleri (menu_id, urun_id)
            VALUES (?, ?)
        ");

        foreach ($gecerli_idler as $urun_id) {
            $urun_ekle->execute([$menu_id, $urun_id]);
        }

        $db->commit();

        $mesaj = "Özel menü başarıyla oluşturuldu.";
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $hata = $e->getMessage();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $hata = "Menü kaydedilemedi. Veritabanı ayarlarını kontrol edin.";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Özel Menü Oluştur</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            color: #333;
        }

        .kutu {
            max-width: 800px;
            margin: 0 auto;
            padding: 25px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        h1 {
            margin-top: 0;
        }

        label {
            display: block;
            margin-top: 16px;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="number"],
        input[type="datetime-local"] {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .urun-listesi {
            max-height: 300px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .urun {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 0;
            border-bottom: 1px solid #eee;
        }

        .urun:last-child {
            border-bottom: none;
        }

        .urun label {
            display: flex;
            flex: 1;
            justify-content: space-between;
            gap: 10px;
            margin: 0;
            font-weight: normal;
        }

        .toplam {
            margin-top: 12px;
            font-weight: bold;
        }

        button, .geri {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 18px;
            border: none;
            border-radius: 5px;
            background: #198754;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 15px;
        }

        .geri {
            background: #555;
            margin-left: 8px;
        }

        .mesaj {
            padding: 12px;
            margin-bottom: 15px;
            background: #d1e7dd;
            color: #0f5132;
            border-radius: 5px;
        }

        .hata {
            padding: 12px;
            margin-bottom: 15px;
            background: #f8d7da;
            color: #842029;
            border-radius: 5px;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }

            .kutu {
                padding: 16px;
            }
        }
    </style>
</head>

<body>
<div class="kutu">

    <h1>Özel Menü Oluştur</h1>

    <?php if ($mesaj !== ""): ?>
        <div class="mesaj">
            <?= htmlspecialchars($mesaj, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <?php if ($hata !== ""): ?>
        <div class="hata">
            <?= htmlspecialchars($hata, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>"
        >

        <label for="menu_adi">Menü Adı</label>
        <input
            type="text"
            id="menu_adi"
            name="menu_adi"
            maxlength="150"
            required
            value="<?= htmlspecialchars($_POST["menu_adi"] ?? "", ENT_QUOTES, "UTF-8") ?>"
        >

        <label>Menüye Eklenecek Ürünler</label>

        <div class="urun-listesi">
            <?php if (count($urunler) > 0): ?>

                <?php foreach ($urunler as $urun): ?>
                    <div class="urun">
                        <input
                            type="checkbox"
                            class="urun-sec"
                            name="urunler[]"
                            value="<?= (int)$urun["id"] ?>"
                            data-fiyat="<?= htmlspecialchars((string)$urun["fiyat"], ENT_QUOTES, "UTF-8") ?>"
                            id="urun_<?= (int)$urun["id"] ?>"
                            <?= in_array(
                                (int)$urun["id"],
                                array_map("intval", $_POST["urunler"] ?? []),
                                true
                            ) ? "checked" : "" ?>
                        >

                        <label for="urun_<?= (int)$urun["id"] ?>">
                            <span><?= htmlspecialchars($urun["urun_adi"], ENT_QUOTES, "UTF-8") ?></span>
                            <span><?= number_format((float)$urun["fiyat"], 2, ",", ".") ?> TL</span>
                        </label>
                    </div>
                <?php endforeach; ?>

            <?php else: ?>
                <p>Aktif ürün bulunamadı.</p>
            <?php endif; ?>
        </div>

        <p class="toplam">
            Seçilen Ürünlerin Toplamı:
            <span id="toplam">0,00 TL</span>
        </p>

        <label for="menu_fiyati">Özel Menü Fiyatı (TL)</label>
        <input
            type="number"
            id="menu_fiyati"
            name="menu_fiyati"
            min="0"
            step="0.01"
            required
            value="<?= htmlspecialchars($_POST["menu_fiyati"] ?? "", ENT_QUOTES, "UTF-8") ?>"
        >

        <label for="indirim_orani">İndirim Oranı (%)</label>
        <input
            type="number"
            id="indirim_orani"
            name="indirim_orani"
            min="0"
            max="100"
            step="0.01"
            value="<?= htmlspecialchars($_POST["indirim_orani"] ?? "0", ENT_QUOTES, "UTF-8") ?>"
            required
        >

        <label for="indirim_baslangic">İndirim Başlangıç Tarihi (isteğe bağlı)</label>
        <input
            type="datetime-local"
            id="indirim_baslangic"
            name="indirim_baslangic"
            value="<?= htmlspecialchars($_POST["indirim_baslangic"] ?? "", ENT_QUOTES, "UTF-8") ?>"
        >

        <label for="indirim_bitis">İndirim Bitiş Tarihi (isteğe bağlı)</label>
        <input
            type="datetime-local"
            id="indirim_bitis"
            name="indirim_bitis"
            value="<?= htmlspecialchars($_POST["indirim_bitis"] ?? "", ENT_QUOTES, "UTF-8") ?>"
        >

        <div>
            <button type="submit">Özel Menüyü Kaydet</button>
            <a href="panel.php" class="geri">Panele Dön</a>
        </div>

    </form>
</div>

<script>
    const secimler = document.querySelectorAll(".urun-sec");
    const toplamAlan = document.getElementById("toplam");

    function toplamHesapla() {
        let toplam = 0;

        secimler.forEach(function (secim) {
            if (secim.checked) {
                toplam += Number(secim.dataset.fiyat);
            }
        });

        toplamAlan.textContent = toplam.toLocaleString("tr-TR", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + " TL";
    }

    secimler.forEach(function (secim) {
        secim.addEventListener("change", toplamHesapla);
    });

    toplamHesapla();
</script>

</body>
</html>
