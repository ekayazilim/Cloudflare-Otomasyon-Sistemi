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
use App\Yardimci;

// Oturum kontrolü
$kullanici = new Kullanici();
$kullanici_bilgileri = $kullanici->oturumKontrol();

if (!$kullanici_bilgileri) {
    Yardimci::yonlendir('giris.php');
}

// Domain istatistiklerini al
$domain = new Domain();
$domainler = $domain->domainleriListele($kullanici_bilgileri['id']);

// API anahtarlarını al
$api_anahtarlari = $kullanici->apiAnahtarlariniListele($kullanici_bilgileri['id']);

// İstatistikler
$toplam_domain = is_array($domainler) ? count($domainler) : 0;
$toplam_api_anahtari = is_array($api_anahtarlari) ? count($api_anahtarlari) : 0;

// DNS kayıtları sayısı
$toplam_dns_kaydi = 0;
if (is_array($domainler)) {
    foreach ($domainler as $d) {
        if (isset($d['id'])) {
            $dns_kayitlari = $domain->dnsKayitlariniListele($d['id']);
            $toplam_dns_kaydi += is_array($dns_kayitlari) ? count($dns_kayitlari) : 0;
        }
    }
}

// Son eklenen domainler (en fazla 5 tane)
$son_domainler = is_array($domainler) ? array_slice($domainler, 0, 5) : [];

// Header'ı yükle
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">Hoş Geldiniz, <?php echo $kullanici_bilgileri['ad']; ?>!</h1>
    </div>
</div>

<!-- İstatistikler -->
<div class="row dashboard-stats">
    <div class="col-md-4">
        <div class="card stat-card">
            <i class="fas fa-globe"></i>
            <h3><?php echo $toplam_domain; ?></h3>
            <p>Toplam Domain</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <i class="fas fa-key"></i>
            <h3><?php echo $toplam_api_anahtari; ?></h3>
            <p>API Anahtarı</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <i class="fas fa-server"></i>
            <h3><?php echo $toplam_dns_kaydi; ?></h3>
            <p>DNS Kaydı</p>
        </div>
    </div>
</div>

<!-- Hızlı İşlemler -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Hızlı İşlemler</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="domain-ekle.php" class="btn btn-primary w-100">
                            <i class="fas fa-plus-circle"></i> Domain Ekle
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="api-anahtari-ekle.php" class="btn btn-secondary w-100">
                            <i class="fas fa-key"></i> API Anahtarı Ekle
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="dns-kaydi-ekle.php" class="btn btn-success w-100">
                            <i class="fas fa-server"></i> DNS Kaydı Ekle
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="firewall-kurali-ekle.php" class="btn btn-danger w-100">
                            <i class="fas fa-shield-alt"></i> Firewall Kuralı Ekle
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Son Eklenen Domainler -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Son Eklenen Domainler</h5>
            </div>
            <div class="card-body">
                <?php if (empty($son_domainler)): ?>
                <div class="alert alert-info">
                    Henüz domain eklenmemiş. <a href="domain-ekle.php">Hemen ekleyin</a>.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>SSL Modu</th>
                                <th>Durum</th>
                                <th>Eklenme Tarihi</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($son_domainler as $d): ?>
                            <tr>
                                <td><?php echo $d['domain']; ?></td>
                                <td>
                                    <span class="ssl-badge ssl-<?php echo strtolower($d['ssl_modu']); ?>">
                                        <?php echo strtoupper($d['ssl_modu']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($d['durum'] == 1): ?>
                                    <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo Yardimci::tarihFormat($d['olusturma_tarihi']); ?></td>
                                <td>
                                    <a href="domain-detay.php?id=<?php echo $d['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="domain-duzenle.php?id=<?php echo $d['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="domain-sil.php?id=<?php echo $d['id']; ?>" class="btn btn-sm btn-danger delete-confirm">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end mt-3">
                    <a href="domainler.php" class="btn btn-primary">
                        Tüm Domainleri Görüntüle <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Sistem Bilgileri -->
<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Sistem Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-info-circle"></i> PHP Bilgileri</h6>
                        <ul class="list-group mb-3">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                PHP Sürümü
                                <span class="badge bg-primary"><?php echo phpversion(); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Bellek Limiti
                                <span class="badge bg-primary"><?php echo ini_get('memory_limit'); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Maksimum Yükleme Boyutu
                                <span class="badge bg-primary"><?php echo ini_get('upload_max_filesize'); ?></span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-server"></i> Sunucu Bilgileri</h6>
                        <ul class="list-group mb-3">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Sunucu Yazılımı
                                <span class="badge bg-secondary"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                IP Adresi
                                <span class="badge bg-secondary"><?php echo $_SERVER['SERVER_ADDR']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Protokol
                                <span class="badge bg-secondary"><?php echo $_SERVER['SERVER_PROTOCOL']; ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <?php if ($kullanici_bilgileri['yetki'] === 'admin'): ?>
                <div class="text-end mt-3">
                    <a href="sistem-bilgileri.php" class="btn btn-dark">
                        Detaylı Sistem Bilgilerini Görüntüle <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı yükle
include_once __DIR__ . '/../resources/templates/footer.php';
?>
