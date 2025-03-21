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

// Domain ID'sini al
$domain_id = isset($_GET['domain_id']) ? (int)$_GET['domain_id'] : 0;

if ($domain_id <= 0) {
    $_SESSION['mesaj'] = 'Geçersiz domain ID.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('domainler.php');
    exit;
}

// Domain sınıfı örneği oluştur
$domain = new Domain();

// Domain bilgilerini al
$domain_bilgi = $domain->domainGetir($domain_id, $kullanici_bilgileri['id']);

if (!$domain_bilgi) {
    $_SESSION['mesaj'] = 'Domain bulunamadı veya bu domain size ait değil.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('domainler.php');
    exit;
}

// API anahtarı bilgilerini al
$api_anahtar = $kullanici->apiAnahtariGetir($domain_bilgi['api_anahtar_id']);

if (!$api_anahtar) {
    $_SESSION['mesaj'] = 'Bu domain için API anahtarı bulunamadı.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('domainler.php');
    exit;
}

// CloudflareAPI'yi ayarla
$cloudflare = new CloudflareAPI($api_anahtar['api_anahtari'] ?? '', $api_anahtar['email'] ?? '');

// Form gönderildi mi kontrol et
$hata_mesaji = '';
$basari_mesaji = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $aciklama = $_POST['aciklama'] ?? '';
    $filtre_ifade = $_POST['filtre_ifade'] ?? '';
    $filtre_deger = $_POST['filtre_deger'] ?? '';
    $aksiyon = $_POST['aksiyon'] ?? '';
    $oncelik = isset($_POST['oncelik']) ? (int)$_POST['oncelik'] : 1;
    
    // Form verilerini doğrula
    if (empty($aciklama) || empty($filtre_ifade) || empty($filtre_deger) || empty($aksiyon)) {
        $hata_mesaji = 'Tüm alanları doldurun.';
    } else {
        // Filtre oluştur
        $filtre = [
            'expression' => $filtre_ifade,
            'value' => $filtre_deger
        ];
        
        // Firewall kuralı ekle
        $sonuc = $cloudflare->firewallKuraliEkle(
            $domain_bilgi['zone_id'],
            $aciklama,
            $filtre,
            $aksiyon,
            $oncelik
        );
        
        if (isset($sonuc['success']) && $sonuc['success']) {
            // Başarılı - kullanıcıyı yönlendir
            $_SESSION['mesaj'] = 'Firewall kuralı başarıyla eklendi.';
            $_SESSION['mesaj_tur'] = 'success';
            
            // Kuralları senkronize etmek için domain sınıfını hazırla
            $domain->setCloudflareAPI($api_anahtar['api_anahtari'] ?? '', $api_anahtar['email'] ?? '');
            $domain->firewallKurallariniSenkronizeEt($domain_id, $domain_bilgi['zone_id']);
            
            Yardimci::yonlendir('firewall-kurallari.php?domain_id=' . $domain_id);
            exit;
        } else {
            // Hata oluştu
            $hata_mesaji = 'Firewall kuralı eklenirken bir hata oluştu: ' . 
                (isset($sonuc['errors']) && isset($sonuc['errors'][0]['message']) ? 
                $sonuc['errors'][0]['message'] : 'Bilinmeyen hata');
        }
    }
}

// Sayfa başlığı ve header kısmını ekle
$sayfa_basligi = 'Firewall Kuralı Ekle: ' . $domain_bilgi['domain'];
include_once __DIR__ . '/../resources/templates/header.php';

// Eğer oturum mesajı varsa göster
if (isset($_SESSION['mesaj'])) {
    echo '<div class="alert alert-' . $_SESSION['mesaj_tur'] . ' alert-dismissible fade show" role="alert">';
    echo $_SESSION['mesaj'];
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>';
    echo '</div>';
    
    // Mesajı gösterdikten sonra session'dan kaldır
    unset($_SESSION['mesaj']);
    unset($_SESSION['mesaj_tur']);
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Firewall Kuralı Ekle: <?php echo $domain_bilgi['domain']; ?></h1>
            <div>
                <a href="firewall-kurallari.php?domain_id=<?php echo $domain_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Firewall Kurallarına Dön
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($hata_mesaji)): ?>
<div class="alert alert-danger" role="alert">
    <?php echo $hata_mesaji; ?>
</div>
<?php endif; ?>

<?php if (!empty($basari_mesaji)): ?>
<div class="alert alert-success" role="alert">
    <?php echo $basari_mesaji; ?>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Yeni Firewall Kuralı</h5>
    </div>
    <div class="card-body">
        <form method="post" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="aciklama" class="form-label">Kural Açıklaması:</label>
                    <input type="text" class="form-control" id="aciklama" name="aciklama" required>
                    <div class="form-text">Bu kuralın ne için olduğunu açıklayan bir metin girin.</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="aksiyon" class="form-label">Aksiyon:</label>
                    <select class="form-select" id="aksiyon" name="aksiyon" required>
                        <option value="">Aksiyon Seçin</option>
                        <option value="block">Engelle (Block)</option>
                        <option value="challenge">Challenge</option>
                        <option value="allow">İzin Ver (Allow)</option>
                        <option value="js_challenge">JavaScript Challenge</option>
                        <option value="managed_challenge">Managed Challenge</option>
                    </select>
                    <div class="form-text">Kural eşleştiğinde uygulanacak eylemi seçin.</div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="filtre_ifade" class="form-label">Filtre İfadesi:</label>
                    <input type="text" class="form-control" id="filtre_ifade" name="filtre_ifade" required
                           placeholder="ip.src eq 192.168.1.0">
                    <div class="form-text">Örnek: ip.src eq 192.168.1.0, http.request.uri.path contains wp-login.php</div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="filtre_deger" class="form-label">Filtre Değeri:</label>
                    <input type="text" class="form-control" id="filtre_deger" name="filtre_deger" required
                           placeholder="Filtre için ek değer">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="oncelik" class="form-label">Öncelik:</label>
                    <input type="number" class="form-control" id="oncelik" name="oncelik" min="1" value="1" required>
                    <div class="form-text">Düşük değerler daha yüksek önceliğe sahiptir (1 en yüksek öncelik).</div>
                </div>
            </div>
            
            <div class="mb-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Kuralı Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<?php include_once __DIR__ . '/../resources/templates/footer.php'; ?> 