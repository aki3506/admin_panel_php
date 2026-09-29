<?php
/**
 * Çıkış İşlemi 
 */

// Session başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tüm session verilerini temizle
$_SESSION = array();

// Session çerezini sil
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Session'ı yok et
session_destroy();

// Login sayfasına yönlendir (RELATIVE URL)
header('Location: login.php?success=logout');
exit();
?>