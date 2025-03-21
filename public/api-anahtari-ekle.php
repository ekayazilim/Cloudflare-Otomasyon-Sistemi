<?php
// Oturum başlat
session_start();

// Konfigürasyon dosyalarını yükle
$config = require_once __DIR__ . '/../config/uygulama.php';

// Gerekli sınıfları yükle
require_once __DIR__ . '/../app/Veritabani.php';
require_once __DIR__ . '/../app/Kullanici.php';
require_once __DIR__ . '/../app/CloudflareAPI.php';
require_once __DIR__ . '/../app/Yardimci.php';

use App\Kullanici;
use App\CloudflareAPI;
use App\Yardimci;

// Oturum kontrolü
$kullanici = new Kullanici();
$kullanici_bilgileri = $kullanici->oturumKontrol();

if (!$kullanici_bilgileri) {
    Yardimci::yonlendir('giris.php');
}

// Form gönderilmişse
$hata_mesaji = '';
$basari_mesaji = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad = Yardimci::post('ad');
    $email = Yardimci::post('email');
    $api_key = Yardimci::post('api_key');
    
    // Validasyon
    if (empty($ad)) {
        $hata_mesaji = 'API anahtarı adı gereklidir.';
    } elseif (empty($email)) {
        $hata_mesaji = 'E-posta adresi gereklidir.';
    } elseif (empty($api_key)) {
        $hata_mesaji = 'API anahtarı gereklidir.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hata_mesaji = 'Geçerli bir e-posta adresi giriniz.';
    } else {
        // API anahtarının geçerliliğini kontrol et
        $cloudflare = new CloudflareAPI($api_key, $email);
        $kontrol_sonuc = $cloudflare->apiAnahtariKontrol();
        
        if ($kontrol_sonuc) {
            // API anahtarı ekle
            $api_anahtar_veri = [
                'kullanici_id' => $kullanici_bilgileri['id'],
                'api_anahtari' => $api_key,
                'email' => $email,
                'aciklama' => $ad,
                'durum' => 1
            ];
            
            $api_anahtar_id = $kullanici->apiAnahtariEkle($api_anahtar_veri);
            
            if ($api_anahtar_id) {
                $basari_mesaji = 'API anahtarı başarıyla eklendi.';
                
                // Yönlendirme için session mesajı ayarla
                $_SESSION['mesaj'] = 'API anahtarı başarıyla eklendi.';
                $_SESSION['mesaj_tur'] = 'success';
                
                // API anahtarları sayfasına yönlendir
                Yardimci::yonlendir('api-anahtarlari.php');
            } else {
                $hata_mesaji = 'API anahtarı veritabanına eklenirken bir hata oluştu.';
            }
        } else {
            $hata_mesaji = 'Geçersiz API anahtarı veya e-posta adresi. Lütfen bilgilerinizi kontrol edip tekrar deneyin.';
        }
    }
}

// Header'ı yükle
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>API Anahtarı Ekle</h1>
            <a href="api-anahtarlari.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> API Anahtarlarına Dön
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
        <h5 class="mb-0">Yeni API Anahtarı Ekle</h5>
    </div>
    <div class="card-body">
        <form method="post" action="">
            <div class="mb-3">
                <label for="ad" class="form-label">API Anahtarı Adı</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-tag"></i></span>
                    <input type="text" class="form-control" id="ad" name="ad" placeholder="Örn: Ana Cloudflare Hesabı" required>
                </div>
                <div class="form-text">Bu API anahtarını tanımlayan bir isim girin.</div>
            </div>
            
            <div class="mb-3">
                <label for="email" class="form-label">Cloudflare E-posta Adresi</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Cloudflare hesabınızın e-posta adresi" required>
                </div>
                <div class="form-text">Cloudflare hesabınızın e-posta adresini girin.</div>
            </div>
            
            <div class="mb-3">
                <label for="api_key" class="form-label">API Anahtarı</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                    <input type="password" class="form-control" id="api_key" name="api_key" placeholder="Cloudflare API anahtarınız" required>
                    <button class="btn btn-outline-secondary toggle-api-key" type="button" data-target="api_key">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="form-text">Cloudflare hesabınızdan aldığınız API anahtarını girin.</div>
            </div>
            
            <div class="alert alert-info">
                <h6><i class="fas fa-info-circle"></i> API Anahtarı Nasıl Alınır?</h6>
                <ol>
                    <li>Cloudflare hesabınıza giriş yapın.</li>
                    <li>Sağ üst köşedeki profil ikonuna tıklayın ve "Profil" seçeneğini seçin.</li>
                    <li>"API Tokens" sekmesine geçin.</li>
                    <li>"Create Token" butonuna tıklayın.</li>
                    <li>"Edit zone DNS" template'ini seçin ve gerekli izinleri verin.</li>
                    <li>Token'ı oluşturun ve kopyalayın.</li>
                </ol>
                <p class="mb-0">API anahtarınız güvenli bir şekilde saklanacaktır.</p>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> API Anahtarı Ekle
                </button>
                <a href="api-anahtarlari.php" class="btn btn-secondary">
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
