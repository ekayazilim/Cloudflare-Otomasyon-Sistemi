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

// Sadece admin kullanıcılar erişebilir
if ($kullanici_bilgileri['yetki'] !== 'admin') {
    $_SESSION['mesaj'] = 'Bu sayfaya erişim yetkiniz bulunmamaktadır.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('index.php');
}

// Sayfalama için parametreler
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
$limit = $config['sayfa_basina_kayit'] ?? 15;
$offset = ($sayfa - 1) * $limit;

// Arama parametresi
$arama = isset($_GET['arama']) ? Yardimci::temizle($_GET['arama']) : '';

// Kullanıcıları listele
$kullanicilar = $kullanici->kullanicilariListele($limit, $offset, $arama);

// Toplam kullanıcı sayısı
$toplam_kullanici = $kullanici->kullaniciSayisi($arama);
$toplam_sayfa = ceil($toplam_kullanici / $limit);

// Header'ı yükle
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Kullanıcılar</h1>
            <a href="kullanici-ekle.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Yeni Kullanıcı Ekle
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
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0">Kullanıcı Listesi</h5>
            </div>
            <div class="col-md-6">
                <form action="" method="get" class="d-flex">
                    <input type="text" name="arama" class="form-control" placeholder="Kullanıcı ara..." value="<?php echo $arama; ?>">
                    <button type="submit" class="btn btn-light ms-2">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($kullanicilar)): ?>
        <div class="alert alert-info">
            <?php if (!empty($arama)): ?>
            "<?php echo $arama; ?>" aramasına uygun kullanıcı bulunamadı.
            <?php else: ?>
            Henüz kullanıcı eklenmemiş. <a href="kullanici-ekle.php">Hemen ekleyin</a>.
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kullanıcı Adı</th>
                        <th>Ad Soyad</th>
                        <th>Email</th>
                        <th>Yetki</th>
                        <th>Durum</th>
                        <th>Son Giriş</th>
                        <th>Kayıt Tarihi</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kullanicilar as $k): ?>
                    <tr>
                        <td><?php echo $k['id']; ?></td>
                        <td><?php echo $k['kullanici_adi']; ?></td>
                        <td><?php echo $k['ad'] . ' ' . $k['soyad']; ?></td>
                        <td><?php echo $k['email']; ?></td>
                        <td>
                            <?php if ($k['yetki'] == 'admin'): ?>
                            <span class="badge bg-danger">Admin</span>
                            <?php else: ?>
                            <span class="badge bg-info">Kullanıcı</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($k['durum'] == 1): ?>
                            <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Pasif</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $k['son_giris'] ? Yardimci::tarihFormat($k['son_giris']) : '-'; ?></td>
                        <td><?php echo Yardimci::tarihFormat($k['olusturma_tarihi']); ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="kullanici-duzenle.php?id=<?php echo $k['id']; ?>" class="btn btn-sm btn-warning" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($k['id'] != $kullanici_bilgileri['id']): ?>
                                <a href="kullanici-sil.php?id=<?php echo $k['id']; ?>" class="btn btn-sm btn-danger delete-confirm" title="Sil">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($toplam_sayfa > 1): ?>
        <nav aria-label="Sayfalama">
            <ul class="pagination justify-content-center">
                <?php if ($sayfa > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?sayfa=<?php echo $sayfa - 1; ?><?php echo !empty($arama) ? '&arama=' . $arama : ''; ?>">
                        <i class="fas fa-chevron-left"></i> Önceki
                    </a>
                </li>
                <?php endif; ?>
                
                <?php for ($i = max(1, $sayfa - 2); $i <= min($toplam_sayfa, $sayfa + 2); $i++): ?>
                <li class="page-item <?php echo $i == $sayfa ? 'active' : ''; ?>">
                    <a class="page-link" href="?sayfa=<?php echo $i; ?><?php echo !empty($arama) ? '&arama=' . $arama : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <?php if ($sayfa < $toplam_sayfa): ?>
                <li class="page-item">
                    <a class="page-link" href="?sayfa=<?php echo $sayfa + 1; ?><?php echo !empty($arama) ? '&arama=' . $arama : ''; ?>">
                        Sonraki <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="card-footer">
        <div class="d-flex justify-content-between align-items-center">
            <span>Toplam <?php echo $toplam_kullanici; ?> kullanıcı</span>
            <a href="kullanici-ekle.php" class="btn btn-primary btn-sm">
                <i class="fas fa-user-plus"></i> Yeni Kullanıcı Ekle
            </a>
        </div>
    </div>
</div>

<!-- Footer'ı yükle -->
<?php include_once __DIR__ . '/../resources/templates/footer.php'; ?>
