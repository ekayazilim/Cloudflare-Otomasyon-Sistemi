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

// CloudflareAPI'yi ayarla
$domain->setCloudflareAPI($api_anahtar['api_anahtari'] ?? '', $api_anahtar['email'] ?? '');

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
$cloudflare = new CloudflareAPI($api_anahtar['api_anahtari'] ?? '', $api_anahtar['email'] ?? '');

// Firewall kurallarını al
$firewall_kurallari = $domain->firewallKurallariniListele($domain_id);

// Eğer firewall kuralları boşsa, Cloudflare'den güncel kuralları çek
if (empty($firewall_kurallari)) {
    $senkronizasyon_sonuc = $domain->firewallKurallariniSenkronizeEt($domain_id, $domain_bilgi['zone_id']);
    
    if ($senkronizasyon_sonuc) {
        $_SESSION['mesaj'] = 'Firewall kuralları Cloudflare\'den başarıyla senkronize edildi.';
        $_SESSION['mesaj_tur'] = 'success';
        $firewall_kurallari = $domain->firewallKurallariniListele($domain_id);
    } else {
        $_SESSION['mesaj'] = 'Firewall kuralları senkronize edilirken bir hata oluştu.';
        $_SESSION['mesaj_tur'] = 'danger';
    }
}

// Header'ı yükle
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Firewall Kuralları: <?php echo $domain_bilgi['domain']; ?></h1>
            <div>
                <a href="firewall-kurali-ekle.php?domain_id=<?php echo $domain_id; ?>" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Yeni Firewall Kuralı Ekle
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
                        <td><?php echo $api_anahtar['email'] ?? ''; ?> (<?php echo substr($api_anahtar['api_anahtari'] ?? '', 0, 10); ?>...)</td>
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
                <h5 class="mb-0">Firewall Kuralları</h5>
            </div>
            <div class="col-md-6 text-end">
                <form action="firewall-kurallari-senkronize.php" method="post" class="d-inline">
                    <input type="hidden" name="domain_id" value="<?php echo $domain_id; ?>">
                    <button type="submit" class="btn btn-light btn-sm">
                        <i class="fas fa-sync-alt"></i> Senkronize Et
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($firewall_kurallari)): ?>
        <div class="alert alert-info">
            Bu domain için henüz firewall kuralı bulunmuyor. 
            <a href="firewall-kurali-ekle.php?domain_id=<?php echo $domain_id; ?>">Hemen ekleyin</a>.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Kural Adı</th>
                        <th>İçerik</th>
                        <th>Eylem</th>
                        <th>Öncelik</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($firewall_kurallari as $kural): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($kural['kural_adi']); ?></td>
                        <td>
                            <code><?php echo htmlspecialchars($kural['filtre_icerik']); ?></code>
                        </td>
                        <td>
                            <?php 
                            $eylem_renk = '';
                            switch($kural['eylem']) {
                                case 'allow': $eylem_renk = 'success'; break;
                                case 'block': $eylem_renk = 'danger'; break;
                                case 'challenge': $eylem_renk = 'warning'; break;
                                default: $eylem_renk = 'secondary';
                            }
                            ?>
                            <span class="badge bg-<?php echo $eylem_renk; ?>">
                                <?php echo strtoupper($kural['eylem']); ?>
                            </span>
                        </td>
                        <td><?php echo (int)$kural['oncelik']; ?></td>
                        <td>
                            <?php if ($kural['durum'] == 1): ?>
                            <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Pasif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="firewall-kurali-duzenle.php?id=<?php echo $kural['id']; ?>" class="btn btn-sm btn-warning" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="firewall-kurali-sil.php?id=<?php echo $kural['id']; ?>" class="btn btn-sm btn-danger delete-confirm" title="Sil">
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
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Silme işlemi onayı için
    const deleteButtons = document.querySelectorAll('.delete-confirm');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Bu firewall kuralını silmek istediğinize emin misiniz?')) {
                e.preventDefault();
            }
        });
    });
});
</script>

<?php
// Footer'ı yükle
include_once __DIR__ . '/../resources/templates/footer.php';
?> 