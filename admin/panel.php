
<?php

session_start();

require_once "../config/baglanti.php";

// Giriş yapılmamışsa giriş sayfasına gönder
if (!isset($_SESSION["admin_id"])) {
    header("Location: giris.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="tr">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Paneli</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }

        .ust-bar {
            background: #b71c1c;
            color: white;
            padding: 18px 30px;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ust-bar h1 {
            margin: 0;
            font-size: 24px;
        }

        .cikis {
            background: white;
            color: #b71c1c;
            padding: 9px 16px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
        }

        .container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .hosgeldin {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;

            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .hosgeldin h2 {
            margin-top: 0;
            color: #333;
        }

        .kartlar {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .kart {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;

            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .kart h3 {
            margin-top: 0;
            color: #b71c1c;
        }

        .kart p {
            color: #555;
            line-height: 1.5;
        }

        .buton {
            display: inline-block;
            margin-top: 15px;

            background: #b71c1c;
            color: white;

            padding: 10px 18px;

            text-decoration: none;
            border-radius: 6px;
        }

        .buton:hover {
            background: #8f1515;
        }

        @media (max-width: 700px) {

            .kartlar {
                grid-template-columns: 1fr;
            }

            .ust-bar {
                flex-direction: column;
                gap: 15px;
            }

        }

    </style>

</head>

<body>

    <div class="ust-bar">

        <h1>Restoran Admin Paneli</h1>

        <a href="cikis.php" class="cikis">
            Çıkış Yap
        </a>

    </div>

    <div class="container">

        <div class="hosgeldin">

            <h2>
                Hoş geldin,
                <?= htmlspecialchars($_SESSION["admin_kullanici"]) ?>!
            </h2>

            <p>
                Restoran yönetim paneline başarıyla giriş yaptınız.
            </p>

        </div>

        <div class="kartlar">

            <div class="kart">

                <h3>Ürünler</h3>

                <p>
                    Restoran ürünlerini ekleyebilir,
                    düzenleyebilir ve silebilirsiniz.
                </p>

                <a href="urunler.php" class="buton">
                    Ürünleri Yönet
                </a>

            </div>


            <div class="kart">

                <h3>Menü</h3>

                <p>
                    Restoran menüsünü görüntüleyebilirsiniz.
                </p>

                <a href="../menu.php" class="buton">
                    Menüyü Gör
                </a>

                <button type="button" class="buton" onclick="menuyuGoster()">
                        Menüyü Gör
                </button>

                <div id="menuAlani" style="display: none; margin-top: 20px; background: white; padding: 15px; border-radius: 10px;">


                      <button type="button" class="buton" onclick="menuyuKapat()">
                             Menüyü Kapat
                              </button>

                                  <iframe
                                      src="../menu.php"
                                      title="Restoran Menüsü"
                                      style="width: 100%; height: 700px; border: none; margin-top: 15px;">
                                 </iframe>


                   </div>

                   <script>
                                function menuyuGoster() {
                                  document.getElementById('menuAlani').style.display = 'block';
                                  document.getElementById('menuAlani').scrollIntoView({ behavior: 'smooth' });
                            }

                                function menuyuKapat() {
                                  document.getElementById('menuAlani').style.display = 'none';
                            }
                    </script>


            </div>


            <div class="kart">

                <h3>Ayarlar</h3>

                <p>
                    Restoran bilgilerini düzenleyebilirsiniz.
                </p>

                <a href="ayarlar.php" class="buton">
                    Ayarları Yönet
                </a>

            </div>

        </div>

    </div>

</body>

</html>


