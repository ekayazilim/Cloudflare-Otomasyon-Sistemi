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

// Sadece admin yetkisi olanlar erişebilir
if ($kullanici_bilgileri['yetki'] !== 'admin') {
    $_SESSION['mesaj'] = 'Bu sayfaya erişim yetkiniz bulunmamaktadır.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('index.php');
}

// Header'ı yükle
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Önbellek Yönetimi</h1>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Anasayfaya Dön
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

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Önbellek İşlemleri</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">Tüm Önbellek</h5>
                                <p class="card-text">Sistemdeki tüm önbellek verilerini temizler.</p>
                                <a href="cache-temizle.php?tip=all" class="btn btn-danger">
                                    <i class="fas fa-trash"></i> Temizle
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">Uygulama Önbelleği</h5>
                                <p class="card-text">Uygulama tarafından oluşturulan önbellek verilerini temizler.</p>
                                <a href="cache-temizle.php?tip=app" class="btn btn-warning">
                                    <i class="fas fa-broom"></i> Temizle
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">Oturum Önbelleği</h5>
                                <p class="card-text">Oturum verilerini temizler. Aktif kullanıcılar etkilenmez.</p>
                                <a href="cache-temizle.php?tip=session" class="btn btn-info">
                                    <i class="fas fa-user-clock"></i> Temizle
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">Dosya Önbelleği</h5>
                                <p class="card-text">Geçici dosya önbelleğini temizler.</p>
                                <a href="cache-temizle.php?tip=file" class="btn btn-secondary">
                                    <i class="fas fa-file"></i> Temizle
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">DNS Önbelleği</h5>
                                <p class="card-text">DNS sorguları için önbelleği temizler.</p>
                                <a href="cache-temizle.php?tip=dns" class="btn btn-primary">
                                    <i class="fas fa-server"></i> Temizle
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">API Önbelleği</h5>
                                <p class="card-text">API istekleri için önbelleği temizler.</p>
                                <a href="cache-temizle.php?tip=api" class="btn btn-success">
                                    <i class="fas fa-code"></i> Temizle
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">Görüntü Önbelleği</h5>
                                <p class="card-text">Önbelleğe alınmış görüntüleri temizler.</p>
                                <a href="cache-temizle.php?tip=image" class="btn btn-dark">
                                    <i class="fas fa-images"></i> Temizle
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">Veritabanı Önbelleği</h5>
                                <p class="card-text">Veritabanı sorguları için önbelleği temizler.</p>
                                <a href="cache-temizle.php?tip=db" class="btn btn-danger">
                                    <i class="fas fa-database"></i> Temizle
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Önbellek Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle"></i> Önbellek Nedir?</h6>
                    <p>Önbellek (cache), uygulamanın performansını artırmak için sık kullanılan verilerin geçici olarak saklandığı bir mekanizmadır. Bu sayede:</p>
                    <ul>
                        <li>Sayfa yükleme süreleri kısalır</li>
                        <li>Sunucu yükü azalır</li>
                        <li>Veritabanı sorgularının sayısı azalır</li>
                        <li>Kullanıcı deneyimi iyileşir</li>
                    </ul>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle"></i> Önbellek Temizleme Hakkında</h6>
                    <p>Önbellek temizleme işlemi aşağıdaki durumlarda gerekli olabilir:</p>
                    <ul>
                        <li>Uygulama güncellemelerinden sonra</li>
                        <li>Hatalı veya eski verilerin gösterilmesi durumunda</li>
                        <li>Performans sorunları yaşandığında</li>
                        <li>Disk alanı yönetimi için</li>
                    </ul>
                    <p class="mb-0"><strong>Not:</strong> Önbellek temizleme işlemi, kısa süreli performans düşüşüne neden olabilir çünkü veriler yeniden oluşturulacaktır.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı yükle
include_once __DIR__ . '/../resources/templates/footer.php';
?>
