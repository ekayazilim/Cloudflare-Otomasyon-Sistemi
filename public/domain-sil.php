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

// Form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['onay']) && $_POST['onay'] === 'evet') {
    // Domain silinecek
    
    // Önce ilişkili kayıtları silme
    $domain->dnsKayitlariniSil($domain_id);
    $domain->firewallKurallariniSil($domain_id);
    
    // Domain'i sil
    $silme_sonuc = $domain->domainSil($domain_id);
    
    if ($silme_sonuc) {
        $_SESSION['mesaj'] = 'Domain ve ilişkili tüm kayıtlar başarıyla silindi.';
        $_SESSION['mesaj_tur'] = 'success';
    } else {
        $_SESSION['mesaj'] = 'Domain silinirken bir hata oluştu.';
        $_SESSION['mesaj_tur'] = 'danger';
    }
    
    Yardimci::yonlendir('domainler.php');
}

// Header'ı yükle
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Domain Silme İşlemi</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-triangle"></i> Uyarı!</h5>
                    <p>
                        <strong><?php echo htmlspecialchars($domain_bilgi['domain']); ?></strong> domainini ve ona bağlı tüm kayıtları (DNS kayıtları, firewall kuralları vb.) silmek üzeresiniz. 
                        Bu işlem geri alınamaz.
                    </p>
                    <p>
                        Bu işlem sadece yönetim panelindeki kayıtları siler, Cloudflare'deki gerçek domainleri veya kayıtları silmez.
                    </p>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        Domain Bilgileri
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th>Domain:</th>
                                <td><?php echo htmlspecialchars($domain_bilgi['domain']); ?></td>
                            </tr>
                            <tr>
                                <th>Zone ID:</th>
                                <td><?php echo htmlspecialchars($domain_bilgi['zone_id']); ?></td>
                            </tr>
                            <tr>
                                <th>Plan:</th>
                                <td><?php echo htmlspecialchars($domain_bilgi['plan']); ?></td>
                            </tr>
                            <tr>
                                <th>SSL Modu:</th>
                                <td><?php echo htmlspecialchars($domain_bilgi['ssl_modu']); ?></td>
                            </tr>
                            <tr>
                                <th>Oluşturma Tarihi:</th>
                                <td><?php echo htmlspecialchars($domain_bilgi['olusturma_tarihi']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <form action="domain-sil.php?id=<?php echo $domain_id; ?>" method="post">
                    <div class="form-group mb-3">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="onay-checkbox" required>
                            <label class="custom-control-label" for="onay-checkbox">
                                Bu domain ve ilişkili tüm kayıtları silmek istediğimi onaylıyorum.
                            </label>
                        </div>
                    </div>
                    
                    <input type="hidden" name="onay" value="evet">
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-danger" id="sil-btn" disabled>
                            <i class="fas fa-trash"></i> Domaini Sil
                        </button>
                        <a href="domain-detay.php?id=<?php echo $domain_id; ?>" class="btn btn-secondary ml-2">
                            <i class="fas fa-arrow-left"></i> İptal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const onayCheckbox = document.getElementById('onay-checkbox');
    const silBtn = document.getElementById('sil-btn');
    
    onayCheckbox.addEventListener('change', function() {
        silBtn.disabled = !this.checked;
    });
    
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!onayCheckbox.checked) {
            e.preventDefault();
            alert('Silme işlemini onaylamanız gerekmektedir.');
            return false;
        }
        
        return confirm('Bu domain ve ilişkili tüm kayıtları silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.');
    });
});
</script>

<?php
// Footer'ı yükle
include_once __DIR__ . '/../resources/templates/footer.php';
?> 