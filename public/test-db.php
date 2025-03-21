<?php
// Gerekli dosyaları dahil et
require_once __DIR__ . '/../app/Veritabani.php';
require_once __DIR__ . '/../app/Kullanici.php';
require_once __DIR__ . '/../app/Domain.php';

use App\Veritabani;
use App\Kullanici;
use App\Domain;

// Veritabanı bağlantısı
$db = Veritabani::baglan();

// Kullanıcı sınıfı örneği oluştur
$kullanici = new Kullanici();

// Domain sınıfı örneği oluştur
$domain = new Domain();

// Domainler tablosundaki kayıtları getir
$domainler = $db->getirTumu("SELECT * FROM domainler");

// Sonuçları göster
echo "<h2>Domainler Tablosu</h2>";
echo "<pre>";
print_r($domainler);
echo "</pre>";

// Cloudflare API Anahtarları tablosundaki kayıtları getir
$api_anahtarlari = $db->getirTumu("SELECT * FROM cloudflare_api_anahtarlari");

// Sonuçları göster
echo "<h2>API Anahtarları Tablosu</h2>";
echo "<pre>";
print_r($api_anahtarlari);
echo "</pre>";

// Kullanıcı ID'si 1 için API anahtarlarını listele
$kullanici_api_anahtarlari = $kullanici->apiAnahtarlariniListele(1);
echo "<h2>Kullanıcı ID: 1 için API Anahtarları</h2>";
echo "<pre>";
print_r($kullanici_api_anahtarlari);
echo "</pre>";

// Kullanıcı ID'si 1 için domainleri listele
$kullanici_domainleri = $domain->domainleriListele(1);
echo "<h2>Kullanıcı ID: 1 için Domainler</h2>";
echo "<pre>";
print_r($kullanici_domainleri);
echo "</pre>";
