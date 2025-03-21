<?php
// Oturum başlat
session_start();

// Konfigürasyon dosyalarını yükle
$config = require_once __DIR__ . '/../config/uygulama.php';

// Gerekli sınıfları yükle
require_once __DIR__ . '/../app/Veritabani.php';
require_once __DIR__ . '/../app/Kullanici.php';
require_once __DIR__ . '/../app/CloudflareAPI.php';
require_once __DIR__ . '/../app/Domain.php';
require_once __DIR__ . '/../app/Yardimci.php';

use App\Kullanici;
use App\Domain;
use App\CloudflareAPI;
use App\Yardimci;

// Sadece POST isteklerine yanıt ver
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['mesaj'] = 'Geçersiz istek yöntemi.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('domainler.php');
    exit;
}

// Oturum kontrolü
$kullanici = new Kullanici();
$kullanici_bilgileri = $kullanici->oturumKontrol();

if (!$kullanici_bilgileri) {
    Yardimci::yonlendir('giris.php');
    exit;
}

// Domain ID'sini al
$domain_id = isset($_POST['domain_id']) ? (int)$_POST['domain_id'] : 0;

if ($domain_id <= 0) {
    $_SESSION['mesaj'] = 'Geçersiz domain ID.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('domainler.php');
    exit;
}

// Domain sınıfı örneği oluştur
$domain = new Domain();

// Domain bilgilerini al
$domain_bilgi = $domain->domainGetir($domain_id, $kullanici_bilgileri['id']);

if (!$domain_bilgi) {
    $_SESSION['mesaj'] = 'Domain bulunamadı veya bu domain size ait değil.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('domainler.php');
    exit;
}

// API anahtarı bilgilerini al
$api_anahtar = $kullanici->apiAnahtariGetir($domain_bilgi['api_anahtar_id']);

if (!$api_anahtar) {
    $_SESSION['mesaj'] = 'Bu domain için API anahtarı bulunamadı.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('domainler.php');
    exit;
}

// CloudflareAPI'yi ayarla
$domain->setCloudflareAPI($api_anahtar['api_anahtari'] ?? '', $api_anahtar['email'] ?? '');

// Firewall kurallarını senkronize et
$sonuc = $domain->firewallKurallariniSenkronizeEt($domain_id, $domain_bilgi['zone_id']);

if ($sonuc) {
    $_SESSION['mesaj'] = 'Firewall kuralları Cloudflare\'den başarıyla senkronize edildi.';
    $_SESSION['mesaj_tur'] = 'success';
} else {
    $_SESSION['mesaj'] = 'Firewall kuralları senkronize edilirken bir hata oluştu.';
    $_SESSION['mesaj_tur'] = 'danger';
}

// Firewall kuralları sayfasına yönlendir
Yardimci::yonlendir('firewall-kurallari.php?domain_id=' . $domain_id); 