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

// Bakım modu durumunu kontrol et
$bakim_modu_dosyasi = __DIR__ . '/../config/bakim_modu.php';
$bakim_modu_aktif = file_exists($bakim_modu_dosyasi);

// Form gönderilmişse
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = Yardimci::post('islem');
    
    if ($islem === 'aktif') {
        // Bakım modunu aktifleştir
        $bakim_mesaji = Yardimci::post('bakim_mesaji', 'Sistem şu anda bakım modundadır. Lütfen daha sonra tekrar deneyiniz.');
        $bakim_bitis = Yardimci::post('bakim_bitis', '');
        
        // Bakım modu dosyasını oluştur
        $bakim_modu_icerigi = "<?php\nreturn [\n    'aktif' => true,\n    'mesaj' => '" . addslashes($bakim_mesaji) . "',\n    'bitis' => '" . addslashes($bakim_bitis) . "',\n    'baslangic' => '" . date('Y-m-d H:i:s') . "'\n];";
        
        if (file_put_contents($bakim_modu_dosyasi, $bakim_modu_icerigi)) {
            $_SESSION['mesaj'] = 'Bakım modu başarıyla aktifleştirildi.';
            $_SESSION['mesaj_tur'] = 'success';
        } else {
            $_SESSION['mesaj'] = 'Bakım modu aktifleştirilirken bir hata oluştu.';
            $_SESSION['mesaj_tur'] = 'danger';
        }
    } elseif ($islem === 'pasif') {
        // Bakım modunu devre dışı bırak
        if (file_exists($bakim_modu_dosyasi) && unlink($bakim_modu_dosyasi)) {
            $_SESSION['mesaj'] = 'Bakım modu başarıyla devre dışı bırakıldı.';
            $_SESSION['mesaj_tur'] = 'success';
        } else {
            $_SESSION['mesaj'] = 'Bakım modu devre dışı bırakılırken bir hata oluştu.';
            $_SESSION['mesaj_tur'] = 'danger';
        }
    }
    
    // İşlem kaydını tut
    $log_mesaj = date('Y-m-d H:i:s') . ' - Kullanıcı: ' . $kullanici_bilgileri['kullanici_adi'] . 
                 ' - İşlem: Bakım Modu (' . $islem . ') - Sonuç: ' . 
                 (isset($_SESSION['mesaj_tur']) && $_SESSION['mesaj_tur'] === 'success' ? 'Başarılı' : 'Başarısız');
    file_put_contents(__DIR__ . '/../logs/sistem.log', $log_mesaj . PHP_EOL, FILE_APPEND);
    
    // Sayfayı yenile
    Yardimci::yonlendir('bakim-modu.php');
}

// Bakım modu bilgilerini al
$bakim_bilgileri = [];
if ($bakim_modu_aktif) {
    $bakim_bilgileri = require $bakim_modu_dosyasi;
}

// Header'ı yükle
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Bakım Modu Yönetimi</h1>
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
    <div class="col-md-8">
        <div class="card">
            <div class="card-header <?php echo $bakim_modu_aktif ? 'bg-danger' : 'bg-success'; ?> text-white">
                <h5 class="mb-0">
                    <i class="fas <?php echo $bakim_modu_aktif ? 'fa-tools' : 'fa-check-circle'; ?>"></i> 
                    Bakım Modu Durumu: <?php echo $bakim_modu_aktif ? 'AKTİF' : 'PASİF'; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($bakim_modu_aktif): ?>
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle"></i> Bakım Modu Aktif</h5>
                    <p>Sistem şu anda bakım modundadır. Yeni kullanıcılar ve giriş yapmamış kullanıcılar sisteme erişemezler.</p>
                    <p><strong>Bakım Mesajı:</strong> <?php echo isset($bakim_bilgileri['mesaj']) ? $bakim_bilgileri['mesaj'] : 'Belirtilmemiş'; ?></p>
                    <p><strong>Başlangıç:</strong> <?php echo isset($bakim_bilgileri['baslangic']) ? $bakim_bilgileri['baslangic'] : 'Belirtilmemiş'; ?></p>
                    <?php if (!empty($bakim_bilgileri['bitis'])): ?>
                    <p><strong>Tahmini Bitiş:</strong> <?php echo $bakim_bilgileri['bitis']; ?></p>
                    <?php endif; ?>
                </div>
                
                <form method="post" action="">
                    <input type="hidden" name="islem" value="pasif">
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-power-off"></i> Bakım Modunu Devre Dışı Bırak
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle"></i> Bakım Modu Pasif</h5>
                    <p>Sistem normal şekilde çalışıyor. Tüm kullanıcılar sisteme erişebilir.</p>
                </div>
                
                <form method="post" action="">
                    <input type="hidden" name="islem" value="aktif">
                    
                    <div class="mb-3">
                        <label for="bakim_mesaji" class="form-label">Bakım Mesajı</label>
                        <textarea class="form-control" id="bakim_mesaji" name="bakim_mesaji" rows="3" placeholder="Bakım modu mesajını girin">Sistem şu anda bakım modundadır. Lütfen daha sonra tekrar deneyiniz.</textarea>
                        <div class="form-text">Bu mesaj, bakım modu sırasında kullanıcılara gösterilecektir.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bakim_bitis" class="form-label">Tahmini Bitiş Zamanı (İsteğe Bağlı)</label>
                        <input type="datetime-local" class="form-control" id="bakim_bitis" name="bakim_bitis">
                        <div class="form-text">Bakımın ne zaman biteceğini kullanıcılara bildirmek için bir zaman belirleyebilirsiniz.</div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="fas fa-tools"></i> Bakım Modunu Aktifleştir
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Bakım Modu Hakkında</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6>Bakım Modu Nedir?</h6>
                    <p>Bakım modu, sisteminizde bakım, güncelleme veya onarım işlemleri yaparken kullanıcıların erişimini geçici olarak kısıtlayan bir özelliktir.</p>
                </div>
                
                <div class="alert alert-warning">
                    <h6>Bakım Modu Etkileri</h6>
                    <ul>
                        <li>Giriş yapmamış kullanıcılar sisteme erişemez</li>
                        <li>Yeni kullanıcı kayıtları devre dışı kalır</li>
                        <li>Sadece admin yetkisine sahip kullanıcılar erişebilir</li>
                    </ul>
                </div>
                
                <div class="alert alert-success">
                    <h6>Ne Zaman Kullanılmalı?</h6>
                    <ul>
                        <li>Sistem güncellemeleri sırasında</li>
                        <li>Veritabanı bakımı yaparken</li>
                        <li>Yeni özellikler eklerken</li>
                        <li>Güvenlik düzenlemeleri yaparken</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı yükle
include_once __DIR__ . '/../resources/templates/footer.php';
?>
