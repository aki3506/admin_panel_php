<?php
/**
 * Modern Stok Yönetimi 
 */

require_once __DIR__ . '/../config.php';
// Not: Hata görüntüleme config.php içindeki APP_DEBUG (.env) ayarına göre yönetilir.

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php?error=unauthorized');
    exit();
}

$error = '';
$success = '';

// STOK GÜNCELLEME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    $barkod_no = clean($_POST['barkod_no'] ?? '');
    $hareket_tipi = clean($_POST['hareket_tipi'] ?? '');
    $miktar = intval($_POST['miktar'] ?? 0);
    $aciklama = clean($_POST['aciklama'] ?? '');

    if (empty($barkod_no) || empty($hareket_tipi) || $miktar <= 0) {
        $error = 'Tüm alanları doldurun ve miktar 0\'dan büyük olmalı!';
    } else {
        try {
            $urun = $db->fetchOne("SELECT * FROM urunler WHERE barkod_no = ?", [$barkod_no]);

            if (!$urun) {
                $error = 'Ürün bulunamadı!';
            } else {
                $urun_id = $urun['urun_id'];
                $onceki_stok = $urun['stok_miktari'];
                $yeni_stok = $onceki_stok;

                switch ($hareket_tipi) {
                    case 'Giriş':
                        $yeni_stok = $onceki_stok + $miktar;
                        break;
                    case 'Çıkış':
                        $yeni_stok = $onceki_stok - $miktar;
                        if ($yeni_stok < 0) {
                            $error = 'Stok eksi olamaz! Mevcut stok: ' . $onceki_stok;
                        }
                        break;
                    case 'İade':
                        $yeni_stok = $onceki_stok + $miktar;
                        break;
                    case 'Sayım':
                        $yeni_stok = $miktar;
                        break;
                }

                if (empty($error)) {
                    $db->update('urunler', [
                        'stok_miktari' => $yeni_stok
                    ], 'urun_id = ?', [$urun_id]);

                    $db->insert('stok_hareketleri', [
                        'barkod_no' => $barkod_no,
                        'hareket_tipi' => $hareket_tipi,
                        'miktar' => $miktar,
                        'onceki_stok' => $onceki_stok,
                        'yeni_stok' => $yeni_stok,
                        'aciklama' => $aciklama,
                        'admin_id' => $_SESSION['admin_id']
                    ]);

                    header('Location: stok.php?success=updated');
                    exit();
                }
            }
        } catch (Exception $e) {
            $error = 'Stok güncellenirken hata: ' . $e->getMessage();
        }
    }
}

// FİLTRELEME
$filter = clean($_GET['filter'] ?? 'all');
$search = clean($_GET['search'] ?? '');

// Ürünleri listele 
$sql = "SELECT u.*, k.kategori_adi,
        CASE 
            WHEN u.stok_miktari = 0 THEN 'Tükendi'
            WHEN u.stok_miktari <= u.kritik_stok_seviyesi THEN 'Kritik'
            ELSE 'Normal'
        END as stok_durumu
        FROM urunler u
        LEFT JOIN kategoriler k ON u.kategori_id = k.kategori_id
        WHERE 1=1";

$params = [];

if ($filter === 'kritik') {
    $sql .= " AND u.stok_miktari <= u.kritik_stok_seviyesi AND u.stok_miktari > 0";
} elseif ($filter === 'tukendi') {
    $sql .= " AND u.stok_miktari = 0";
} elseif ($filter === 'normal') {
    $sql .= " AND u.stok_miktari > u.kritik_stok_seviyesi";
}

if (!empty($search)) {
    $sql .= " AND (u.urun_adi LIKE ? OR u.barkod_no LIKE ? OR u.marka LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$sql .= " ORDER BY 
          CASE 
              WHEN u.stok_miktari = 0 THEN 1
              WHEN u.stok_miktari <= u.kritik_stok_seviyesi THEN 2
              ELSE 3
          END,
          u.stok_miktari ASC";

$urunler = $db->fetchAll($sql, $params);

// İstatistikler
$stats = [
    'toplam_urun' => count($urunler),
    'kritik_stok' => 0,
    'tukenen' => 0,
    'normal' => 0,
    'toplam_stok_degeri' => 0
];

foreach ($urunler as $urun) {
    if ($urun['stok_durumu'] === 'Tükendi') {
        $stats['tukenen']++;
    } elseif ($urun['stok_durumu'] === 'Kritik') {
        $stats['kritik_stok']++;
    } else {
        $stats['normal']++;
    }
    $stats['toplam_stok_degeri'] += $urun['alis_fiyati'] * $urun['stok_miktari'];
}

// Son 10 stok hareketi
$son_hareketler = $db->fetchAll("
    SELECT sh.*, u.urun_adi, u.barkod_no, a.ad_soyad as admin_adi
    FROM stok_hareketleri sh
    LEFT JOIN urunler u ON sh.barkod_no = u.barkod_no
    LEFT JOIN admin_kullanicilar a ON sh.admin_id = a.admin_id
    ORDER BY sh.hareket_tarihi DESC
    LIMIT 10
");

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'updated':
            $success = 'Stok başarıyla güncellendi!';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Yönetimi - <?php echo SITE_NAME; ?></title>
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

        /* Header */
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

        /* Navigation */
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
            max-width: 1600px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .stat-card.success {
            border-left-color: #28a745;
        }

        .stat-card.warning {
            border-left-color: #ffc107;
        }

        .stat-card.danger {
            border-left-color: #dc3545;
        }

        .stat-card.info {
            border-left-color: #17a2b8;
        }

        .stat-card h3 {
            color: #666;
            font-size: 12px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 800;
            color: #333;
        }

        /* Alert */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            animation: slideDown 0.3s;
            border-left: 4px solid;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left-color: #28a745;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left-color: #dc3545;
        }

        /* Card */
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 30px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #141E30;
            flex-wrap: wrap;
            gap: 15px;
        }

        .card-header h2 {
            color: #333;
            font-size: 22px;
            font-weight: 700;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #141E30 0%, #35577D 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(20, 30, 48, 0.3);
        }

        .btn-success {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            font-size: 13px;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
            padding: 8px 16px;
            font-size: 13px;
        }

        /* Filters */
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            flex: 1;
        }

        .filter-tab {
            padding: 10px 20px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .filter-tab:hover {
            border-color: #141E30;
            color: #141E30;
            transform: translateY(-2px);
        }

        .filter-tab.active {
            background: linear-gradient(135deg, #141E30 0%, #35577D 100%);
            border-color: transparent;
            color: white;
            box-shadow: 0 5px 15px rgba(20, 30, 48, 0.3);
        }

        .search-box {
            flex: 1;
            min-width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #141E30;
            box-shadow: 0 0 0 4px rgba(20, 30, 48, 0.1);
        }

        /* Table */
        .table-container {
            overflow-x: auto;
            border-radius: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 15px 10px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #555;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 15px 10px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        tr:hover {
            background: linear-gradient(135deg, #f8f9fa 0%, #f1f3f5 100%);
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

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            overflow-y: auto;
            backdrop-filter: blur(5px);
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            margin: 20px;
            animation: slideUp 0.3s;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 25px;
            border-bottom: 2px solid #141E30;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            color: #333;
            font-size: 22px;
            font-weight: 700;
        }

        .modal-close {
            background: #dc3545;
            color: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            transition: all 0.3s;
        }

        .modal-close:hover {
            background: #c82333;
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #141E30;
            box-shadow: 0 0 0 4px rgba(20, 30, 48, 0.1);
        }

        .info-box {
            background: linear-gradient(135deg, #e3f2ff 0%, #d1e7ff 100%);
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #141E30;
            margin-bottom: 20px;
        }

        .info-box strong {
            color: #141E30;
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

            .filters {
                flex-direction: column;
            }

            .search-box {
                width: 100%;
            }

            .stat-card .value {
                font-size: 28px;
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
            <a href="dashboard.php">📊 Dashboard</a>
            <a href="urunler.php">📦 Ürünler</a>
            <a href="kategoriler.php">📂 Kategoriler</a>
            <a href="stok.php" class="active">📊 Stok</a>
            <a href="../index.php" target="_blank">🌐 Siteye Git</a>
        </div>
    </div>

    <div class="container">

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>📦 Toplam Ürün</h3>
                <div class="value"><?php echo $stats['toplam_urun']; ?></div>
            </div>
            <div class="stat-card success">
                <h3>✅ Normal Stok</h3>
                <div class="value"><?php echo $stats['normal']; ?></div>
            </div>
            <div class="stat-card warning">
                <h3>⚠️ Kritik Stok</h3>
                <div class="value"><?php echo $stats['kritik_stok']; ?></div>
            </div>
            <div class="stat-card danger">
                <h3>❌ Tükenen</h3>
                <div class="value"><?php echo $stats['tukenen']; ?></div>
            </div>
            <div class="stat-card info">
                <h3>💰 Stok Değeri</h3>
                <div class="value" style="font-size: 20px;">
                    <?php echo number_format($stats['toplam_stok_degeri'], 0, ',', '.'); ?> ₺
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success">✔ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">⚠ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Card -->
        <div class="card">
            <div class="card-header">
                <h2>📊 Stok Durumu</h2>
                <button class="btn btn-primary" onclick="openStockModal()">
                    📝 Stok Hareketi Ekle
                </button>
            </div>

            <!-- Filters -->
            <div class="filters">
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        🏠 Tümü (<?php echo $stats['toplam_urun']; ?>)
                    </a>
                    <a href="?filter=kritik" class="filter-tab <?php echo $filter === 'kritik' ? 'active' : ''; ?>">
                        ⚠️ Kritik (<?php echo $stats['kritik_stok']; ?>)
                    </a>
                    <a href="?filter=tukendi" class="filter-tab <?php echo $filter === 'tukendi' ? 'active' : ''; ?>">
                        ❌ Tükendi (<?php echo $stats['tukenen']; ?>)
                    </a>
                    <a href="?filter=normal" class="filter-tab <?php echo $filter === 'normal' ? 'active' : ''; ?>">
                        ✅ Normal (<?php echo $stats['normal']; ?>)
                    </a>
                </div>

                <form method="GET" class="search-box">
                    <?php if ($filter !== 'all'): ?>
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <?php endif; ?>
                    <input type="text" name="search" placeholder="🔍 Ürün, barkod veya marka ara..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </form>
            </div>

            <!-- Table -->
            <?php if (count($urunler) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Barkod</th>
                                <th>Ürün Adı</th>
                                <th>Kategori</th>
                                <th>Mevcut Stok</th>
                                <th>Kritik Seviye</th>
                                <th>Durum</th>
                                <th>Fiyat</th>
                                <th>Stok Değeri</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($urunler as $urun):
                                $stok_degeri = $urun['alis_fiyati'] * $urun['stok_miktari'];
                                // Para birimi formatı - varsayılan USD
                                $para_sembol = strtoupper($urun['para_birimi'] ?? 'USD');
                                if ($para_sembol == 'USD')
                                    $para_sembol = '$';
                                elseif ($para_sembol == 'EUR')
                                    $para_sembol = '€';
                                elseif ($para_sembol == 'TL' || $para_sembol == 'TRY')
                                    $para_sembol = '₺';

                                // Fiyatlar - nokta ile format (17.00)
                                // Basit USD gösterimi
                                $satis_fiyat = formatPriceAdmin($urun['satis_fiyati'], 'USD');
                                $alis_fiyat = formatPriceAdmin($urun['alis_fiyati'], 'USD');
                                $stok_deger = number_format($stok_degeri, 2, '.', '');
                                ?>
                                <tr>
                                    <td><code
                                            style="background: #f0f0f0; padding: 4px 8px; border-radius: 4px;"><?php echo htmlspecialchars($urun['barkod_no']); ?></code>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($urun['urun_adi']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($urun['kategori_adi'] ?? '-'); ?></td>
                                    <td><strong style="font-size: 16px;"><?php echo $urun['stok_miktari']; ?></strong></td>
                                    <td><?php echo $urun['kritik_stok_seviyesi']; ?></td>
                                    <td>
                                        <?php if ($urun['stok_durumu'] === 'Tükendi'): ?>
                                            <span class="badge badge-danger">❌ Tükendi</span>
                                        <?php elseif ($urun['stok_durumu'] === 'Kritik'): ?>
                                            <span class="badge badge-warning">⚠️ Kritik</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">✅ Normal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="color:#27ae60; font-weight:700; font-size:15px;">
                                            <?php echo $satis_fiyat; ?>         <?php echo $para_sembol; ?>
                                        </div>
                                        <small style="color:#7f8c8d; font-size:12px;">
                                            Alış: <?php echo $alis_fiyat; ?>         <?php echo $para_sembol; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong style="color:#27ae60; font-size:16px;">
                                            <?php echo $stok_deger; ?>         <?php echo $para_sembol; ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <button class="btn btn-success btn-sm"
                                            onclick='openStockModalWithProduct(<?php echo json_encode($urun, JSON_HEX_APOS); ?>)'>
                                            📝 Hareket
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; color: #999;">
                    <div style="font-size: 64px; margin-bottom: 20px;">📦</div>
                    <h3>Ürün Bulunamadı</h3>
                    <p>Filtreye uygun ürün bulunamadı.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Son Hareketler -->
        <?php if (count($son_hareketler) > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h2>📜 Son Stok Hareketleri</h2>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Ürün</th>
                                <th>Hareket Tipi</th>
                                <th>Miktar</th>
                                <th>Önceki</th>
                                <th>Yeni</th>
                                <th>Açıklama</th>
                                <th>Kullanıcı</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($son_hareketler as $hareket): ?>
                                <tr>
                                    <td><?php echo date('d.m.Y H:i', strtotime($hareket['hareket_tarihi'])); ?></td>
                                    <td><?php echo htmlspecialchars($hareket['urun_adi'] ?? 'Bilinmiyor'); ?></td>
                                    <td>
                                        <?php
                                        $badges = [
                                            'Giriş' => 'success',
                                            'Çıkış' => 'warning',
                                            'İade' => 'info',
                                            'Sayım' => 'info'
                                        ];
                                        $badge_class = 'badge-' . ($badges[$hareket['hareket_tipi']] ?? 'info');
                                        ?>
                                        <span
                                            class="badge <?php echo $badge_class; ?>"><?php echo $hareket['hareket_tipi']; ?></span>
                                    </td>
                                    <td><strong><?php echo $hareket['miktar']; ?></strong></td>
                                    <td><?php echo $hareket['onceki_stok']; ?></td>
                                    <td><strong><?php echo $hareket['yeni_stok']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($hareket['aciklama'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($hareket['admin_adi'] ?? 'Admin'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Stock Modal -->
    <div id="stockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📝 Stok Hareketi Ekle</h3>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="stockForm">
                    <input type="hidden" name="action" value="update_stock">

                    <div class="form-group">
                        <label>Barkod No <span style="color:red;">*</span></label>
                        <input type="text" name="barkod_no" id="barkod_no" required placeholder="Barkod numarası girin">
                    </div>

                    <div id="urunBilgi" class="info-box" style="display: none;">
                        <strong>Ürün:</strong> <span id="urunAdi"></span><br>
                        <strong>Mevcut Stok:</strong> <span id="mevcutStok"></span>
                    </div>

                    <div class="form-group">
                        <label>Hareket Tipi <span style="color:red;">*</span></label>
                        <select name="hareket_tipi" id="hareket_tipi" required>
                            <option value="">Seçiniz</option>
                            <option value="Giriş">➕ Giriş (Stok Artışı)</option>
                            <option value="Çıkış">➖ Çıkış (Stok Azalışı)</option>
                            <option value="İade">🔄 İade</option>
                            <option value="Sayım">📊 Sayım (Düzeltme)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Miktar <span style="color:red;">*</span></label>
                        <input type="number" name="miktar" id="miktar" required min="1" value="1">
                        <small style="color: #999;">Sayım seçiliyse yeni stok miktarını girin</small>
                    </div>

                    <div class="form-group">
                        <label>Açıklama</label>
                        <textarea name="aciklama" id="aciklama" placeholder="Stok hareketi açıklaması..."></textarea>
                    </div>

                    <div style="margin-top: 25px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" class="btn btn-warning" onclick="closeModal()">İptal</button>
                        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openStockModal() {
            document.getElementById('stockForm').reset();
            document.getElementById('urunBilgi').style.display = 'none';
            document.getElementById('stockModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function openStockModalWithProduct(urun) {
            document.getElementById('barkod_no').value = urun.barkod_no;
            document.getElementById('urunAdi').textContent = urun.urun_adi;
            document.getElementById('mevcutStok').textContent = urun.stok_miktari + ' adet';
            document.getElementById('urunBilgi').style.display = 'block';
            document.getElementById('stockModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('stockModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Modal dışına tıklayınca kapat
        document.getElementById('stockModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // ESC tuşu ile kapat
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>

</html>