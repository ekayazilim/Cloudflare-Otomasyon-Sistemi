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
    exit;
}

// Domain ve API sınıf örneklerini oluştur
$domain = new Domain();
$db = App\Veritabani::baglan();

// IP parametresi kontrolü
$ip = isset($_GET['ip']) ? trim($_GET['ip']) : '';
$dns_tipi = isset($_GET['dns_tipi']) ? trim($_GET['dns_tipi']) : 'A';

// Sonuçları saklayan dizi
$sonuclar = [];
$hata_mesaji = '';

// IP adresi girilmiş ise sorgulama yap
if (!empty($ip)) {
    try {
        // Kullanıcının tüm domainlerini al
        $domainler = $domain->domainleriListele($kullanici_bilgileri['id']);
        
        // Her domain için DNS kayıtlarını kontrol et
        foreach ($domainler as $domain_bilgisi) {
            // API anahtarını al
            $api_anahtar = $kullanici->apiAnahtariGetir($domain_bilgisi['api_anahtar_id']);
            
            if (!$api_anahtar) {
                continue; // API anahtarı yoksa sonraki domain'e geç
            }
            
            // CloudflareAPI nesnesini oluştur
            $cloudflare = new CloudflareAPI($api_anahtar['api_anahtari'], $api_anahtar['email']);
            
            // DNS kayıtlarını al
            $dns_kayitlari = $cloudflare->dnsKayitlariniListele($domain_bilgisi['zone_id']);
            
            if (isset($dns_kayitlari['success']) && $dns_kayitlari['success']) {
                // Belirtilen IP'yi içeren DNS kayıtlarını bul
                $eslesen_kayitlar = [];
                
                foreach ($dns_kayitlari['result'] as $kayit) {
                    // Belirtilen DNS tipi ve IP eşleşiyorsa
                    if ($kayit['type'] === $dns_tipi && $kayit['content'] === $ip) {
                        $eslesen_kayitlar[] = [
                            'id' => $kayit['id'],
                            'name' => $kayit['name'],
                            'type' => $kayit['type'],
                            'content' => $kayit['content'],
                            'proxied' => $kayit['proxied'],
                            'ttl' => $kayit['ttl']
                        ];
                    }
                }
                
                // Eşleşen kayıt varsa sonuçlara ekle
                if (!empty($eslesen_kayitlar)) {
                    $sonuclar[] = [
                        'domain' => $domain_bilgisi,
                        'kayitlar' => $eslesen_kayitlar
                    ];
                }
            }
        }
    } catch (Exception $e) {
        $hata_mesaji = "Sorgulama sırasında bir hata oluştu: " . $e->getMessage();
    }
}

// Sayfa başlığı
$sayfa_basligi = 'IP Adresine Göre Domain Sorgulama';
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>IP Adresine Göre Domain Sorgulama</h1>
            <div>
                <a href="domainler.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Domainlere Dön
                </a>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0">IP Adresi ile Domain Sorgula</h5>
    </div>
    <div class="card-body">
        <form id="ipSorguForm" method="get" class="mb-4">
            <div class="row">
                <div class="col-md-5">
                    <div class="mb-3">
                        <label for="ip" class="form-label">IP Adresi</label>
                        <input type="text" class="form-control" id="ip" name="ip" required 
                               value="<?php echo htmlspecialchars($ip); ?>" placeholder="Örn: 192.168.1.1">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="dns_tipi" class="form-label">DNS Kaydı Tipi</label>
                        <select class="form-select" id="dns_tipi" name="dns_tipi">
                            <option value="A" <?php echo $dns_tipi === 'A' ? 'selected' : ''; ?>>A</option>
                            <option value="AAAA" <?php echo $dns_tipi === 'AAAA' ? 'selected' : ''; ?>>AAAA</option>
                            <option value="CNAME" <?php echo $dns_tipi === 'CNAME' ? 'selected' : ''; ?>>CNAME</option>
                            <option value="MX" <?php echo $dns_tipi === 'MX' ? 'selected' : ''; ?>>MX</option>
                            <option value="TXT" <?php echo $dns_tipi === 'TXT' ? 'selected' : ''; ?>>TXT</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3 d-flex align-items-end h-100">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Sorgula
                        </button>
                    </div>
                </div>
            </div>
        </form>
        
        <?php if (!empty($hata_mesaji)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $hata_mesaji; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($ip)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> <strong><?php echo htmlspecialchars($ip); ?></strong> IP adresini içeren <?php echo htmlspecialchars($dns_tipi); ?> DNS kayıtları aranıyor...
        </div>
        <?php endif; ?>
        
        <?php if (empty($sonuclar) && empty($hata_mesaji) && !empty($ip)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-circle"></i> Belirtilen IP adresine sahip domain bulunamadı.
        </div>
        <?php endif; ?>
        
        <?php if (!empty($sonuclar)): ?>
        <h4 class="mt-4">Sonuçlar</h4>
        <p><strong><?php echo count($sonuclar); ?></strong> domain içerisinde <strong><?php echo htmlspecialchars($ip); ?></strong> IP adresine sahip DNS kaydı bulundu.</p>
        
        <div class="table-responsive mt-3">
            <table class="table table-hover table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Domain</th>
                        <th>Kayıt Adı</th>
                        <th>Kayıt Tipi</th>
                        <th>TTL</th>
                        <th>Proxy</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sonuclar as $sonuc): ?>
                        <?php foreach ($sonuc['kayitlar'] as $kayit): ?>
                        <tr>
                            <td>
                                <a href="domain-detay.php?id=<?php echo $sonuc['domain']['id']; ?>">
                                    <?php echo htmlspecialchars($sonuc['domain']['domain']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($kayit['name']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo $kayit['type']; ?></span></td>
                            <td><?php echo $kayit['ttl'] == 1 ? 'Otomatik' : $kayit['ttl']; ?></td>
                            <td>
                                <?php if ($kayit['proxied']): ?>
                                <span class="badge bg-warning text-dark"><i class="fas fa-cloud"></i> Aktif</span>
                                <?php else: ?>
                                <span class="badge bg-light text-dark"><i class="fas fa-times"></i> Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="dns-kayitlari.php?domain_id=<?php echo $sonuc['domain']['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-server"></i> DNS Kayıtları
                                    </a>
                                    <a href="dns-kaydi-duzenle.php?domain_id=<?php echo $sonuc['domain']['id']; ?>&kayit_id=<?php echo $kayit['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i> Düzenle
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header bg-dark text-white">
        <h5 class="card-title mb-0">Toplu Değiştirme İşlemleri</h5>
    </div>
    <div class="card-body">
        <?php if (empty($sonuclar) || empty($ip)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Toplu değiştirme işlemleri için önce bir IP adresi sorgulayın.
        </div>
        <?php else: ?>
        <p>Bu IP adresini içeren tüm DNS kayıtlarını toplu olarak güncellemek için:</p>
        <div class="mb-3">
            <a href="dns-toplu-guncelle.php?kaynak_ip=<?php echo urlencode($ip); ?>&dns_tipi=<?php echo urlencode($dns_tipi); ?>" class="btn btn-success">
                <i class="fas fa-exchange-alt"></i> Toplu Güncelleme Sayfasına Git
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Footer'ı yükle
include_once __DIR__ . '/../resources/templates/footer.php';
?> 