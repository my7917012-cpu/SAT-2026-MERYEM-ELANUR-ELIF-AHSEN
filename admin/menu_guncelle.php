
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

$hata = "";
$mesaj = "";

$menu_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $menu_id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
}

if (!$menu_id || $menu_id < 1) {
    die("Geçersiz menü ID.");
}

$menuSorgu = $db->prepare("SELECT * FROM ozel_menuler WHERE id = ?");
$menuSorgu->execute([$menu_id]);
$menu = $menuSorgu->fetch();

if (!$menu) {
    die("Menü bulunamadı.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        if (
            !isset($_POST["csrf_token"]) ||
            !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
        ) {
            throw new Exception("Güvenlik doğrulaması başarısız. Sayfayı yenileyin.");
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

        if (!is_numeric($menu_fiyati) || (float)$menu_fiyati < 0) {
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
            throw new Exception("İndirim başlangıç ve bitiş tarihlerini birlikte girin.");
        }

        if (
            $baslangic !== "" &&
            (
                strtotime($baslangic) === false ||
                strtotime($bitis) === false ||
                strtotime($baslangic) >= strtotime($bitis)
            )
        ) {
            throw new Exception("İndirim bitişi, başlangıç tarihinden sonra olmalıdır.");
        }

        $secilen_urunler = array_values(array_unique(
            array_filter(
                array_map("intval", $secilen_urunler),
                fn($id) => $id > 0
            )
        ));

        if (count($secilen_urunler) === 0) {
            throw new Exception("Geçerli ürün seçmelisiniz.");
        }

        $yerler = implode(",", array_fill(0, count($secilen_urunler), "?"));

        $urunKontrol = $db->prepare("
            SELECT id
            FROM urunler
            WHERE aktif = 1 AND id IN ($yerler)
        ");
        $urunKontrol->execute($secilen_urunler);
        $gecerliUrunler = array_map(
            "intval",
            $urunKontrol->fetchAll(PDO::FETCH_COLUMN)
        );

        if (count($gecerliUrunler) !== count($secilen_urunler)) {
            throw new Exception("Seçilen ürünlerden biri bulunamadı veya aktif değil.");
        }

        $db->beginTransaction();

        $guncelle = $db->prepare("
            UPDATE ozel_menuler
            SET menu_adi = ?,
                menu_fiyati = ?,
                indirim_orani = ?,
                indirim_baslangic = ?,
                indirim_bitis = ?
            WHERE id = ?
        ");

        $guncelle->execute([
            $menu_adi,
            (float)$menu_fiyati,
            (float)$indirim_orani,
            $baslangic !== "" ? date("Y-m-d H:i:s", strtotime($baslangic)) : null,
            $bitis !== "" ? date("Y-m-d H:i:s", strtotime($bitis)) : null,
            $menu_id
        ]);

        $sil = $db->prepare("DELETE FROM ozel_menu_urunleri WHERE menu_id = ?");
        $sil->execute([$menu_id]);

        $urunEkle = $db->prepare("
            INSERT INTO ozel_menu_urunleri (menu_id, urun_id)
            VALUES (?, ?)
        ");

        foreach ($gecerliUrunler as $urun_id) {
            $urunEkle->execute([$menu_id, $urun_id]);
        }

        $db->commit();

        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

        header("Location: menu_guncelle.php?id=" . $menu_id . "&guncellendi=1");
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $hata = $e->getMessage();

    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $hata = "Menü güncellenemedi. Lütfen tekrar deneyin.";
    }
}

$menuSorgu = $db->prepare("SELECT * FROM ozel_menuler WHERE id = ?");
$menuSorgu->execute([$menu_id]);
$menu = $menuSorgu->fetch();

$urunSorgu = $db->query("
    SELECT id, urun_adi, fiyat
    FROM urunler
    WHERE aktif = 1
    ORDER BY urun_adi ASC
");
$urunler = $urunSorgu->fetchAll();

$bagliSorgu = $db->prepare("
    SELECT urun_id
    FROM ozel_menu_urunleri
    WHERE menu_id = ?
");
$bagliSorgu->execute([$menu_id]);
$bagliUrunler = array_map("intval", $bagliSorgu->fetchAll(PDO::FETCH_COLUMN));

function tarihInputaCevir($tarih) {
    if (empty($tarih)) {
        return "";
    }

    return date("Y-m-d\TH:i", strtotime($tarih));
}

if (isset($_GET["guncellendi"])) {
    $mesaj = "Menü başarıyla güncellendi.";
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Özel Menüyü Güncelle</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 20px;
            background: #f4f6f9;
            font-family: Arial, sans-serif;
            color: #333;
        }

        .kutu {
            max-width: 800px;
            margin: auto;
            padding: 25px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,.08);
        }

        label {
            display: block;
            margin: 16px 0 7px;
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
            padding: 10px 0;
            border-bottom: 1px solid #eee;
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
            border: 0;
            border-radius: 5px;
            background: #198754;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 15px;
        }

        .geri { background: #555; }

        .mesaj, .hata {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 5px;
        }

        .mesaj {
            background: #d1e7dd;
            color: #0f5132;
        }

        .hata {
            background: #f8d7da;
            color: #842029;
        }

        @media (max-width: 600px) {
            body { padding: 10px; }
            .kutu { padding: 15px; }
        }
    </style>
</head>

<body>
<div class="kutu">

    <h1>Özel Menüyü Güncelle</h1>

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
        <input type="hidden" name="id" value="<?= (int)$menu_id ?>">
        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">

        <label for="menu_adi">Menü Adı</label>
        <input type="text" id="menu_adi" name="menu_adi" maxlength="150"
               required value="<?= htmlspecialchars($menu["menu_adi"], ENT_QUOTES, "UTF-8") ?>">

        <label>Menü Ürünleri</label>
        <div class="urun-listesi">
            <?php foreach ($urunler as $urun): ?>
                <div class="urun">
                    <input type="checkbox"
                           class="urun-sec"
                           name="urunler[]"
                           id="urun_<?= (int)$urun["id"] ?>"
                           value="<?= (int)$urun["id"] ?>"
                           data-fiyat="<?= htmlspecialchars((string)$urun["fiyat"], ENT_QUOTES, "UTF-8") ?>"
                           <?= in_array((int)$urun["id"], $bagliUrunler, true) ? "checked" : "" ?>>

                    <label for="urun_<?= (int)$urun["id"] ?>">
                        <span><?= htmlspecialchars($urun["urun_adi"], ENT_QUOTES, "UTF-8") ?></span>
                        <span><?= number_format((float)$urun["fiyat"], 2, ",", ".") ?> TL</span>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="toplam">
            Seçilen Ürünlerin Toplamı: <span id="toplam">0,00 TL</span>
        </p>

        <label for="menu_fiyati">Menü Fiyatı (TL)</label>
        <input type="number" id="menu_fiyati" name="menu_fiyati"
               min="0" step="0.01" required
               value="<?= htmlspecialchars((string)$menu["menu_fiyati"], ENT_QUOTES, "UTF-8") ?>">

        <label for="indirim_orani">İndirim Oranı (%)</label>
        <input type="number" id="indirim_orani" name="indirim_orani"
               min="0" max="100" step="0.01" required
               value="<?= htmlspecialchars((string)$menu["indirim_orani"], ENT_QUOTES, "UTF-8") ?>">

        <label for="indirim_baslangic">İndirim Başlangıcı</label>
        <input type="datetime-local" id="indirim_baslangic"
               name="indirim_baslangic"
               value="<?= htmlspecialchars(tarihInputaCevir($menu["indirim_baslangic"]), ENT_QUOTES, "UTF-8") ?>">

        <label for="indirim_bitis">İndirim Bitişi</label>
        <input type="datetime-local" id="indirim_bitis"
               name="indirim_bitis"
               value="<?= htmlspecialchars(tarihInputaCevir($menu["indirim_bitis"]), ENT_QUOTES, "UTF-8") ?>">

        <div>
            <button type="submit">Değişiklikleri Kaydet</button>
            <a href="panel.php" class="geri">Panele Dön</a>
        </div>
    </form>
</div>

<script>
    const secimler = document.querySelectorAll(".urun-sec");
    const toplamAlan = document.getElementById("toplam");

    function toplamHesapla() {
        let toplam = 0;

        secimler.forEach(function(secim) {
            if (secim.checked) {
                toplam += Number(secim.dataset.fiyat);
            }
        });

        toplamAlan.textContent = toplam.toLocaleString("tr-TR", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + " TL";
    }

    secimler.forEach(function(secim) {
        secim.addEventListener("change", toplamHesapla);
    });

    toplamHesapla();
</script>
</body>
</html>
