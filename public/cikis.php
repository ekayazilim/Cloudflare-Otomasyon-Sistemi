<?php
// Oturum başlat
session_start();

// Gerekli sınıfları yükle
require_once __DIR__ . '/../app/Veritabani.php';
require_once __DIR__ . '/../app/Kullanici.php';
require_once __DIR__ . '/../app/Yardimci.php';

use App\Kullanici;
use App\Yardimci;

// Kullanıcı oturumunu kapat
$kullanici = new Kullanici();
$kullanici->cikis();

// Başarılı mesajı ayarla
$_SESSION['mesaj'] = 'Başarıyla çıkış yaptınız.';
$_SESSION['mesaj_tur'] = 'success';

// Giriş sayfasına yönlendir
Yardimci::yonlendir('giris.php');
?>
