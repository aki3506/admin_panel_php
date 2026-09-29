<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Config dosyasını dahil et - doğru yol
require_once __DIR__ . '/../config.php';
// Not: Hata görüntüleme config.php içindeki APP_DEBUG (.env) ayarına göre yönetilir.

// Veritabanı bağlantısını test et
try {
    $toplam_urun = $db->fetchOne("SELECT COUNT(*) as total FROM urunler WHERE aktif = 1")['total'] ?? 0;
    $toplam_kategori = $db->fetchOne("SELECT COUNT(*) as total FROM kategoriler WHERE aktif = 1")['total'] ?? 0;
    $stokta_yok = $db->fetchOne("SELECT COUNT(*) as total FROM urunler WHERE aktif = 1 AND stok_miktari = 0")['total'] ?? 0;
    $kritik_stok = $db->fetchOne("SELECT COUNT(*) as total FROM urunler WHERE aktif = 1 AND stok_miktari > 0 AND stok_miktari <= kritik_stok_seviyesi")['total'] ?? 0;

    // Son eklenen ürünler - ADMIN URUNLER.PHP İLE AYNI
    $son_urunler = $db->fetchAll("
        SELECT u.*, k.kategori_adi
        FROM urunler u
        LEFT JOIN kategoriler k ON u.kategori_id = k.kategori_id
        WHERE 1=1
        ORDER BY u.olusturma_tarihi DESC
        LIMIT 5
    ");

    // DEBUG: Ürün sayısını kontrol et
    if (empty($son_urunler)) {
        error_log("DASHBOARD: Ürün bulunamadı!");
        // Veritabanında gerçekten ürün var mı?
        $urun_kontrolu = $db->fetchAll("SELECT urun_id, urun_adi, aktif FROM urunler LIMIT 5");
        error_log("DASHBOARD: Toplam ürün kontrolü: " . print_r($urun_kontrolu, true));
    } else {
        error_log("DASHBOARD: " . count($son_urunler) . " ürün bulundu.");
    }

} catch (Exception $e) {
    error_log("DASHBOARD HATA: " . $e->getMessage());
    die("Veritabanı hatası: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
        }

        /* Header - Modern Gradient */
        .header {
            background: linear-gradient(135deg, #141E30 0%, #35577D 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-logout {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        /* Navigation - Responsive */
        .nav {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 0 20px;
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .nav-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            gap: 30px;
        }

        .nav a {
            display: block;
            padding: 15px 0;
            color: #666;
            text-decoration: none;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 600;
        }

        .nav a:hover {
            color: #141E30;
        }

        .nav a.active {
            color: #141E30;
            border-bottom-color: #141E30;
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Stats Grid - Modern Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            border-left: 5px solid #141E30;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(135deg, rgba(20, 30, 48, 0.03) 0%, transparent 100%);
            pointer-events: none;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .stat-card.warning {
            border-left-color: #ff9800;
        }

        .stat-card.danger {
            border-left-color: #f44336;
        }

        .stat-card.success {
            border-left-color: #4caf50;
        }

        .stat-card h3 {
            color: #666;
            font-size: 13px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: 800;
            color: #333;
            position: relative;
            z-index: 1;
        }

        /* Quick Actions - Gradient Cards */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: white;
            padding: 30px 20px;
            border-radius: 15px;
            text-decoration: none;
            color: #333;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .action-btn:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(20, 30, 48, 0.15);
            border-color: #141E30;
        }

        .action-btn .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .action-btn div:last-child {
            font-weight: 600;
            font-size: 15px;
        }

        /* Card - Modern Design */
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 40px;
            overflow-x: auto;
        }

        .card h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #141E30;
            font-size: 20px;
            font-weight: 700;
        }

        /* Table - Responsive */
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #555;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 15px 12px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state .icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .user-info {
                width: 100%;
                justify-content: space-between;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }

            .stat-card .value {
                font-size: 28px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 15px;
            }

            .header h1 {
                font-size: 18px;
            }

            .action-btn {
                padding: 20px 15px;
            }

            .action-btn .icon {
                font-size: 36px;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="header-content">
            <h1>🏢 <?php echo SITE_NAME; ?> - Yönetim Paneli</h1>
            <div class="user-info">
                <span>👤 <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
                <a href="logout.php" class="btn-logout">Çıkış</a>
            </div>
        </div>
    </div>

    <div class="nav">
        <div class="nav-content">
            <a href="dashboard.php" class="active">📊 Dashboard</a>
            <a href="urunler.php">📦 Ürünler</a>
            <a href="kategoriler.php">📂 Kategoriler</a>
            <a href="stok.php">📊 Stok</a>
            <a href="../index.php" target="_blank">🌐 Siteye Git</a>
        </div>
    </div>

    <div class="container">

        <div class="stats-grid">
            <div class="stat-card">
                <h3>📦 Toplam Ürün</h3>
                <div class="value"><?php echo $toplam_urun; ?></div>
            </div>
            <div class="stat-card success">
                <h3>📂 Kategoriler</h3>
                <div class="value"><?php echo $toplam_kategori; ?></div>
            </div>
            <div class="stat-card warning">
                <h3>⚠️ Kritik Stok</h3>
                <div class="value"><?php echo $kritik_stok; ?></div>
            </div>
            <div class="stat-card danger">
                <h3>❌ Stokta Yok</h3>
                <div class="value"><?php echo $stokta_yok; ?></div>
            </div>
        </div>

        <div class="quick-actions">
            <a href="urunler.php" class="action-btn">
                <div class="icon">➕</div>
                <div>Ürün Ekle</div>
            </a>
            <a href="stok.php" class="action-btn">
                <div class="icon">📊</div>
                <div>Stok Yönet</div>
            </a>
            <a href="kategoriler.php" class="action-btn">
                <div class="icon">📂</div>
                <div>Kategoriler</div>
            </a>
            <a href="../index.php" target="_blank" class="action-btn">
                <div class="icon">🌐</div>
                <div>Siteyi Gör</div>
            </a>
        </div>

        <div class="card">
            <h2>📦 Son Eklenen Ürünler</h2>
            <?php if (count($son_urunler) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Ürün Adı</th>
                            <th>Kategori</th>
                            <th>Fiyat</th>
                            <th>Stok</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($son_urunler as $urun):
                            // Para birimi sembolü - varsayılan USD
                            $para_sembol = strtoupper($urun['para_birimi'] ?? 'USD');
                            if ($para_sembol == 'USD')
                                $para_sembol = '$';
                            elseif ($para_sembol == 'EUR')
                                $para_sembol = '€';
                            elseif ($para_sembol == 'TL' || $para_sembol == 'TRY')
                                $para_sembol = '₺';

                            // Fiyatlar - nokta ile format (17.00)
                            $alis_fiyat = number_format($urun['alis_fiyati'], 2, '.', '');
                            $satis_fiyat = number_format($urun['satis_fiyati'], 2, '.', '');
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($urun['urun_adi']); ?></strong></td>
                                <td><?php echo htmlspecialchars($urun['kategori_adi'] ?? '-'); ?></td>
                                <td>
                                    <div style="color:#27ae60; font-weight:700; font-size:15px;">
                                        <?php echo $satis_fiyat; ?>         <?php echo $para_sembol; ?>
                                    </div>
                                    <small style="color:#7f8c8d; font-size:12px;">
                                        Alış: <?php echo $alis_fiyat; ?>         <?php echo $para_sembol; ?>
                                    </small>
                                </td>
                                <td><?php echo $urun['stok_miktari']; ?></td>
                                <td>
                                    <?php if ($urun['stok_miktari'] == 0): ?>
                                        <span class="badge badge-danger">Yok</span>
                                    <?php elseif ($urun['stok_miktari'] <= $urun['kritik_stok_seviyesi']): ?>
                                        <span class="badge badge-warning">Kritik</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Stokta</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icon">📦</div>
                    <h3>Henüz Ürün Eklenmemiş</h3>
                    <p>İlk ürününüzü eklemek için "Ürün Ekle" butonuna tıklayın.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</body>

</html>