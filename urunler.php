<?php
/**
 * Ürün Yönetimi - NK Teknoloji Admin Panel
 * Ana Fotoğraf + Diğer Fotoğraflar Ayrı Sistem
 */

require_once __DIR__ . '/../config.php';

// Giriş Kontrolü
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php?error=unauthorized');
    exit();
}

$db = Database::getInstance();
$error = '';
$success = '';

// --- EKLENEN FONKSİYON: SEO URL (SLUG) OLUŞTURMA ---
// Bu fonksiyon ürün adını alır "Ürün Adı" -> "urun-adi" yapar.
function seolink($s)
{
    $tr = array('ş', 'Ş', 'ı', 'I', 'İ', 'ğ', 'Ğ', 'ü', 'Ü', 'ö', 'Ö', 'Ç', 'ç', '(', ')', '/', ' ', ',', '?');
    $eng = array('s', 's', 'i', 'i', 'i', 'g', 'g', 'u', 'u', 'o', 'o', 'c', 'c', '', '', '-', '-', '', '');
    $s = str_replace($tr, $eng, $s);
    $s = strtolower($s);
    $s = preg_replace('/&amp;amp;amp;amp;amp;amp;amp;amp;amp;.+?;/', '', $s);
    $s = preg_replace('/\s+/', '-', $s);
    $s = preg_replace('|-+|', '-', $s);
    $s = preg_replace('/#/', '', $s);
    $s = str_replace('.', '', $s);
    $s = trim($s, '-');
    return $s;
}

// Mesaj Yönetimi
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $success = 'Ürün ve fotoğraflar başarıyla eklendi!';
            break;
        case 'updated':
            $success = 'Ürün başarıyla güncellendi!';
            break;
        case 'deleted':
            $success = 'Ürün silindi!';
            break;
        case 'stock_updated':
            $success = 'Stok güncellendi!';
            break;
    }
}

// Kategorileri Getir
$kategoriler = $db->fetchAll("SELECT * FROM kategoriler WHERE aktif = 1 ORDER BY kategori_adi");

// --- TEK DOSYA YÜKLEME (ANA FOTOĞRAF) ---
function uploadSingleImage($file)
{
    $target_dir = UPLOAD_PATH . 'products/';
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (!in_array($ext, $allowed)) {
        return false;
    }

    $new_name = 'prod_' . uniqid() . '_main.' . $ext;
    $destination = $target_dir . $new_name;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $new_name;
    }

    return false;
}

// --- ÇOKLU DOSYA YÜKLEME (DİĞER FOTOĞRAFLAR) ---
function uploadMultipleImages($files)
{
    $uploaded = [];
    $target_dir = UPLOAD_PATH . 'products/';

    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $total = count($files['name']);

    for ($i = 0; $i < $total; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($ext, $allowed)) {
                $new_name = 'prod_' . uniqid() . '_' . ($i + 1) . '.' . $ext;
                $destination = $target_dir . $new_name;

                if (move_uploaded_file($files['tmp_name'][$i], $destination)) {
                    $uploaded[] = $new_name;
                }
            }
        }
    }

    return $uploaded;
}

// --- İŞLEM: ÜRÜN EKLEME ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $barkod = clean($_POST['barkod_no']);
    $ad = clean($_POST['urun_adi']);
    $kat_id = intval($_POST['kategori_id']);

    if (empty($barkod) || empty($ad)) {
        $error = 'Barkod ve Ürün Adı zorunludur!';
    } else {

        // EKLENEN KISIM: SLUG OLUŞTURMA
        $slug = seolink($ad) . '-' . rand(100, 999);

        try {
            // 1. Ana Fotoğraf Yükle
            $ana_resim = '';
            if (!empty($_FILES['ana_fotograf']['name'])) {
                $ana_resim = uploadSingleImage($_FILES['ana_fotograf']);
            }

            // 2. Diğer Fotoğrafları Yükle
            $diger_resimler = [];
            if (!empty($_FILES['diger_fotograflar']['name'][0])) {
                $diger_resimler = uploadMultipleImages($_FILES['diger_fotograflar']);
            }

            // JSON formatına çevir
            $diger_resimler_json = !empty($diger_resimler) ? json_encode($diger_resimler) : null;

            // 3. Ürünü Ekle
            $result = $db->insert('urunler', [
                'barkod_no' => $_POST['barkod_no'],
                'kategori_id' => $_POST['kategori_id'],
                'urun_adi' => $_POST['urun_adi'],
                'urun_slug' => $slug,
                'urun_aciklama' => $_POST['urun_aciklama'],
                'doviz_alis_fiyati' => floatval(str_replace(',', '.', $_POST['alis_fiyati'] ?? '0')),
                'doviz_satis_fiyati' => floatval(str_replace(',', '.', $_POST['satis_fiyati'] ?? '0')),
                'alis_fiyati' => floatval(str_replace(',', '.', $_POST['alis_fiyati'] ?? '0')),
                'satis_fiyati' => floatval(str_replace(',', '.', $_POST['satis_fiyati'] ?? '0')),
                'fiyat_doviz' => 'USD',
                'para_birimi' => 'USD',
                'stok_miktari' => $_POST['stok_miktari'],
                'kritik_stok_seviyesi' => $_POST['kritik_stok_seviyesi'],
                'marka' => $_POST['marka'],
                'model' => $_POST['model'],
                'ana_resim' => $ana_resim,
                'diger_resimler' => $diger_resimler_json,
                'vitrin_urun' => isset($_POST['vitrin_urun']) ? 1 : 0,
                'yeni_urun' => isset($_POST['yeni_urun']) ? 1 : 0,
                'indirimli' => isset($_POST['indirimli']) ? 1 : 0,
                'aktif' => 1,
                'olusturma_tarihi' => date('Y-m-d H:i:s')
            ]);

            if ($result) {
                header('Location: urunler.php?success=added');
                exit();
            } else {
                $error = 'Veritabanına kayıt yapılamadı!';
            }

        } catch (Exception $e) {
            $error = 'Hata: ' . $e->getMessage();
        }
    }
}

// --- İŞLEM: ÜRÜN GÜNCELLEME ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $urun_id = intval($_POST['urun_id']);

    try {
        // Mevcut ürün bilgilerini çek
        $mevcut = $db->fetchOne("SELECT ana_resim, diger_resimler FROM urunler WHERE urun_id = ?", [$urun_id]);

        $ana_resim = $mevcut['ana_resim'];
        $diger_resimler_array = json_decode($mevcut['diger_resimler'], true) ?: [];

        // Slug güncelle
        $slug = seolink($_POST['urun_adi']);

        // 1. Yeni Ana Fotoğraf Yüklendiyse
        if (!empty($_FILES['ana_fotograf']['name'])) {
            $yeni_ana = uploadSingleImage($_FILES['ana_fotograf']);
            if ($yeni_ana) {
                // Eski ana fotoğrafı sil
                if ($ana_resim && file_exists(UPLOAD_PATH . 'products/' . $ana_resim)) {
                    @unlink(UPLOAD_PATH . 'products/' . $ana_resim);
                }
                $ana_resim = $yeni_ana;
            }
        }

        // 2. Yeni Diğer Fotoğraflar Eklendiyse
        if (!empty($_FILES['diger_fotograflar']['name'][0])) {
            $yeni_diger = uploadMultipleImages($_FILES['diger_fotograflar']);
            $diger_resimler_array = array_merge($diger_resimler_array, $yeni_diger);
        }

        $diger_resimler_json = !empty($diger_resimler_array) ? json_encode($diger_resimler_array) : null;

        // 3. Güncelle
        $db->update('urunler', [
            'barkod_no' => $_POST['barkod_no'],
            'kategori_id' => $_POST['kategori_id'],
            'urun_adi' => $_POST['urun_adi'],
            'urun_slug' => $slug,
            'urun_aciklama' => $_POST['urun_aciklama'],
            'doviz_alis_fiyati' => floatval(str_replace(',', '.', $_POST['alis_fiyati'] ?? '0')),
            'doviz_satis_fiyati' => floatval(str_replace(',', '.', $_POST['satis_fiyati'] ?? '0')),
            'alis_fiyati' => floatval(str_replace(',', '.', $_POST['alis_fiyati'] ?? '0')),
            'satis_fiyati' => floatval(str_replace(',', '.', $_POST['satis_fiyati'] ?? '0')),
            'stok_miktari' => $_POST['stok_miktari'],
            'kritik_stok_seviyesi' => $_POST['kritik_stok_seviyesi'],
            'marka' => $_POST['marka'],
            'model' => $_POST['model'],
            'ana_resim' => $ana_resim,
            'diger_resimler' => $diger_resimler_json,
            'vitrin_urun' => isset($_POST['vitrin_urun']) ? 1 : 0,
            'yeni_urun' => isset($_POST['yeni_urun']) ? 1 : 0,
            'indirimli' => isset($_POST['indirimli']) ? 1 : 0
        ], "urun_id = :id", ['id' => $urun_id]);

        header('Location: urunler.php?success=updated');
        exit();
    } catch (Exception $e) {
        $error = 'Güncelleme Hatası: ' . $e->getMessage();
    }
}

// --- İŞLEM: STOK GÜNCELLEME ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    $urun_id = intval($_POST['urun_id']);
    $yeni_stok = intval($_POST['yeni_stok']);

    try {
        $db->update('urunler', ['stok_miktari' => $yeni_stok], "urun_id = :id", ['id' => $urun_id]);
        header('Location: urunler.php?success=stock_updated');
        exit();
    } catch (Exception $e) {
        $error = 'Stok Hatası: ' . $e->getMessage();
    }
}

// --- İŞLEM: SİLME ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Ürün resimlerini al ve sil
    $urun = $db->fetchOne("SELECT ana_resim, diger_resimler FROM urunler WHERE urun_id = ?", [$id]);

    if ($urun) {
        // Ana resmi sil
        if ($urun['ana_resim'] && file_exists(UPLOAD_PATH . 'products/' . $urun['ana_resim'])) {
            @unlink(UPLOAD_PATH . 'products/' . $urun['ana_resim']);
        }

        // Diğer resimleri sil
        if ($urun['diger_resimler']) {
            $diger = json_decode($urun['diger_resimler'], true);
            if (is_array($diger)) {
                foreach ($diger as $resim) {
                    if (file_exists(UPLOAD_PATH . 'products/' . $resim)) {
                        @unlink(UPLOAD_PATH . 'products/' . $resim);
                    }
                }
            }
        }
    }

    $db->query("DELETE FROM urunler WHERE urun_id = ?", [$id]);
    header('Location: urunler.php?success=deleted');
    exit();
}

// Arama ve Listeleme
$search = isset($_GET['search']) ? clean($_GET['search']) : '';
$sql = "SELECT u.*, k.kategori_adi FROM urunler u LEFT JOIN kategoriler k ON u.kategori_id = k.kategori_id WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (u.urun_adi LIKE ? OR u.barkod_no LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY u.olusturma_tarihi DESC";
$urunler = $db->fetchAll($sql, $params);

?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ürün Yönetimi - <?php echo SITE_NAME; ?></title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header & Butonlar */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 14px;
            display: inline-block;
        }

        .btn-primary {
            background: #141E30;
        }

        .btn-success {
            background: #28a745;
        }

        .btn-danger {
            background: #dc3545;
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-back {
            background: #6c757d;
        }

        /* Kart ve Tablo */
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            padding: 20px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            min-width: 800px;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            color: #333;
            font-weight: 600;
        }

        .thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        /* Rozetler (Badges) */
        .badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
            color: white;
            margin-right: 3px;
            display: inline-block;
            margin-bottom: 2px;
        }

        .bg-vitrin {
            background: #35577D;
        }

        .bg-slider {
            background: #e74c3c;
        }

        .bg-stok {
            background: #27ae60;
        }

        .bg-kritik {
            background: #f1c40f;
            color: #333;
        }

        .bg-yok {
            background: #c0392b;
        }

        /* Modal (Pop-up) */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 10px;
            width: 600px;
            max-width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            background: none;
            border: none;
        }

        /* Form Elemanları */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
            font-size: 13px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }

        /* Fotoğraf Upload Alanı */
        .photo-upload {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 2px dashed #ddd;
            margin-bottom: 15px;
        }

        .photo-upload label {
            font-weight: 700;
            color: #141E30;
            margin-bottom: 8px;
            display: block;
            font-size: 14px;
        }

        .photo-upload input[type="file"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
        }

        .photo-hint {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }

        /* Checkbox Alanı */
        .checks {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #eee;
        }

        .check-item {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .check-item input {
            width: 18px;
            height: 18px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            th:nth-child(3),
            th:nth-child(4) {
                display: none;
            }

            td:nth-child(3),
            td:nth-child(4) {
                display: none;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>

    <div style="display:flex; gap:10px;">
        <a href="./dashboard.php" class="btn btn-back">🏠 Dashboard</a>
        <a href="../index.php" target="_blank" class="btn btn-back">🌐 Siteye Git</a>
        <button onclick="openAddModal()" class="btn btn-primary">➕ Yeni Ürün</button>
    </div>

    <?php if ($success): ?>
        <div style="background:#d4edda; color:#155724; padding:15px; border-radius:5px; margin-bottom:15px;">✅
            <?php echo $success; ?></div><?php endif; ?>
    <?php if ($error): ?>
        <div style="background:#f8d7da; color:#721c24; padding:15px; border-radius:5px; margin-bottom:15px;">⚠️
            <?php echo $error; ?></div><?php endif; ?>

    <div class="card">
        <form method="GET" style="margin-bottom: 15px; display: flex; gap: 10px;">
            <input type="text" name="search" placeholder="Ürün ara..." value="<?php echo htmlspecialchars($search); ?>"
                style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; flex:1; max-width:300px;">
            <button type="submit" class="btn btn-primary">Ara</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th width="70">Görsel</th>
                    <th>Ürün Adı</th>
                    <th>Kategori</th>
                    <th>Fiyat</th>
                    <th>Durum</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($urunler)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:20px; color:#999;">Kayıtlı ürün bulunamadı.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($urunler as $urun): ?>
                        <tr>
                            <td>
                                <?php if ($urun['ana_resim'] && file_exists(UPLOAD_PATH . 'products/' . $urun['ana_resim'])): ?>
                                    <img src="<?php echo UPLOAD_URL . 'products/' . $urun['ana_resim']; ?>" class="thumb">
                                <?php else: ?>
                                    <span style="font-size:24px;">📷</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($urun['urun_adi']); ?></strong><br>
                                <small style="color:#777;"><?php echo $urun['barkod_no']; ?></small>
                            </td>
                            <td><?php echo $urun['kategori_adi']; ?></td>
                            <td>
                                <div style="font-weight:bold; color:#27ae60;">
                                    <?php echo number_format($urun['doviz_satis_fiyati'], 2); ?> $
                                </div>
                                <small style="color:#999;">Alış: <?php echo number_format($urun['doviz_alis_fiyati'], 2); ?>
                                    $</small>
                            </td>
                            <td>
                                <?php
                                if ($urun['stok_miktari'] <= 0)
                                    echo '<span class="badge bg-yok">Tükendi</span> ';
                                elseif ($urun['stok_miktari'] <= $urun['kritik_stok_seviyesi'])
                                    echo '<span class="badge bg-kritik">' . $urun['stok_miktari'] . ' (Kritik)</span> ';
                                else
                                    echo '<span class="badge bg-stok">' . $urun['stok_miktari'] . ' Stok</span> ';
                                ?>
                                <br>
                                <?php if ($urun['vitrin_urun'])
                                    echo '<span class="badge bg-vitrin">Vitrin</span> '; ?>
                                <?php if ($urun['yeni_urun'])
                                    echo '<span class="badge bg-slider">Slider</span>'; ?>
                            </td>
                            <td style="white-space: nowrap;">
                                <button class="btn btn-warning"
                                    onclick='openEditModal(<?php echo json_encode($urun, JSON_HEX_APOS); ?>)'>✏️</button>
                                <button class="btn btn-success"
                                    onclick='openStockModal(<?php echo json_encode($urun, JSON_HEX_APOS); ?>)'>📦</button>
                                <a href="?delete=<?php echo $urun['urun_id']; ?>" class="btn btn-danger"
                                    onclick="return confirm('Bu ürünü silmek istediğinize emin misiniz?')">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeAddModal()">×</button>
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">➕ Yeni Ürün Ekle</h3>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">

                <div class="checks">
                    <label class="check-item" title="Ana sayfada 'Öne Çıkanlar' kısmında görünür">
                        <input type="checkbox" name="vitrin_urun"> ✨ Vitrin (Öne Çıkan)
                    </label>
                    <label class="check-item" title="Ana sayfada 'Yeni Gelenler' (Kayan Slider) kısmında görünür">
                        <input type="checkbox" name="yeni_urun" checked> 🔥 Slider (Kayan Kısım)
                    </label>
                    <label class="check-item">
                        <input type="checkbox" name="indirimli"> 🏷️ İndirimli
                    </label>
                </div>

                <div class="photo-upload" style="border-color: #27ae60;">
                    <label>📸 Ana Fotoğraf (Kapak Resmi)</label>
                    <input type="file" name="ana_fotograf" accept="image/*">
                    <div class="photo-hint">Bu fotoğraf ürün kartlarında ve listelerde görünecek.</div>
                </div>

                <div class="photo-upload" style="border-color: #3498db;">
                    <label>🖼️ Diğer Fotoğraflar (Galeri)</label>
                    <input type="file" name="diger_fotograflar[]" multiple accept="image/*">
                    <div class="photo-hint">Ürün detay sayfasında gösterilecek ek fotoğraflar. Birden fazla
                        seçebilirsiniz.</div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Barkod No *</label>
                        <input type="text" name="barkod_no" required>
                    </div>
                    <div class="form-group">
                        <label>Kategori *</label>
                        <select name="kategori_id" required>
                            <option value="">Seçin</option>
                            <?php foreach ($kategoriler as $kat): ?>
                                <option value="<?php echo $kat['kategori_id']; ?>"><?php echo $kat['kategori_adi']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Ürün Adı *</label>
                    <input type="text" name="urun_adi" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Alış Fiyatı ($)</label>
                        <input type="number" name="alis_fiyati" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label>Satış Fiyatı ($)</label>
                        <input type="number" name="satis_fiyati" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label>Stok Miktarı</label>
                        <input type="number" name="stok_miktari" value="10">
                    </div>
                    <div class="form-group">
                        <label>Kritik Stok</label>
                        <input type="number" name="kritik_stok_seviyesi" value="5">
                    </div>
                    <div class="form-group">
                        <label>Marka</label>
                        <input type="text" name="marka">
                    </div>
                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" name="model">
                    </div>
                </div>

                <div class="form-group">
                    <label>Açıklama</label>
                    <textarea name="urun_aciklama" rows="3"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">💾 Kaydet</button>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeEditModal()">×</button>
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">✏️ Ürün Düzenle</h3>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="urun_id" id="edit_urun_id">

                <div class="checks">
                    <label class="check-item"><input type="checkbox" name="vitrin_urun" id="edit_vitrin_urun"> ✨
                        Vitrin</label>
                    <label class="check-item"><input type="checkbox" name="yeni_urun" id="edit_yeni_urun"> 🔥
                        Slider</label>
                    <label class="check-item"><input type="checkbox" name="indirimli" id="edit_indirimli"> 🏷️
                        İndirimli</label>
                </div>

                <div class="photo-upload" style="border-color: #27ae60;">
                    <label>📸 Ana Fotoğrafı Değiştir (Kapak)</label>
                    <input type="file" name="ana_fotograf" accept="image/*">
                    <div class="photo-hint">Yeni bir ana fotoğraf seçerseniz eskisi silinir.</div>
                </div>

                <div class="photo-upload" style="border-color: #3498db;">
                    <label>🖼️ Yeni Galeri Fotoğrafları Ekle</label>
                    <input type="file" name="diger_fotograflar[]" multiple accept="image/*">
                    <div class="photo-hint">Mevcut fotoğraflara eklenecek. Eskiler silinmez.</div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Barkod No</label>
                        <input type="text" name="barkod_no" id="edit_barkod_no" required>
                    </div>
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="kategori_id" id="edit_kategori_id" required>
                            <?php foreach ($kategoriler as $kat): ?>
                                <option value="<?php echo $kat['kategori_id']; ?>"><?php echo $kat['kategori_adi']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Ürün Adı</label>
                    <input type="text" name="urun_adi" id="edit_urun_adi" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Alış ($)</label>
                        <input type="number" name="alis_fiyati" id="edit_alis_fiyati" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Satış ($)</label>
                        <input type="number" name="satis_fiyati" id="edit_satis_fiyati" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Stok</label>
                        <input type="number" name="stok_miktari" id="edit_stok_miktari">
                    </div>
                    <div class="form-group">
                        <label>Kritik</label>
                        <input type="number" name="kritik_stok_seviyesi" id="edit_kritik_stok_seviyesi">
                    </div>
                    <div class="form-group">
                        <label>Marka</label>
                        <input type="text" name="marka" id="edit_marka">
                    </div>
                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" name="model" id="edit_model">
                    </div>
                </div>

                <div class="form-group">
                    <label>Açıklama</label>
                    <textarea name="urun_aciklama" id="edit_urun_aciklama" rows="3"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">💾 Güncelle</button>
            </form>
        </div>
    </div>

    <div id="stockModal" class="modal">
        <div class="modal-content" style="max-width:400px;">
            <button class="close-modal" onclick="closeStockModal()">×</button>
            <h3 style="margin-top:0;">📦 Stok Güncelle</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_stock">
                <input type="hidden" name="urun_id" id="stock_urun_id">

                <p style="background:#f8f9fa; padding:10px; border-radius:5px;">
                    <strong>Ürün:</strong> <span id="stock_urun_adi"></span>
                </p>

                <div class="form-group">
                    <label>Yeni Stok Miktarı</label>
                    <input type="number" name="yeni_stok" id="stock_yeni_stok" required min="0"
                        style="font-size:18px; font-weight:bold;">
                </div>

                <button type="submit" class="btn btn-success" style="width:100%;">💾 Kaydet</button>
            </form>
        </div>
    </div>

    <script>
        // Modal Fonksiyonları
        function openAddModal() { document.getElementById('addModal').classList.add('active'); document.body.style.overflow = 'hidden'; }
        function closeAddModal() { document.getElementById('addModal').classList.remove('active'); document.body.style.overflow = 'auto'; }

        function openEditModal(urun) {
            document.getElementById('edit_urun_id').value = urun.urun_id;
            document.getElementById('edit_barkod_no').value = urun.barkod_no;
            document.getElementById('edit_kategori_id').value = urun.kategori_id;
            document.getElementById('edit_urun_adi').value = urun.urun_adi;
            document.getElementById('edit_urun_aciklama').value = urun.urun_aciklama || '';
            document.getElementById('edit_alis_fiyati').value = urun.doviz_alis_fiyati;
            document.getElementById('edit_satis_fiyati').value = urun.doviz_satis_fiyati;
            document.getElementById('edit_stok_miktari').value = urun.stok_miktari;
            document.getElementById('edit_kritik_stok_seviyesi').value = urun.kritik_stok_seviyesi;
            document.getElementById('edit_marka').value = urun.marka || '';
            document.getElementById('edit_model').value = urun.model || '';

            document.getElementById('edit_vitrin_urun').checked = (urun.vitrin_urun == 1);
            document.getElementById('edit_yeni_urun').checked = (urun.yeni_urun == 1);
            document.getElementById('edit_indirimli').checked = (urun.indirimli == 1);

            document.getElementById('editModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeEditModal() { document.getElementById('editModal').classList.remove('active'); document.body.style.overflow = 'auto'; }

        function openStockModal(urun) {
            document.getElementById('stock_urun_id').value = urun.urun_id;
            document.getElementById('stock_urun_adi').textContent = urun.urun_adi;
            document.getElementById('stock_yeni_stok').value = urun.stok_miktari;
            document.getElementById('stockModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeStockModal() { document.getElementById('stockModal').classList.remove('active'); document.body.style.overflow = 'auto'; }

        // Dışarı tıklama
        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        }
    </script>

</body>

</html>