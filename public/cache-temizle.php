<?php
// Oturum başlat
session_start();

// Konfigürasyon dosyalarını yükle
$config = require_once __DIR__ . '/../config/uygulama.php';

// Gerekli sınıfları yükle
require_once __DIR__ . '/../app/Veritabani.php';
require_once __DIR__ . '/../app/Kullanici.php';
require_once __DIR__ . '/../app/Yardimci.php';

use App\Kullanici;
use App\Yardimci;

// Oturum kontrolü
$kullanici = new Kullanici();
$kullanici_bilgileri = $kullanici->oturumKontrol();

if (!$kullanici_bilgileri) {
    Yardimci::yonlendir('giris.php');
}

// Sadece admin yetkisi olanlar erişebilir
if ($kullanici_bilgileri['yetki'] !== 'admin') {
    $_SESSION['mesaj'] = 'Bu işlemi gerçekleştirme yetkiniz bulunmamaktadır.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('index.php');
}

// Önbellek tipini al
$tip = isset($_GET['tip']) ? Yardimci::temizle($_GET['tip']) : '';

if (empty($tip)) {
    $_SESSION['mesaj'] = 'Geçersiz önbellek tipi.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('cache-yonetimi.php');
}

// Önbellek temizleme işlemi
$basari = false;
$mesaj = '';

// Önbellek dizinleri
$cache_dir = __DIR__ . '/../cache/';
$app_cache_dir = $cache_dir . 'app/';
$session_cache_dir = $cache_dir . 'sessions/';
$file_cache_dir = $cache_dir . 'files/';
$dns_cache_dir = $cache_dir . 'dns/';
$api_cache_dir = $cache_dir . 'api/';
$image_cache_dir = $cache_dir . 'images/';
$db_cache_dir = $cache_dir . 'db/';

// Dizin temizleme fonksiyonu
function temizleDizin($dizin) {
    if (!is_dir($dizin)) {
        return false;
    }
    
    $files = glob($dizin . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            temizleDizin($file);
        } else {
            unlink($file);
        }
    }
    
    return true;
}

// Önbellek tipine göre temizleme işlemi
switch ($tip) {
    case 'all':
        // Tüm önbelleği temizle
        $basari = temizleDizin($cache_dir);
        $mesaj = 'Tüm önbellek başarıyla temizlendi.';
        break;
        
    case 'app':
        // Uygulama önbelleğini temizle
        $basari = temizleDizin($app_cache_dir);
        $mesaj = 'Uygulama önbelleği başarıyla temizlendi.';
        break;
        
    case 'session':
        // Oturum önbelleğini temizle
        $basari = temizleDizin($session_cache_dir);
        $mesaj = 'Oturum önbelleği başarıyla temizlendi.';
        break;
        
    case 'file':
        // Dosya önbelleğini temizle
        $basari = temizleDizin($file_cache_dir);
        $mesaj = 'Dosya önbelleği başarıyla temizlendi.';
        break;
        
    case 'dns':
        // DNS önbelleğini temizle
        $basari = temizleDizin($dns_cache_dir);
        $mesaj = 'DNS önbelleği başarıyla temizlendi.';
        break;
        
    case 'api':
        // API önbelleğini temizle
        $basari = temizleDizin($api_cache_dir);
        $mesaj = 'API önbelleği başarıyla temizlendi.';
        break;
        
    case 'image':
        // Görüntü önbelleğini temizle
        $basari = temizleDizin($image_cache_dir);
        $mesaj = 'Görüntü önbelleği başarıyla temizlendi.';
        break;
        
    case 'db':
        // Veritabanı önbelleğini temizle
        $basari = temizleDizin($db_cache_dir);
        $mesaj = 'Veritabanı önbelleği başarıyla temizlendi.';
        break;
        
    default:
        $basari = false;
        $mesaj = 'Geçersiz önbellek tipi.';
}

// İşlem sonucunu session'a kaydet
if ($basari) {
    $_SESSION['mesaj'] = $mesaj;
    $_SESSION['mesaj_tur'] = 'success';
} else {
    $_SESSION['mesaj'] = 'Önbellek temizlenirken bir hata oluştu: ' . $mesaj;
    $_SESSION['mesaj_tur'] = 'danger';
}

// İşlem kaydını tut
$log_mesaj = date('Y-m-d H:i:s') . ' - Kullanıcı: ' . $kullanici_bilgileri['kullanici_adi'] . 
             ' - İşlem: Önbellek Temizleme (' . $tip . ') - Sonuç: ' . ($basari ? 'Başarılı' : 'Başarısız');
file_put_contents(__DIR__ . '/../logs/cache.log', $log_mesaj . PHP_EOL, FILE_APPEND);

// Önbellek yönetimi sayfasına yönlendir
Yardimci::yonlendir('cache-yonetimi.php');
?>
