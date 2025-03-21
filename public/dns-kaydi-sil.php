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

// Oturum kontrolü
$kullanici = new Kullanici();
$kullanici_bilgileri = $kullanici->oturumKontrol();

if (!$kullanici_bilgileri) {
    Yardimci::yonlendir('giris.php');
}

// DNS Kaydı ID'sini al
$kayit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($kayit_id <= 0) {
    $_SESSION['mesaj'] = 'Geçersiz kayıt ID.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('domainler.php');
}

// Domain sınıfı örneği oluştur
$domain = new Domain();

// DNS kaydı bilgilerini al
$dns_kaydi = $domain->dnsKaydiGetir($kayit_id);

if (!$dns_kaydi) {
    $_SESSION['mesaj'] = 'DNS kaydı bulunamadı.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('domainler.php');
}

// Domain bilgilerini al
$domain_bilgi = $domain->domainGetir($dns_kaydi['domain_id'], $kullanici_bilgileri['id']);

if (!$domain_bilgi) {
    $_SESSION['mesaj'] = 'Domain bulunamadı veya bu domain size ait değil.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('domainler.php');
}

// API anahtarı bilgilerini al
$api_anahtar = $kullanici->apiAnahtariGetir($domain_bilgi['api_anahtar_id']);

if (!$api_anahtar) {
    $_SESSION['mesaj'] = 'Bu domain için API anahtarı bulunamadı.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('domainler.php');
}

// Cloudflare API örneği oluştur
$cloudflare = new CloudflareAPI($api_anahtar['api_anahtari'], $api_anahtar['email']);

// Onay alındı mı?
$onaylandi = isset($_GET['onay']) && $_GET['onay'] == 1;

if ($onaylandi) {
    // DNS kaydını sil
    $dns_sil_sonuc = $cloudflare->dnsKaydiSil($domain_bilgi['zone_id'], $dns_kaydi['record_id']);
    
    if (isset($dns_sil_sonuc['success']) && $dns_sil_sonuc['success']) {
        // Veritabanından da sil
        $silindi = $domain->dnsKaydiSil($kayit_id);
        
        if ($silindi) {
            $_SESSION['mesaj'] = 'DNS kaydı başarıyla silindi.';
            $_SESSION['mesaj_tur'] = 'success';
        } else {
            $_SESSION['mesaj'] = 'DNS kaydı Cloudflare\'den silindi ancak veritabanından silinirken bir hata oluştu.';
            $_SESSION['mesaj_tur'] = 'warning';
        }
    } else {
        $_SESSION['mesaj'] = 'DNS kaydı silinirken bir hata oluştu: ' . 
            (isset($dns_sil_sonuc['errors']) && is_array($dns_sil_sonuc['errors']) && isset($dns_sil_sonuc['errors'][0]['message']) ? $dns_sil_sonuc['errors'][0]['message'] : 'Bilinmeyen hata');
        $_SESSION['mesaj_tur'] = 'danger';
    }
    
    // DNS kayıtları sayfasına yönlendir
    Yardimci::yonlendir('dns-kayitlari.php?domain_id=' . $dns_kaydi['domain_id']);
}

// Header'ı yükle
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>DNS Kaydı Sil</h1>
            <a href="dns-kayitlari.php?domain_id=<?php echo $dns_kaydi['domain_id']; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> DNS Kayıtlarına Dön
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0">DNS Kaydını Silmek Üzeresiniz</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> <strong>Uyarı:</strong> Bu işlem geri alınamaz! DNS kaydını sildiğinizde, bu kayda bağlı hizmetler çalışmayı durdurabilir.
        </div>
        
        <h4>Silinecek DNS Kaydı Bilgileri:</h4>
        <table class="table table-bordered">
            <tr>
                <th style="width: 150px;">Domain:</th>
                <td><?php echo $domain_bilgi['domain']; ?></td>
            </tr>
            <tr>
                <th>Kayıt Adı:</th>
                <td><?php echo isset($dns_kaydi['isim']) ? $dns_kaydi['isim'] : (isset($dns_kaydi['ad']) ? $dns_kaydi['ad'] : 'Belirtilmemiş'); ?></td>
            </tr>
            <tr>
                <th>Tip:</th>
                <td><span class="badge bg-secondary"><?php echo $dns_kaydi['tip']; ?></span></td>
            </tr>
            <tr>
                <th>İçerik:</th>
                <td><code><?php echo $dns_kaydi['icerik']; ?></code></td>
            </tr>
            <tr>
                <th>TTL:</th>
                <td>
                    <?php if ($dns_kaydi['ttl'] == 1): ?>
                    <span class="badge bg-info">Otomatik</span>
                    <?php else: ?>
                    <?php echo $dns_kaydi['ttl']; ?> sn
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Proxy:</th>
                <td>
                    <?php if (isset($dns_kaydi['proxy']) && $dns_kaydi['proxy'] == 1): ?>
                    <span class="badge bg-success">Açık</span>
                    <?php else: ?>
                    <span class="badge bg-danger">Kapalı</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <div class="mt-4">
            <p>Bu DNS kaydını silmek istediğinizden emin misiniz?</p>
            
            <div class="d-flex gap-2">
                <a href="dns-kaydi-sil.php?id=<?php echo $kayit_id; ?>&onay=1" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Evet, Sil
                </a>
                <a href="dns-kayitlari.php?domain_id=<?php echo $dns_kaydi['domain_id']; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> İptal
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı yükle
include_once __DIR__ . '/../resources/templates/footer.php';
?> 