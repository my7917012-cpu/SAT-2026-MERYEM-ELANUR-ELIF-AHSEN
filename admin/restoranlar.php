
<?php

session_start();

require_once "../config/baglanti.php";

// Sistem yöneticisi girişi kontrolü
if (!isset($_SESSION["admin_id"])) {
    header("Location: sistem_giris.php");
    exit;
}

$mesaj = "";

try {
    $sorgu = $db->prepare("
        SELECT
            id,
            restoran_adi,
            adres,
            telefon,
            aktif,
            olusturma_tarihi
        FROM restoranlar
        ORDER BY id DESC
    ");

    $sorgu->execute();
    $restoranlar = $sorgu->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $mesaj = "Restoranlar listelenirken bir hata oluştu.";
    $restoranlar = [];
}

?>

<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restoranlar</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 25px;
        }

        .kutu {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
        }

        h1 {
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #333;
            color: white;
        }

        tr:nth-child(even) {
            background: #f8f8f8;
        }

        .aktif {
            color: green;
            font-weight: bold;
        }

        .pasif {
            color: #c0392b;
            font-weight: bold;
        }

        .buton {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 15px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .tablo-alani {
            overflow-x: auto;
        }
    </style>
</head>

<body>

<div class="kutu">

    <h1>Kayıtlı Restoranlar</h1>

    <?php if ($mesaj !== ""): ?>

        <p><?= htmlspecialchars($mesaj, ENT_QUOTES, "UTF-8") ?></p>

    <?php elseif (count($restoranlar) > 0): ?>

        <div class="tablo-alani">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Restoran Adı</th>
                        <th>Adres</th>
                        <th>Telefon</th>
                        <th>Durum</th>
                        <th>Kayıt Tarihi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($restoranlar as $restoran): ?>
                        <tr>
                            <td>
                                <?= (int) $restoran["id"] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($restoran["restoran_adi"] ?? "", ENT_QUOTES, "UTF-8") ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($restoran["adres"] ?? "", ENT_QUOTES, "UTF-8") ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($restoran["telefon"] ?? "", ENT_QUOTES, "UTF-8") ?>
                            </td>

                            <td>
                                <?php if ((int) $restoran["aktif"] === 1): ?>
                                    <span class="aktif">Aktif</span>
                                <?php else: ?>
                                    <span class="pasif">Pasif</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($restoran["olusturma_tarihi"] ?? "", ENT_QUOTES, "UTF-8") ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>

        <p>Henüz kayıtlı restoran bulunmuyor.</p>

    <?php endif; ?>

    <a href="sistem_paneli.php" class="buton">Panele Dön</a>

</div>

</body>
</html>
