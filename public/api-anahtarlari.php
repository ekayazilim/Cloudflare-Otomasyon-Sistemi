<?php
// Oturum başlat
session_start();

// Konfigürasyon dosyalarını yükle
$config = require_once __DIR__ . '/../config/uygulama.php';

// Gerekli sınıfları yükle
require_once __DIR__ . '/../app/Veritabani.php';
require_once __DIR__ . '/../app/Kullanici.php';
require_once __DIR__ . '/../app/Yardimci.php';

use App\Kullanici;
use App\Yardimci;

// Oturum kontrolü
$kullanici = new Kullanici();
$kullanici_bilgileri = $kullanici->oturumKontrol();

if (!$kullanici_bilgileri) {
    Yardimci::yonlendir('giris.php');
}

// API anahtarlarını listele
$api_anahtarlari = $kullanici->apiAnahtarlariniListele($kullanici_bilgileri['id']);

// Header'ı yükle
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>API Anahtarları</h1>
            <a href="api-anahtari-ekle.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Yeni API Anahtarı Ekle
            </a>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['mesaj'])): ?>
<div class="alert alert-<?php echo $_SESSION['mesaj_tur']; ?> alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['mesaj']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
</div>
<?php unset($_SESSION['mesaj'], $_SESSION['mesaj_tur']); endif; ?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">API Anahtarı Listesi</h5>
    </div>
    <div class="card-body">
        <?php if (empty($api_anahtarlari)): ?>
        <div class="alert alert-info">
            Henüz API anahtarı eklenmemiş. <a href="api-anahtari-ekle.php">Hemen ekleyin</a>.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Açıklama</th>
                        <th>E-posta</th>
                        <th>API Anahtarı</th>
                        <th>Durum</th>
                        <th>Eklenme Tarihi</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($api_anahtarlari as $anahtar): ?>
                    <tr>
                        <td><?php echo $anahtar['id']; ?></td>
                        <td><?php echo $anahtar['aciklama']; ?></td>
                        <td><?php echo $anahtar['email']; ?></td>
                        <td>
                            <div class="input-group">
                                <input type="password" class="form-control form-control-sm" value="<?php echo $anahtar['api_anahtari']; ?>" id="api_key_<?php echo $anahtar['id']; ?>" readonly>
                                <button class="btn btn-sm btn-outline-secondary toggle-api-key" type="button" data-target="api_key_<?php echo $anahtar['id']; ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </td>
                        <td>
                            <?php if ($anahtar['durum'] == 1): ?>
                            <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Pasif</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo Yardimci::tarihFormat($anahtar['olusturma_tarihi']); ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="api-anahtari-duzenle.php?id=<?php echo $anahtar['id']; ?>" class="btn btn-sm btn-warning" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="api-anahtari-sil.php?id=<?php echo $anahtar['id']; ?>" class="btn btn-sm btn-danger delete-confirm" title="Sil">
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
            <span>Toplam <?php echo count($api_anahtarlari); ?> API anahtarı</span>
            <a href="api-anahtari-ekle.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus-circle"></i> Yeni API Anahtarı Ekle
            </a>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">API Anahtarı Bilgileri</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <h6><i class="fas fa-info-circle"></i> API Anahtarı Nedir?</h6>
            <p>API anahtarı, Cloudflare hesabınıza programatik erişim sağlayan güvenlik anahtarıdır. Bu anahtar sayesinde:</p>
            <ul>
                <li>Domain ekleyebilir ve yönetebilirsiniz</li>
                <li>DNS kayıtlarını düzenleyebilirsiniz</li>
                <li>SSL ayarlarını yapılandırabilirsiniz</li>
                <li>Firewall kurallarını yönetebilirsiniz</li>
            </ul>
            <p class="mb-0"><strong>Güvenlik Uyarısı:</strong> API anahtarınızı kimseyle paylaşmayın. Bu anahtar, hesabınıza tam erişim sağlar.</p>
        </div>
        
        <div class="alert alert-warning">
            <h6><i class="fas fa-exclamation-triangle"></i> API Anahtarı İzinleri</h6>
            <p>API anahtarınızın aşağıdaki izinlere sahip olduğundan emin olun:</p>
            <ul>
                <li>Zone.Zone: Read</li>
                <li>Zone.DNS: Edit</li>
                <li>Zone.SSL and Certificates: Edit</li>
                <li>Zone.Firewall Services: Edit</li>
            </ul>
            <p class="mb-0">Bu izinler olmadan, bazı işlevler çalışmayabilir.</p>
        </div>
    </div>
</div>

<?php
// Footer'ı yükle
include_once __DIR__ . '/../resources/templates/footer.php';
?>
