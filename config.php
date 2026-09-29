<?php
/**
 * NK Teknoloji - Ana Yapılandırma Dosyası
 *
 * Veritabanı bilgileri artık kod içinde sabit (hardcoded) değil,
 * proje kök dizinindeki .env dosyasından okunuyor.
 * Kurulum için .env.example dosyasını .env olarak kopyalayıp kendi bilgilerinizi girin.
 */

// ==============================================
// 1. SESSION VE HATA AYARLARI
// ==============================================
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_DEBUG') === 'true' ? 1 : 0);
date_default_timezone_set('Europe/Istanbul');

// ==============================================
// 2. .ENV DOSYASINI YÜKLE (harici paket gerektirmez)
// ==============================================
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, '=') === false) {
            continue;
        }

        list($key, $value) = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");

        if (!getenv($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

loadEnv(__DIR__ . '/.env');

// ==============================================
// 3. VERİTABANI VE SİTE AYARLARI
// ==============================================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Site URL Ayarı (Otomatik Algılama)
if (isset($_SERVER['HTTP_HOST'])) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";

    if (getenv('SITE_URL')) {
        define('SITE_URL', rtrim(getenv('SITE_URL'), '/') . '/');
    } else {
        define('SITE_URL', $protocol . $_SERVER['HTTP_HOST'] . '/');
    }
} else {
    define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/');
}

define('SITE_NAME', getenv('SITE_NAME') ?: 'NK Teknoloji');

if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', __DIR__ . '/uploads/');
if (!defined('UPLOAD_URL')) define('UPLOAD_URL', SITE_URL . 'uploads/');
if (!defined('WHATSAPP_NUMBER')) define('WHATSAPP_NUMBER', getenv('WHATSAPP_NUMBER') ?: '');

// KDV Oranı (%)
if (!defined('KDV_ORANI')) define('KDV_ORANI', (int) (getenv('KDV_ORANI') ?: 20));

// ==============================================
// 4. DATABASE SINIFI
// ==============================================
class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Veritabanı bağlantı hatası: ' . $e->getMessage());
            die('Veritabanı bağlantı hatası. Lütfen .env dosyanızı kontrol edin.');
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            return false;
        }
    }

    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : false;
    }

    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    public function insert($table, $data) {
        try {
            $columns = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
            $this->query($sql, $data);
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function update($table, $data, $where, $whereParams = []) {
        $setClause = [];
        foreach ($data as $key => $value) {
            $setClause[] = "$key = :$key";
        }
        $setClause = implode(', ', $setClause);
        $sql = "UPDATE $table SET $setClause WHERE $where";
        return $this->query($sql, array_merge($data, $whereParams));
    }

    public function delete($table, $where, $whereParams = []) {
        try {
            $sql = "DELETE FROM $table WHERE $where";
            return $this->query($sql, $whereParams);
        } catch (PDOException $e) {
            return false;
        }
    }
}

// Global DB Nesnesi
$db = Database::getInstance();

// ==============================================
// 5. GENEL YARDIMCI FONKSİYONLAR
// ==============================================

function clean($data) {
    if (is_array($data)) return array_map('clean', $data);
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function getSetting($key, $default = '') {
    if ($key === 'whatsapp_number') return WHATSAPP_NUMBER;
    if ($key === 'site_name') return SITE_NAME;
    if ($key === 'site_url') return SITE_URL;
    if ($key === 'kdv_orani') return KDV_ORANI;
    return $default;
}

function format_date($date, $format = 'd.m.Y H:i') {
    return date($format, strtotime($date));
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function check_admin_login() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        redirect('admin/login.php');
    }
}

// ==============================================
// 6. DÖVİZ VE FİYAT SİSTEMİ
// ==============================================

function getDovizKuru($doviz = 'USD') {
    global $db;
    $kur = $db->fetchOne("SELECT satis FROM doviz_kurlari WHERE doviz = ?", [$doviz]);
    return ($kur && $kur['satis'] > 0) ? $kur['satis'] : 34.50;
}

function dovizToTL($fiyat, $doviz = 'USD') {
    $kur = getDovizKuru($doviz);
    return $fiyat * $kur;
}

function getKur($doviz = 'USD') {
    return getDovizKuru($doviz);
}

function calculateKDV($fiyat, $kdv_orani = null) {
    if ($kdv_orani === null) {
        $kdv_orani = KDV_ORANI;
    }
    return $fiyat * ($kdv_orani / 100);
}

function addKDV($fiyat, $kdv_orani = null) {
    if ($kdv_orani === null) {
        $kdv_orani = KDV_ORANI;
    }
    return $fiyat * (1 + ($kdv_orani / 100));
}

function removeKDV($fiyat, $kdv_orani = null) {
    if ($kdv_orani === null) {
        $kdv_orani = KDV_ORANI;
    }
    return $fiyat / (1 + ($kdv_orani / 100));
}

function formatPriceDual($fiyat, $para_birimi = 'USD', $kdv_dahil = false) {
    if (empty($fiyat) || $fiyat == 0) {
        return '<div style="color:#999; font-size:14px; font-weight:500;">Fiyat Sorunuz</div>';
    }

    $kur = getDovizKuru('USD');

    if ($para_birimi == 'TRY' || $para_birimi == 'TL') {
        $tl_fiyat = $fiyat;
        $usd_fiyat = $fiyat / $kur;
    } else {
        $usd_fiyat = $fiyat;
        $tl_fiyat = $fiyat * $kur;
    }

    $kdv_text = $kdv_dahil ? '' : ' + KDV';

    return '
    <div class="price-dual-box" style="line-height: 1.3;">
        <div style="font-weight:700; color:#27ae60; font-size:18px;">' . number_format($usd_fiyat, 2, ',', '.') . ' USD' . $kdv_text . '</div>
        <div style="color:#27ae60; font-size:16px; font-weight:600; margin-top:2px;">' . number_format($tl_fiyat, 2, ',', '.') . ' TL' . $kdv_text . '</div>
    </div>';
}

function formatPriceCart($fiyat, $para_birimi = 'USD', $adet = 1) {
    if (empty($fiyat) || $fiyat == 0) {
        return '<span style="color:#999; font-size:12px;">Fiyat Sorunuz</span>';
    }

    $kur = getDovizKuru('USD');

    if ($para_birimi == 'TRY' || $para_birimi == 'TL') {
        $tl_fiyat = $fiyat;
        $usd_fiyat = $fiyat / $kur;
    } else {
        $usd_fiyat = $fiyat;
        $tl_fiyat = $fiyat * $kur;
    }

    $usd_toplam = $usd_fiyat * $adet;
    $tl_toplam = $tl_fiyat * $adet;

    $kdv_usd = calculateKDV($usd_toplam);
    $kdv_tl = calculateKDV($tl_toplam);

    $genel_toplam_usd = $usd_toplam + $kdv_usd;
    $genel_toplam_tl = $tl_toplam + $kdv_tl;

    return '
    <div class="cart-price-box" style="background:#f8f9fa; padding:15px; border-radius:8px; border:1px solid #ecf0f1;">
        <table style="width:100%; font-size:0.95em;">
            <tr>
                <td style="color:#7f8c8d; padding:5px 0;">Ürün Sayısı:</td>
                <td style="text-align:right; font-weight:600; color:#2c3e50;">' . $adet . ' adet</td>
            </tr>
            <tr style="border-top:1px dashed #bdc3c7;">
                <td style="color:#7f8c8d; padding:8px 0 5px 0;">Ara Toplam (KDV Hariç):</td>
                <td style="text-align:right; font-weight:700; color:#10b981; padding:8px 0 5px 0;">' . number_format($usd_toplam, 2, ',', '.') . ' USD + KDV</td>
            </tr>
            <tr>
                <td style="color:#7f8c8d; padding:0 0 8px 0;"></td>
                <td style="text-align:right; font-weight:700; color:#10b981; padding:0 0 8px 0;">' . number_format($tl_toplam, 2, ',', '.') . ' TL + KDV</td>
            </tr>
            <tr style="border-top:2px solid #bdc3c7;">
                <td style="color:#7f8c8d; padding:8px 0 5px 0; font-weight:600;">KDV (%' . KDV_ORANI . '):</td>
                <td style="text-align:right; font-weight:700; color:#10b981; padding:8px 0 5px 0;">' . number_format($kdv_usd, 2, ',', '.') . ' USD</td>
            </tr>
            <tr>
                <td style="color:#7f8c8d; padding:0 0 8px 0;"></td>
                <td style="text-align:right; font-weight:700; color:#10b981; padding:0 0 8px 0;">' . number_format($kdv_tl, 2, ',', '.') . ' TL</td>
            </tr>
            <tr style="border-top:2px solid #10b981; background:#ecfdf5;">
                <td style="color:#059669; padding:10px 5px; font-weight:700; font-size:1.05em;">Genel Toplam (KDV Dahil):</td>
                <td style="text-align:right; font-weight:800; color:#059669; font-size:1.15em; padding:10px 5px;">' . number_format($genel_toplam_usd, 2, ',', '.') . ' USD</td>
            </tr>
            <tr style="background:#ecfdf5;">
                <td style="color:#059669; padding:0 5px 10px 5px;"></td>
                <td style="text-align:right; font-weight:800; color:#059669; font-size:1.05em; padding:0 5px 10px 5px;">' . number_format($genel_toplam_tl, 2, ',', '.') . ' TL</td>
            </tr>
        </table>
    </div>';
}

/**
 * TCMB'den Otomatik Kur Çekme (6 Saatte bir)
 */
function updateKurTCMB() {
    global $db;

    try {
        $son_guncelleme = $db->fetchOne("SELECT guncelleme_tarihi FROM doviz_kurlari WHERE doviz = 'USD'");

        $guncelle = false;
        if (!$son_guncelleme) {
            $guncelle = true;
        } else {
            $gecen_sure = time() - strtotime($son_guncelleme['guncelleme_tarihi']);
            if ($gecen_sure > 21600) {
                $guncelle = true;
            }
        }

        if (!$guncelle) {
            return false;
        }

        $bugun = date('Ymd');
        $ay_yil = date('Ym');
        $tcmb_url = "https://www.tcmb.gov.tr/kurlar/{$ay_yil}/{$bugun}.xml";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tcmb_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $xml_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200 || empty($xml_data)) {
            $tcmb_url = "https://www.tcmb.gov.tr/kurlar/today.xml";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $tcmb_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $xml_data = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }

        if ($http_code !== 200 || empty($xml_data)) {
            error_log("TCMB'den veri alınamadı. HTTP Code: {$http_code}");
            return false;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_data);

        if ($xml === false) {
            error_log("TCMB XML parse hatası");
            return false;
        }

        $kur_bulundu = false;
        foreach ($xml->Currency as $currency) {
            $kod = (string) $currency['Kod'];

            if ($kod == 'USD') {
                $satis = (float) str_replace(',', '.', (string) $currency->BanknoteSelling);

                if (!$satis || $satis == 0) {
                    $satis = (float) str_replace(',', '.', (string) $currency->ForexSelling);
                }

                $alis = (float) str_replace(',', '.', (string) $currency->BanknoteBuying);
                if (!$alis || $alis == 0) {
                    $alis = (float) str_replace(',', '.', (string) $currency->ForexBuying);
                }

                if ($satis > 0 && $alis > 0) {
                    $result = $db->query("
                        INSERT INTO doviz_kurlari (doviz, alis, satis, guncelleme_tarihi)
                        VALUES (?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE
                            alis = VALUES(alis),
                            satis = VALUES(satis),
                            guncelleme_tarihi = NOW()
                    ", ['USD', $alis, $satis]);

                    if ($result) {
                        $kur_bulundu = true;
                    }
                }
                break;
            }
        }

        return $kur_bulundu;

    } catch (Exception $e) {
        error_log("TCMB kur güncelleme hatası: " . $e->getMessage());
        return false;
    }
}

// Kuru otomatik kontrol et
updateKurTCMB();

// ==============================================
// 7. RESİM YÜKLEME FONKSİYONU
// ==============================================

function uploadProductImage($file, $old_image = null) {
    $upload_dir = UPLOAD_PATH . 'products/';

    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Dosya yükleme hatası!'];
    }

    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'error' => 'Geçersiz dosya tipi!'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = 'product_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target_path = $upload_dir . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['success' => false, 'error' => 'Dosya kaydedilemedi!'];
    }

    if ($old_image && file_exists($upload_dir . $old_image)) {
        @unlink($upload_dir . $old_image);
    }

    return ['success' => true, 'filename' => $new_filename];
}

// ==============================================
// 8. ÜYELİK FONKSİYONLARI
// ==============================================

if (file_exists(__DIR__ . '/includes/Auth.php')) {
    require_once __DIR__ . '/includes/Auth.php';
    $GLOBALS['auth'] = new Auth();

    function isLoggedIn() {
        return isset($GLOBALS['auth']) ? $GLOBALS['auth']->isLoggedIn() : false;
    }

    function getCurrentUser() {
        return isset($GLOBALS['auth']) ? $GLOBALS['auth']->getCurrentUser() : null;
    }

    function requireLogin() {
        if (!isLoggedIn()) {
            header('Location: uye-giris.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit();
        }
    }
} else {
    function isLoggedIn() {
        return isset($_SESSION['admin_logged_in']) || isset($_SESSION['uye_id']);
    }

    function getCurrentUser() {
        return null;
    }

    function requireLogin() {
        header('Location: uye-giris.php');
        exit();
    }
}

function formatPriceAdmin($fiyat, $para_birimi = 'USD') {
    if (empty($fiyat) || $fiyat == 0) {
        return '<span style="color:#999;">0.00 $</span>';
    }

    $sembol = '$';
    if ($para_birimi == 'TL' || $para_birimi == 'TRY') {
        $sembol = '₺';
    } elseif ($para_birimi == 'EUR') {
        $sembol = '€';
    }

    return number_format($fiyat, 2, '.', ',') . ' ' . $sembol;
}

function formatPrice($price) {
    $is_admin = (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false);

    if ($is_admin) {
        return formatPriceAdmin($price, 'USD');
    } else {
        return formatPriceDual($price, 'USD', false);
    }
}
