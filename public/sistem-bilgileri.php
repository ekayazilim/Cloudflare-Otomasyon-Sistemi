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
use App\Veritabani;
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

// Veritabanı bağlantısı
$veritabani = Veritabani::baglan();
$db = $veritabani->getConn();

// Yüklü PHP uzantılarını al
$php_extensions = get_loaded_extensions();
sort($php_extensions);

// Header'ı yükle
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Sistem Bilgileri</h1>
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
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> PHP Bilgileri</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th>PHP Sürümü</th>
                        <td><?php echo phpversion(); ?></td>
                    </tr>
                    <tr>
                        <th>Bellek Limiti</th>
                        <td><?php echo ini_get('memory_limit'); ?></td>
                    </tr>
                    <tr>
                        <th>Maksimum Yükleme Boyutu</th>
                        <td><?php echo ini_get('upload_max_filesize'); ?></td>
                    </tr>
                    <tr>
                        <th>Post Maksimum Boyutu</th>
                        <td><?php echo ini_get('post_max_size'); ?></td>
                    </tr>
                    <tr>
                        <th>Maksimum Çalışma Süresi</th>
                        <td><?php echo ini_get('max_execution_time'); ?> saniye</td>
                    </tr>
                    <tr>
                        <th>Hata Raporlama</th>
                        <td><?php echo ini_get('display_errors') ? 'Açık' : 'Kapalı'; ?></td>
                    </tr>
                    <tr>
                        <th>Tarih Saat Dilimi</th>
                        <td><?php echo date_default_timezone_get(); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-database"></i> Veritabanı Bilgileri</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th>Veritabanı Tipi</th>
                        <td>MySQL</td>
                    </tr>
                    <tr>
                        <th>Sunucu Sürümü</th>
                        <td>
                            <?php 
                            $version_query = $db->query("SELECT VERSION() as version");
                            $version = $version_query->fetch(PDO::FETCH_ASSOC);
                            echo $version['version'];
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Bağlantı Karakterseti</th>
                        <td><?php echo $db->query("SHOW VARIABLES LIKE 'character_set_connection'")->fetch(PDO::FETCH_ASSOC)['Value']; ?></td>
                    </tr>
                    <tr>
                        <th>Veritabanı Adı</th>
                        <td><?php 
                            $veritabani_config = require_once __DIR__ . '/../config/veritabani.php';
                            echo $veritabani_config['veritabani'] ?? 'Belirtilmemiş'; 
                        ?></td>
                    </tr>
                    <tr>
                        <th>Veritabanı Sunucusu</th>
                        <td><?php echo $veritabani_config['host'] ?? 'Belirtilmemiş'; ?></td>
                    </tr>
                    <tr>
                        <th>Tablo Sayısı</th>
                        <td>
                            <?php 
                            $tables_query = $db->query("SHOW TABLES");
                            echo $tables_query->rowCount();
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-server"></i> Sunucu Bilgileri</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th>Sunucu Yazılımı</th>
                        <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                    </tr>
                    <tr>
                        <th>Sunucu İşletim Sistemi</th>
                        <td><?php echo PHP_OS; ?></td>
                    </tr>
                    <tr>
                        <th>Sunucu IP Adresi</th>
                        <td><?php echo $_SERVER['SERVER_ADDR']; ?></td>
                    </tr>
                    <tr>
                        <th>Sunucu Portu</th>
                        <td><?php echo $_SERVER['SERVER_PORT']; ?></td>
                    </tr>
                    <tr>
                        <th>HTTP Protokolü</th>
                        <td><?php echo $_SERVER['SERVER_PROTOCOL']; ?></td>
                    </tr>
                    <tr>
                        <th>Bellek Kullanımı</th>
                        <td><?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB</td>
                    </tr>
                    <tr>
                        <th>Disk Alanı</th>
                        <td>
                            <?php 
                            $free_space = disk_free_space('/');
                            $total_space = disk_total_space('/');
                            $used_space = $total_space - $free_space;
                            $percent = round(($used_space / $total_space) * 100, 2);
                            
                            echo 'Toplam: ' . round($total_space / 1024 / 1024 / 1024, 2) . ' GB<br>';
                            echo 'Kullanılan: ' . round($used_space / 1024 / 1024 / 1024, 2) . ' GB (' . $percent . '%)';
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-cogs"></i> Uygulama Bilgileri</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th>Uygulama Adı</th>
                        <td><?php echo $config['uygulama_adi'] ?? 'Belirtilmemiş'; ?></td>
                    </tr>
                    <tr>
                        <th>Sürüm</th>
                        <td><?php echo $config['surum'] ?? 'Belirtilmemiş'; ?></td>
                    </tr>
                    <tr>
                        <th>Ortam</th>
                        <td><?php echo $config['debug'] ? 'Geliştirme' : 'Üretim'; ?></td>
                    </tr>
                    <tr>
                        <th>Bakım Modu</th>
                        <td>
                            <?php if (isset($config['bakim_modu']) && $config['bakim_modu']): ?>
                            <span class="badge bg-danger">Aktif</span>
                            <?php else: ?>
                            <span class="badge bg-success">Pasif</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Hata Günlüğü</th>
                        <td>
                            <?php if (isset($config['debug']) && $config['debug']): ?>
                            <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Pasif</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Kurulum Tarihi</th>
                        <td><?php 
                        // Kurulum dosyasının oluşturulma tarihini kullan
                        $kurulum_dosyasi = __DIR__ . '/../config/kurulum_tamamlandi.php';
                        if (file_exists($kurulum_dosyasi)) {
                            echo date('d.m.Y H:i', filemtime($kurulum_dosyasi));
                        } else {
                            echo 'Belirtilmemiş';
                        }
                        ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-puzzle-piece"></i> PHP Uzantıları</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($php_extensions as $extension): ?>
                    <div class="col-md-3 mb-2">
                        <div class="badge bg-light text-dark p-2 w-100 text-start">
                            <i class="fas fa-check-circle text-success"></i> <?php echo $extension; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-tools"></i> Sistem Araçları</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">Önbellek Yönetimi</h5>
                                <p class="card-text">Sistem önbelleğini temizleme ve yönetme.</p>
                                <a href="cache-yonetimi.php" class="btn btn-primary">
                                    <i class="fas fa-broom"></i> Önbellek Yönetimi
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">Bakım Modu</h5>
                                <p class="card-text">Sistemi bakım moduna alma veya çıkarma.</p>
                                <a href="bakim-modu.php" class="btn btn-warning">
                                    <i class="fas fa-tools"></i> Bakım Modu
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title">Günlük Dosyaları</h5>
                                <p class="card-text">Sistem günlük dosyalarını görüntüleme.</p>
                                <a href="gunluk-dosyalari.php" class="btn btn-info">
                                    <i class="fas fa-file-alt"></i> Günlük Dosyaları
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı yükle
include_once __DIR__ . '/../resources/templates/footer.php';
?>
