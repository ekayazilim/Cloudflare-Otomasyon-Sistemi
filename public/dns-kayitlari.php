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
$domain_id = isset($_GET['domain_id']) ? (int)$_GET['domain_id'] : 0;

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

// API anahtarı bilgilerini al
$api_anahtar = $kullanici->apiAnahtariGetir($domain_bilgi['api_anahtar_id']);

if (!$api_anahtar) {
    $_SESSION['mesaj'] = 'Bu domain için API anahtarı bulunamadı.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('domainler.php');
}

// Cloudflare API örneği oluştur
$cloudflare = new CloudflareAPI($api_anahtar['api_key'] ?? '', $api_anahtar['email'] ?? '');

// DNS kayıtlarını al
$dns_kayitlari = $domain->dnsKayitlariniListele($domain_id);

// Eğer DNS kayıtları boşsa, Cloudflare'den güncel kayıtları çek
if (empty($dns_kayitlari)) {
    $cf_dns_kayitlari = $cloudflare->dnsKayitlariniListele($domain_bilgi['zone_id']);
    
    if (isset($cf_dns_kayitlari['success']) && $cf_dns_kayitlari['success'] && !empty($cf_dns_kayitlari['result'])) {
        foreach ($cf_dns_kayitlari['result'] as $kayit) {
            $dns_veri = [
                'domain_id' => $domain_id,
                'record_id' => $kayit['id'],
                'isim' => $kayit['name'],
                'tip' => $kayit['type'],
                'icerik' => $kayit['content'],
                'ttl' => $kayit['ttl'],
                'oncelik' => isset($kayit['priority']) ? $kayit['priority'] : 0,
                'proxied' => $kayit['proxied'] ? 1 : 0
            ];
            
            $domain->dnsKaydiEkle($dns_veri);
        }
        
        // Yeniden listele
        $dns_kayitlari = $domain->dnsKayitlariniListele($domain_id);
    }
}

// Header'ı yükle
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>DNS Kayıtları: <?php echo $domain_bilgi['domain']; ?></h1>
            <div>
                <a href="dns-kaydi-ekle.php?domain_id=<?php echo $domain_id; ?>" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Yeni DNS Kaydı Ekle
                </a>
                <a href="domainler.php" class="btn btn-secondary ms-2">
                    <i class="fas fa-arrow-left"></i> Domainlere Dön
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['mesaj'])): ?>
<div class="alert alert-<?php echo $_SESSION['mesaj_tur']; ?> alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['mesaj']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
</div>
<?php unset($_SESSION['mesaj'], $_SESSION['mesaj_tur']); endif; ?>

<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Domain Bilgileri</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-bordered">
                    <tr>
                        <th>Domain Adı:</th>
                        <td><?php echo $domain_bilgi['domain']; ?></td>
                    </tr>
                    <tr>
                        <th>SSL Modu:</th>
                        <td>
                            <span class="ssl-badge ssl-<?php echo strtolower($domain_bilgi['ssl_modu']); ?>">
                                <?php echo strtoupper($domain_bilgi['ssl_modu']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Durum:</th>
                        <td>
                            <?php if ($domain_bilgi['durum'] == 1): ?>
                            <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Pasif</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-bordered">
                    <tr>
                        <th>Zone ID:</th>
                        <td><code><?php echo $domain_bilgi['zone_id']; ?></code></td>
                    </tr>
                    <tr>
                        <th>API Anahtarı:</th>
                        <td><?php echo isset($api_anahtar['aciklama']) ? $api_anahtar['aciklama'] : 'API Anahtarı #'.$api_anahtar['id']; ?></td>
                    </tr>
                    <tr>
                        <th>Eklenme Tarihi:</th>
                        <td><?php echo Yardimci::tarihFormat($domain_bilgi['olusturma_tarihi']); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0">DNS Kayıtları</h5>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-light btn-sm" id="yenile-btn" onclick="location.reload();">
                    <i class="fas fa-sync-alt"></i> Yenile
                </button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($dns_kayitlari)): ?>
        <div class="alert alert-info">
            Bu domain için henüz DNS kaydı bulunmuyor. 
            <a href="dns-kaydi-ekle.php?domain_id=<?php echo $domain_id; ?>">Hemen ekleyin</a>.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Kayıt Adı</th>
                        <th>Tip</th>
                        <th>İçerik</th>
                        <th>TTL</th>
                        <th>Öncelik</th>
                        <th>Proxy</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dns_kayitlari as $kayit): ?>
                    <tr>
                        <td><?php echo isset($kayit['isim']) ? $kayit['isim'] : $kayit['name']; ?></td>
                        <td>
                            <span class="badge bg-secondary"><?php echo $kayit['tip']; ?></span>
                        </td>
                        <td>
                            <code><?php echo $kayit['icerik']; ?></code>
                        </td>
                        <td>
                            <?php if ($kayit['ttl'] == 1): ?>
                            <span class="badge bg-info">Otomatik</span>
                            <?php else: ?>
                            <?php echo $kayit['ttl']; ?> sn
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($kayit['tip'] == 'MX'): ?>
                            <?php echo isset($kayit['oncelik']) ? $kayit['oncelik'] : '-'; ?>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (isset($kayit['proxied']) && $kayit['proxied'] == 1): ?>
                            <span class="badge bg-success">Açık</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Kapalı</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="dns-kaydi-duzenle.php?id=<?php echo $kayit['id']; ?>" class="btn btn-sm btn-warning" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="dns-kaydi-sil.php?id=<?php echo $kayit['id']; ?>" class="btn btn-sm btn-danger delete-confirm" title="Sil">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-footer">
        <div class="d-flex justify-content-between align-items-center">
            <span>Toplam <?php echo count($dns_kayitlari); ?> DNS kaydı</span>
            <a href="dns-kaydi-ekle.php?domain_id=<?php echo $domain_id; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus-circle"></i> Yeni DNS Kaydı Ekle
            </a>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0">DNS Kayıt Tipleri</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0">A Kaydı</h6>
                    </div>
                    <div class="card-body">
                        <p>Domain adını bir IPv4 adresine yönlendirir.</p>
                        <p><strong>Örnek:</strong> example.com -> 192.168.1.1</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0">AAAA Kaydı</h6>
                    </div>
                    <div class="card-body">
                        <p>Domain adını bir IPv6 adresine yönlendirir.</p>
                        <p><strong>Örnek:</strong> example.com -> 2001:0db8:85a3:0000:0000:8a2e:0370:7334</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0">CNAME Kaydı</h6>
                    </div>
                    <div class="card-body">
                        <p>Bir domain adını başka bir domain adına yönlendirir.</p>
                        <p><strong>Örnek:</strong> www.example.com -> example.com</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0">MX Kaydı</h6>
                    </div>
                    <div class="card-body">
                        <p>E-posta sunucularını belirtir. Öncelik değeri önemlidir.</p>
                        <p><strong>Örnek:</strong> example.com -> mail.example.com (Öncelik: 10)</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0">TXT Kaydı</h6>
                    </div>
                    <div class="card-body">
                        <p>Metin bilgisi içerir. Genellikle SPF, DKIM gibi e-posta doğrulama için kullanılır.</p>
                        <p><strong>Örnek:</strong> example.com -> "v=spf1 include:_spf.example.com ~all"</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0">NS Kaydı</h6>
                    </div>
                    <div class="card-body">
                        <p>Domain için yetkili isim sunucularını belirtir.</p>
                        <p><strong>Örnek:</strong> example.com -> ns1.example.com</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı yükle
include_once __DIR__ . '/../resources/templates/footer.php';
?>
