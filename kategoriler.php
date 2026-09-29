<?php
/**
 * Kategori Yönetimi 
 */

require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php?error=unauthorized');
    exit();
}

$error = '';
$success = '';
$db = Database::getInstance();

// KATEGORİ EKLEME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $kategori_adi = clean($_POST['kategori_adi'] ?? '');
    $kategori_aciklama = clean($_POST['kategori_aciklama'] ?? '');
    $sira_no = intval($_POST['sira_no'] ?? 999);

    if (empty($kategori_adi)) {
        $error = 'Kategori adı zorunludur!';
    } else {
        // Slug oluştur
        $slug = strtolower($kategori_adi);
        $slug = str_replace(
            ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç', ' '],
            ['i', 'g', 'u', 's', 'o', 'c', 'i', 'g', 'u', 's', 'o', 'c', '-'],
            $slug
        );
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        // Slug benzersiz mi kontrol et
        $check = $db->fetchOne("SELECT COUNT(*) as total FROM kategoriler WHERE kategori_slug = ?", [$slug]);
        if ($check['total'] > 0) {
            $slug = $slug . '-' . time();
        }

        try {
            // INSERT sorgusu - query() metodunu kullan
            $sql = "INSERT INTO kategoriler (kategori_adi, kategori_aciklama, kategori_slug, sira_no, aktif) 
                    VALUES (?, ?, ?, ?, 1)";

            $result = $db->query($sql, [$kategori_adi, $kategori_aciklama, $slug, $sira_no]);

            if ($result) {
                header('Location: kategoriler.php?success=added');
                exit();
            } else {
                $error = 'Kategori eklenirken hata oluştu!';
            }
        } catch (Exception $e) {
            $error = 'Hata: ' . $e->getMessage();
        }
    }
}

// KATEGORİ GÜNCELLEME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $kategori_id = intval($_POST['kategori_id'] ?? 0);
    $kategori_adi = clean($_POST['kategori_adi'] ?? '');
    $kategori_aciklama = clean($_POST['kategori_aciklama'] ?? '');
    $sira_no = intval($_POST['sira_no'] ?? 999);
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    if (empty($kategori_adi)) {
        $error = 'Kategori adı zorunludur!';
    } elseif ($kategori_id <= 0) {
        $error = 'Geçersiz kategori ID!';
    } else {
        try {
            // UPDATE sorgusu - query() metodunu kullan
            $sql = "UPDATE kategoriler SET 
                    kategori_adi = ?, 
                    kategori_aciklama = ?, 
                    sira_no = ?, 
                    aktif = ?
                    WHERE kategori_id = ?";

            $result = $db->query($sql, [
                $kategori_adi,
                $kategori_aciklama,
                $sira_no,
                $aktif,
                $kategori_id
            ]);

            if ($result) {
                header('Location: kategoriler.php?success=updated');
                exit();
            } else {
                $error = 'Kategori güncellenirken hata oluştu!';
            }
        } catch (Exception $e) {
            $error = 'Hata: ' . $e->getMessage();
        }
    }
}

// KATEGORİ SİLME
if (isset($_GET['delete'])) {
    $kategori_id = intval($_GET['delete']);

    // Bu kategoride ürün var mı kontrol et
    $check = $db->fetchOne("SELECT COUNT(*) as total FROM urunler WHERE kategori_id = ?", [$kategori_id]);

    if ($check['total'] > 0) {
        $error = 'Bu kategoride ' . $check['total'] . ' ürün var! Önce ürünleri başka kategoriye taşıyın.';
    } else {
        try {
            $result = $db->query("DELETE FROM kategoriler WHERE kategori_id = ?", [$kategori_id]);
            if ($result) {
                header('Location: kategoriler.php?success=deleted');
                exit();
            }
        } catch (Exception $e) {
            $error = 'Kategori silinemedi: ' . $e->getMessage();
        }
    }
}

// KATEGORİLERİ LİSTELE
$kategoriler = $db->fetchAll("
    SELECT k.*, 
           (SELECT COUNT(*) FROM urunler WHERE kategori_id = k.kategori_id AND aktif = 1) as urun_sayisi
    FROM kategoriler k
    ORDER BY k.sira_no, k.kategori_adi
");

// İstatistikler
$stats = [
    'toplam' => count($kategoriler),
    'aktif' => 0,
    'pasif' => 0
];
foreach ($kategoriler as $kat) {
    if ($kat['aktif'])
        $stats['aktif']++;
    else
        $stats['pasif']++;
}

// Success mesajları
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $success = 'Kategori başarıyla eklendi!';
            break;
        case 'updated':
            $success = 'Kategori başarıyla güncellendi!';
            break;
        case 'deleted':
            $success = 'Kategori başarıyla silindi!';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Yönetimi - <?php echo SITE_NAME; ?></title>
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
        }

        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Navigation */
        .nav {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 0 20px;
            overflow-x: auto;
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

        /* Stats */
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
        }

        .stat-card.success {
            border-left-color: #4caf50;
        }

        .stat-card.danger {
            border-left-color: #f44336;
        }

        .stat-card h3 {
            color: #666;
            font-size: 13px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: 800;
            color: #333;
        }

        /* Alert */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }

        .alert-error {
            background: #f8d7da;
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

        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            font-size: 13px;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
            border-radius: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th {
            background: #f8f9fa;
            padding: 15px 12px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #555;
            font-size: 13px;
            text-transform: uppercase;
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
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .action-btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
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
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            margin: 20px;
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
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #141E30;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
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
            <a href="kategoriler.php" class="active">📂 Kategoriler</a>
            <a href="stok.php">📊 Stok</a>
            <a href="../index.php" target="_blank">🌐 Siteye Git</a>
        </div>
    </div>

    <div class="container">

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>📂 Toplam Kategori</h3>
                <div class="value"><?php echo $stats['toplam']; ?></div>
            </div>
            <div class="stat-card success">
                <h3>✅ Aktif</h3>
                <div class="value"><?php echo $stats['aktif']; ?></div>
            </div>
            <div class="stat-card danger">
                <h3>❌ Pasif</h3>
                <div class="value"><?php echo $stats['pasif']; ?></div>
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
                <h2>📂 Kategori Listesi</h2>
                <button class="btn btn-primary" onclick="openAddModal()">
                    ➕ Yeni Kategori Ekle
                </button>
            </div>

            <!-- Table -->
            <?php if (count($kategoriler) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Sıra</th>
                                <th>Kategori Adı</th>
                                <th>Slug</th>
                                <th>Açıklama</th>
                                <th>Ürün Sayısı</th>
                                <th>Durum</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kategoriler as $kat): ?>
                                <tr>
                                    <td><strong><?php echo $kat['sira_no']; ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($kat['kategori_adi']); ?></strong></td>
                                    <td><code
                                            style="background: #f0f0f0; padding: 4px 8px; border-radius: 4px;"><?php echo htmlspecialchars($kat['kategori_slug'] ?? ''); ?></code>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($kat['kategori_aciklama'] ?? '', 0, 50)); ?></td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $kat['urun_sayisi']; ?> ürün</span>
                                    </td>
                                    <td>
                                        <?php if ($kat['aktif']): ?>
                                            <span class="badge badge-success">✅ Aktif</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">❌ Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn btn-success"
                                                onclick='openEditModal(<?php echo json_encode($kat, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                ✏️ Düzenle
                                            </button>
                                            <?php if ($kat['urun_sayisi'] == 0): ?>
                                                <a href="?delete=<?php echo $kat['kategori_id']; ?>" class="btn btn-danger"
                                                    onclick="return confirm('Bu kategoriyi silmek istediğinize emin misiniz?')">
                                                    🗑️ Sil
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; color: #999;">
                    <div style="font-size: 60px; margin-bottom: 20px;">📂</div>
                    <h3>Kategori Bulunamadı</h3>
                    <p>Henüz kategori eklenmemiş.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">➕ Yeni Kategori Ekle</h3>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="categoryForm">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="kategori_id" id="kategori_id">

                    <div class="form-group">
                        <label>Kategori Adı <span style="color:red;">*</span></label>
                        <input type="text" name="kategori_adi" id="kategori_adi" required
                            placeholder="Örn: Bluetooth Kulaklık">
                    </div>

                    <div class="form-group">
                        <label>Açıklama</label>
                        <textarea name="kategori_aciklama" id="kategori_aciklama"
                            placeholder="Kategori açıklaması..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Sıra No</label>
                        <input type="number" name="sira_no" id="sira_no" value="999" min="0">
                        <small style="color: #999;">Düşük numara üstte görünür</small>
                    </div>

                    <div class="form-group" id="aktifGroup" style="display: none;">
                        <label class="checkbox-group">
                            <input type="checkbox" name="aktif" id="aktif" value="1" checked>
                            <span>Aktif</span>
                        </label>
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
        function openAddModal() {
            document.getElementById('modalTitle').textContent = '➕ Yeni Kategori Ekle';
            document.getElementById('formAction').value = 'add';
            document.getElementById('categoryForm').reset();
            document.getElementById('kategori_id').value = '';
            document.getElementById('aktifGroup').style.display = 'none';
            document.getElementById('categoryModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function openEditModal(kategori) {
            console.log('Düzenleme:', kategori); // Debug için

            document.getElementById('modalTitle').textContent = '✏️ Kategori Düzenle';
            document.getElementById('formAction').value = 'update';

            document.getElementById('kategori_id').value = kategori.kategori_id;
            document.getElementById('kategori_adi').value = kategori.kategori_adi;
            document.getElementById('kategori_aciklama').value = kategori.kategori_aciklama || '';
            document.getElementById('sira_no').value = kategori.sira_no;
            document.getElementById('aktif').checked = kategori.aktif == 1;
            document.getElementById('aktifGroup').style.display = 'block';

            document.getElementById('categoryModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('categoryModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Modal dışına tıklayınca kapat
        document.getElementById('categoryModal').addEventListener('click', function (e) {
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