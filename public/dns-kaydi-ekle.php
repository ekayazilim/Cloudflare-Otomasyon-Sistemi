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
$cloudflare = new CloudflareAPI($api_anahtar['api_anahtari'], $api_anahtar['email']);

// Form gönderilmişse
$hata_mesaji = '';
$basari_mesaji = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad = Yardimci::post('dns_ad');
    $tip = Yardimci::post('dns_tip');
    $icerik = Yardimci::post('dns_icerik');
    $ttl = Yardimci::post('dns_ttl');
    $oncelik = Yardimci::post('dns_oncelik', 10);
    $proxy = Yardimci::post('dns_proxy', 0);
    
    // Validasyon
    if (empty($ad)) {
        $hata_mesaji = 'Kayıt adı gereklidir.';
    } elseif (empty($tip)) {
        $hata_mesaji = 'Kayıt tipi seçilmelidir.';
    } elseif (empty($icerik)) {
        $hata_mesaji = 'Kayıt içeriği gereklidir.';
    } elseif (empty($ttl)) {
        $hata_mesaji = 'TTL değeri gereklidir.';
    } elseif ($tip === 'MX' && empty($oncelik)) {
        $hata_mesaji = 'MX kaydı için öncelik değeri gereklidir.';
    } else {
        // Kayıt adını düzenle (domain adını içermiyorsa ekle)
        if (strpos($ad, $domain_bilgi['domain']) === false) {
            $ad = $ad . '.' . $domain_bilgi['domain'];
        }
        
        // DNS kaydı ekle
        $dns_veri = [
            'type' => $tip,
            'name' => $ad,
            'content' => $icerik,
            'ttl' => (int)$ttl,
            'proxied' => $proxy == 1
        ];
        
        // MX kaydı için öncelik ekle
        if ($tip === 'MX') {
            $dns_veri['priority'] = (int)$oncelik;
        }
        
        $dns_ekle_sonuc = $cloudflare->dnsKaydiEkle($domain_bilgi['zone_id'], $dns_veri);
        
        if (isset($dns_ekle_sonuc['success']) && $dns_ekle_sonuc['success']) {
            // Veritabanına DNS kaydı ekle
            $db_dns_veri = [
                'domain_id' => $domain_id,
                'record_id' => $dns_ekle_sonuc['result']['id'],
                'isim' => $ad,
                'tip' => $tip,
                'icerik' => $icerik,
                'ttl' => (int)$ttl,
                'oncelik' => $tip === 'MX' ? (int)$oncelik : 0,
                'proxied' => $proxy
            ];
            
            $dns_id = $domain->dnsKaydiEkle($db_dns_veri);
            
            if ($dns_id) {
                $basari_mesaji = 'DNS kaydı başarıyla eklendi.';
                
                // Yönlendirme için session mesajı ayarla
                $_SESSION['mesaj'] = 'DNS kaydı başarıyla eklendi.';
                $_SESSION['mesaj_tur'] = 'success';
                
                // DNS kayıtları sayfasına yönlendir
                Yardimci::yonlendir('dns-kayitlari.php?domain_id=' . $domain_id);
            } else {
                $hata_mesaji = 'DNS kaydı Cloudflare\'e eklendi ancak veritabanına eklenirken bir hata oluştu.';
            }
        } else {
            $hata_mesaji = 'DNS kaydı eklenirken bir hata oluştu: ' . 
                (isset($dns_ekle_sonuc['errors']) && is_array($dns_ekle_sonuc['errors']) && isset($dns_ekle_sonuc['errors'][0]['message']) ? $dns_ekle_sonuc['errors'][0]['message'] : 'Bilinmeyen hata');
        }
    }
}

// Header'ı yükle
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>DNS Kaydı Ekle: <?php echo $domain_bilgi['domain']; ?></h1>
            <a href="dns-kayitlari.php?domain_id=<?php echo $domain_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> DNS Kayıtlarına Dön
            </a>
        </div>
    </div>
</div>

<?php if (!empty($hata_mesaji)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php echo $hata_mesaji; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
</div>
<?php endif; ?>

<?php if (!empty($basari_mesaji)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo $basari_mesaji; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Yeni DNS Kaydı Ekle</h5>
    </div>
    <div class="card-body">
        <form method="post" action="">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="dns_ad" class="form-label">Kayıt Adı</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="dns_ad" name="dns_ad" placeholder="www" required>
                        <span class="input-group-text">.<?php echo $domain_bilgi['domain']; ?></span>
                    </div>
                    <div class="form-text">Kayıt adını girin. Ana domain için @ kullanın veya boş bırakın.</div>
                </div>
                <div class="col-md-6">
                    <label for="dns_tip" class="form-label">Kayıt Tipi</label>
                    <select class="form-select" id="dns_tip" name="dns_tip" required>
                        <option value="">Kayıt tipi seçin</option>
                        <option value="A">A (IPv4 Adresi)</option>
                        <option value="AAAA">AAAA (IPv6 Adresi)</option>
                        <option value="CNAME">CNAME (Canonical Name)</option>
                        <option value="MX">MX (Mail Exchange)</option>
                        <option value="TXT">TXT (Text)</option>
                        <option value="NS">NS (Name Server)</option>
                        <option value="SRV">SRV (Service)</option>
                        <option value="CAA">CAA (Certificate Authority Authorization)</option>
                    </select>
                    <div class="form-text">DNS kaydının tipini seçin.</div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="dns_icerik" class="form-label">İçerik</label>
                <input type="text" class="form-control" id="dns_icerik" name="dns_icerik" placeholder="192.168.1.1" required>
                <div class="form-text" id="content_help">Kayıt içeriğini girin. Kayıt tipine göre değişir.</div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="dns_ttl" class="form-label">TTL (Time To Live)</label>
                    <select class="form-select" id="dns_ttl" name="dns_ttl" required>
                        <option value="1">Otomatik</option>
                        <option value="60">1 dakika</option>
                        <option value="300">5 dakika</option>
                        <option value="600">10 dakika</option>
                        <option value="1800">30 dakika</option>
                        <option value="3600">1 saat</option>
                        <option value="7200">2 saat</option>
                        <option value="18000">5 saat</option>
                        <option value="43200">12 saat</option>
                        <option value="86400">1 gün</option>
                    </select>
                    <div class="form-text">DNS kaydının önbellek süresini belirler.</div>
                </div>
                <div class="col-md-6">
                    <label for="dns_oncelik" class="form-label">Öncelik (Sadece MX için)</label>
                    <input type="number" class="form-control" id="dns_oncelik" name="dns_oncelik" placeholder="10" value="10" min="0" max="65535">
                    <div class="form-text">MX kayıtları için öncelik değeri. Düşük değerler daha yüksek önceliğe sahiptir.</div>
                </div>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="dns_proxy" name="dns_proxy" value="1">
                <label class="form-check-label" for="dns_proxy">
                    Cloudflare Proxy Etkinleştir (Turuncu Bulut)
                </label>
                <div class="form-text">Etkinleştirilirse, trafik Cloudflare üzerinden geçer ve DDoS koruması, CDN gibi özelliklerden yararlanır.</div>
            </div>
            
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle"></i> DNS Kaydı Bilgileri</h6>
                <p>DNS kayıtları, domain adınızın internet üzerindeki hizmetlere nasıl yönlendirileceğini belirler:</p>
                <ul>
                    <li><strong>A Kaydı:</strong> Domain adını bir IPv4 adresine yönlendirir.</li>
                    <li><strong>CNAME Kaydı:</strong> Bir domain adını başka bir domain adına yönlendirir.</li>
                    <li><strong>MX Kaydı:</strong> E-posta sunucularını belirtir.</li>
                    <li><strong>TXT Kaydı:</strong> Metin bilgisi içerir, genellikle doğrulama için kullanılır.</li>
                </ul>
                <p class="mb-0">DNS değişiklikleri internet genelinde yayılması biraz zaman alabilir.</p>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> DNS Kaydı Ekle
                </button>
                <a href="dns-kayitlari.php?domain_id=<?php echo $domain_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> İptal
                </a>
            </div>
        </form>
    </div>
</div>

<?php
// Footer'ı yükle
include_once __DIR__ . '/../resources/templates/footer.php';
?>
