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

// Günlük dosyaları dizini
$logs_dir = __DIR__ . '/../logs/';

// Günlük dosyasını temizleme işlemi
if (isset($_GET['temizle']) && !empty($_GET['temizle'])) {
    $log_file = Yardimci::temizle($_GET['temizle']);
    $log_path = $logs_dir . $log_file;
    
    // Dosya var mı ve logs dizininde mi kontrol et
    if (file_exists($log_path) && is_file($log_path) && strpos(realpath($log_path), realpath($logs_dir)) === 0) {
        // Dosyayı temizle (boş içerikle yeniden oluştur)
        if (file_put_contents($log_path, '') !== false) {
            $_SESSION['mesaj'] = $log_file . ' günlük dosyası başarıyla temizlendi.';
            $_SESSION['mesaj_tur'] = 'success';
        } else {
            $_SESSION['mesaj'] = 'Günlük dosyası temizlenirken bir hata oluştu.';
            $_SESSION['mesaj_tur'] = 'danger';
        }
    } else {
        $_SESSION['mesaj'] = 'Geçersiz günlük dosyası.';
        $_SESSION['mesaj_tur'] = 'danger';
    }
    
    // Sayfayı yenile
    Yardimci::yonlendir('gunluk-dosyalari.php');
}

// Günlük dosyasını görüntüleme
$log_content = '';
$current_log = '';
if (isset($_GET['dosya']) && !empty($_GET['dosya'])) {
    $log_file = Yardimci::temizle($_GET['dosya']);
    $log_path = $logs_dir . $log_file;
    
    // Dosya var mı ve logs dizininde mi kontrol et
    if (file_exists($log_path) && is_file($log_path) && strpos(realpath($log_path), realpath($logs_dir)) === 0) {
        // Dosya içeriğini oku (son 500 satır)
        $log_content = file_get_contents($log_path);
        $current_log = $log_file;
        
        // Çok büyük dosyalar için son 500 satırı al
        $lines = explode("\n", $log_content);
        if (count($lines) > 500) {
            $lines = array_slice($lines, -500);
            $log_content = implode("\n", $lines);
            $log_content = "... (Dosyanın sadece son 500 satırı gösteriliyor) ...\n\n" . $log_content;
        }
    } else {
        $_SESSION['mesaj'] = 'Geçersiz günlük dosyası.';
        $_SESSION['mesaj_tur'] = 'danger';
        Yardimci::yonlendir('gunluk-dosyalari.php');
    }
}

// Günlük dosyalarını listele
$log_files = [];
if (is_dir($logs_dir)) {
    $files = scandir($logs_dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && is_file($logs_dir . $file) && pathinfo($file, PATHINFO_EXTENSION) == 'log') {
            $log_files[] = [
                'name' => $file,
                'size' => filesize($logs_dir . $file),
                'modified' => filemtime($logs_dir . $file)
            ];
        }
    }
}

// Dosyaları son değiştirilme tarihine göre sırala (en yeni en üstte)
usort($log_files, function($a, $b) {
    return $b['modified'] - $a['modified'];
});

// Header'ı yükle
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Günlük Dosyaları</h1>
            <a href="sistem-bilgileri.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Sistem Bilgilerine Dön
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
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-file-alt"></i> Günlük Dosyaları</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($log_files)): ?>
                <div class="alert alert-info m-3">
                    Henüz günlük dosyası bulunmamaktadır.
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($log_files as $log): ?>
                    <a href="gunluk-dosyalari.php?dosya=<?php echo urlencode($log['name']); ?>" 
                       class="list-group-item list-group-item-action <?php echo ($current_log == $log['name']) ? 'active' : ''; ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo $log['name']; ?></h6>
                            <small><?php echo Yardimci::dosyaBoyutuFormatla($log['size']); ?></small>
                        </div>
                        <small>Son değişiklik: <?php echo date('d.m.Y H:i:s', $log['modified']); ?></small>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Bilgi</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6>Günlük Dosyaları Nedir?</h6>
                    <p>Günlük dosyaları, sistemdeki önemli olayları, hataları ve işlemleri kaydeden dosyalardır. Bu dosyalar, sorun giderme ve sistem izleme için önemlidir.</p>
                </div>
                
                <div class="alert alert-warning">
                    <h6>Dikkat!</h6>
                    <p>Günlük dosyalarını temizlemek, geçmiş kayıtların silinmesine neden olur. Bu işlem geri alınamaz.</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <?php if (!empty($current_log)): ?>
                        <i class="fas fa-file-code"></i> <?php echo $current_log; ?>
                        <?php else: ?>
                        <i class="fas fa-file-code"></i> Günlük İçeriği
                        <?php endif; ?>
                    </h5>
                    <?php if (!empty($current_log)): ?>
                    <div>
                        <a href="gunluk-dosyalari.php?temizle=<?php echo urlencode($current_log); ?>" 
                           class="btn btn-sm btn-danger" 
                           onclick="return confirm('<?php echo $current_log; ?> dosyasını temizlemek istediğinize emin misiniz?');">
                            <i class="fas fa-trash"></i> Temizle
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($current_log)): ?>
                <div class="alert alert-info">
                    Görüntülemek için sol menüden bir günlük dosyası seçin.
                </div>
                <?php else: ?>
                <div class="log-container bg-dark text-light p-3" style="height: 600px; overflow: auto; font-family: monospace; white-space: pre-wrap; font-size: 0.9rem;">
                    <?php 
                    if (empty($log_content)) {
                        echo "Günlük dosyası boş.";
                    } else {
                        // Hata mesajlarını renklendir
                        $log_content = preg_replace('/\b(ERROR|HATA|CRITICAL|KRİTİK)\b/i', '<span class="text-danger">$1</span>', $log_content);
                        $log_content = preg_replace('/\b(WARNING|UYARI)\b/i', '<span class="text-warning">$1</span>', $log_content);
                        $log_content = preg_replace('/\b(INFO|BİLGİ)\b/i', '<span class="text-info">$1</span>', $log_content);
                        $log_content = preg_replace('/\b(SUCCESS|BAŞARILI)\b/i', '<span class="text-success">$1</span>', $log_content);
                        
                        // Tarih formatlarını renklendir
                        $log_content = preg_replace('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', '<span class="text-primary">$0</span>', $log_content);
                        
                        echo $log_content;
                    }
                    ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı yükle
include_once __DIR__ . '/../resources/templates/footer.php';
?>
