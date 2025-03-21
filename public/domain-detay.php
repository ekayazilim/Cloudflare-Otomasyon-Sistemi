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

// Domain ID'sini al
$domain_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($domain_id <= 0) {
    $_SESSION['mesaj'] = 'Geçersiz domain ID.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('domainler.php');
}

// Domain sınıfı örneği oluştur
$domain = new Domain();

// Domain bilgilerini al
$domain_bilgi = $domain->domainGetir($domain_id, $kullanici_bilgileri['id']);

if (!$domain_bilgi) {
    $_SESSION['mesaj'] = 'Domain bulunamadı veya bu domain size ait değil.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('domainler.php');
}

// API anahtarı ile CloudflareAPI nesnesi oluştur
$cloudflareAPI = new CloudflareAPI($domain_bilgi['api_key'], $domain_bilgi['api_email']);

// Zone detaylarını al
$zone_detaylari = $cloudflareAPI->zoneDetay($domain_bilgi['zone_id']);

// Domain'in DNS kayıtlarını getir
$dns_kayitlari = $domain->dnsKayitlariniListele($domain_id);

// Header'ı yükle
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Domain Detayları</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                        <div class="dropdown-header">İşlemler:</div>
                        <a class="dropdown-item" href="domain-duzenle.php?id=<?php echo $domain_id; ?>"><i class="fas fa-edit fa-sm fa-fw mr-2 text-gray-400"></i> Düzenle</a>
                        <a class="dropdown-item" href="dns-kayitlari.php?domain_id=<?php echo $domain_id; ?>"><i class="fas fa-list fa-sm fa-fw mr-2 text-gray-400"></i> DNS Kayıtları</a>
                        <a class="dropdown-item" href="firewall-kurallari.php?domain_id=<?php echo $domain_id; ?>"><i class="fas fa-shield-alt fa-sm fa-fw mr-2 text-gray-400"></i> Firewall Kuralları</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" href="domain-sil.php?id=<?php echo $domain_id; ?>" onclick="return confirm('Bu domaini silmek istediğinize emin misiniz?');"><i class="fas fa-trash fa-sm fa-fw mr-2 text-danger"></i> Sil</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h4 class="text-primary"><?php echo htmlspecialchars($domain_bilgi['domain']); ?></h4>
                        <p>
                            <strong>Zone ID:</strong> <?php echo htmlspecialchars($domain_bilgi['zone_id']); ?><br>
                            <strong>Plan:</strong> <?php echo htmlspecialchars($domain_bilgi['plan']); ?><br>
                            <strong>SSL Modu:</strong> <?php echo htmlspecialchars($domain_bilgi['ssl_modu']); ?><br>
                            <strong>DNS Kayıt Sayısı:</strong> <?php echo (int)$domain_bilgi['dns_kayit_sayisi']; ?><br>
                            <strong>Durum:</strong> 
                            <?php if($domain_bilgi['durum'] == 1): ?>
                                <span class="badge badge-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Pasif</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p>
                            <strong>Oluşturma Tarihi:</strong> <?php echo htmlspecialchars($domain_bilgi['olusturma_tarihi']); ?><br>
                            <strong>Güncelleme Tarihi:</strong> <?php echo $domain_bilgi['guncelleme_tarihi'] ? htmlspecialchars($domain_bilgi['guncelleme_tarihi']) : 'Güncellenmedi'; ?><br>
                            <strong>API E-posta:</strong> <?php echo htmlspecialchars($domain_bilgi['api_email']); ?><br>
                            <strong>API Anahtarı:</strong> <?php echo substr(htmlspecialchars($domain_bilgi['api_key']), 0, 10) . '...'; ?><br>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">DNS Kayıtları (Son 10)</h6>
            </div>
            <div class="card-body">
                <?php if(empty($dns_kayitlari)): ?>
                    <div class="alert alert-info">Bu domain için DNS kaydı bulunamadı.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Tip</th>
                                    <th>İsim</th>
                                    <th>İçerik</th>
                                    <th>TTL</th>
                                    <th>Proxy</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $limit = min(10, count($dns_kayitlari));
                                for($i = 0; $i < $limit; $i++): 
                                    $kayit = $dns_kayitlari[$i];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($kayit['tip']); ?></td>
                                    <td><?php echo htmlspecialchars($kayit['isim']); ?></td>
                                    <td><?php echo htmlspecialchars($kayit['icerik']); ?></td>
                                    <td><?php echo (int)$kayit['ttl']; ?></td>
                                    <td>
                                        <?php if($kayit['proxied'] == 1): ?>
                                            <span class="badge badge-success">Evet</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Hayır</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if(count($dns_kayitlari) > 10): ?>
                        <a href="dns-kayitlari.php?domain_id=<?php echo $domain_id; ?>" class="btn btn-primary btn-sm mt-3">Tüm DNS Kayıtlarını Görüntüle</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı yükle
include_once __DIR__ . '/../resources/templates/footer.php';
?> 