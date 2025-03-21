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

// Domain sınıfı örneği oluştur
$domain = new Domain();

// Form gönderilmişse
$hata_mesaji = '';
$basari_mesaji = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $domain_adi = Yardimci::post('domain');
    $ssl_modu = Yardimci::post('ssl_modu');
    $api_anahtar_id = Yardimci::post('api_anahtar_id');
    
    // Validasyon
    if (empty($domain_adi)) {
        $hata_mesaji = 'Domain adı gereklidir.';
    } elseif (empty($ssl_modu)) {
        $hata_mesaji = 'SSL modu seçilmelidir.';
    } elseif (empty($api_anahtar_id)) {
        $hata_mesaji = 'API anahtarı seçilmelidir.';
    } else {
        // API anahtarı bilgilerini al
        $api_anahtar = $kullanici->apiAnahtariniGetir($api_anahtar_id);
        
        if (!$api_anahtar) {
            $hata_mesaji = 'Geçersiz API anahtarı.';
        } else {
            // Domain sınıfına API anahtarı bilgilerini ayarla
            $domain->setCloudflareAPI($api_anahtar['api_anahtari'], $api_anahtar['email']);
            
            // Domain ekle
            $domain_veri = [
                'kullanici_id' => $kullanici_bilgileri['id'],
                'domain' => $domain_adi,
                'ssl_modu' => $ssl_modu,
                'api_anahtar_id' => $api_anahtar_id,
                'durum' => 1
            ];
            
            try {
                $domain_id = $domain->domainEkle($domain_veri);
                
                if ($domain_id) {
                    $basari_mesaji = 'Domain başarıyla eklendi.';
                    
                    // Yönlendirme için session mesajı ayarla
                    $_SESSION['mesaj'] = 'Domain başarıyla eklendi.';
                    $_SESSION['mesaj_tur'] = 'success';
                    
                    // Domainler sayfasına yönlendir
                    Yardimci::yonlendir('domainler.php');
                } else {
                    $hata_mesaji = 'Domain veritabanına eklenirken bir hata oluştu.';
                }
            } catch (\Exception $e) {
                $hata_mesaji = 'Domain eklenirken bir hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

// API anahtarlarını al
$api_anahtarlari = $kullanici->apiAnahtarlariniListele($kullanici_bilgileri['id']);

// Header'ı yükle
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Domain Ekle</h1>
            <a href="domainler.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Domainlere Dön
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
        <h5 class="mb-0">Yeni Domain Ekle</h5>
    </div>
    <div class="card-body">
        <?php if (empty($api_anahtarlari)): ?>
        <div class="alert alert-warning">
            Domain eklemek için önce bir API anahtarı eklemelisiniz. 
            <a href="api-anahtari-ekle.php" class="alert-link">API Anahtarı Ekle</a>
        </div>
        <?php else: ?>
        <form method="post" action="">
            <div class="mb-3">
                <label for="domain" class="form-label">Domain Adı</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-globe"></i></span>
                    <input type="text" class="form-control" id="domain" name="domain" placeholder="example.com" required>
                </div>
                <div class="form-text">Domain adını www olmadan girin (örn: example.com).</div>
            </div>
            
            <div class="mb-3">
                <label for="ssl_modu" class="form-label">SSL Modu</label>
                <select class="form-select" id="ssl_modu" name="ssl_modu" required>
                    <option value="">SSL modu seçin</option>
                    <option value="off">Kapalı</option>
                    <option value="flexible">Esnek</option>
                    <option value="full">Tam</option>
                    <option value="strict">Tam Katı</option>
                </select>
                <div class="form-text">SSL modunu seçin. Tam Katı mod en güvenli seçenektir.</div>
                
                <div class="mt-2">
                    <div id="ssl_preview" class="ssl-badge ssl-full">FULL</div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="api_anahtar_id" class="form-label">API Anahtarı</label>
                <select class="form-select" id="api_anahtar_id" name="api_anahtar_id" required>
                    <option value="">API anahtarı seçin</option>
                    <?php foreach ($api_anahtarlari as $anahtar): ?>
                    <option value="<?php echo $anahtar['id']; ?>">
                        <?php echo $anahtar['aciklama']; ?> (<?php echo substr($anahtar['api_anahtari'], 0, 5) . '...' . substr($anahtar['api_anahtari'], -5); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Domain için kullanılacak Cloudflare API anahtarını seçin.</div>
            </div>
            
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle"></i> Bilgi</h6>
                <p>Domain eklemek için aşağıdaki adımları takip edin:</p>
                <ol>
                    <li>Domain adını doğru bir şekilde girin (www olmadan).</li>
                    <li>Uygun SSL modunu seçin.</li>
                    <li>Cloudflare hesabınıza bağlı bir API anahtarı seçin.</li>
                </ol>
                <p>Domain eklendikten sonra, DNS kayıtlarını ve diğer ayarları yapılandırabilirsiniz.</p>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Domain Ekle
                </button>
                <a href="domainler.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> İptal
                </a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php
// Footer'ı yükle
include_once __DIR__ . '/../resources/templates/footer.php';
?>
