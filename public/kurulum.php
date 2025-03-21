<?php
// Hata raporlamayı etkinleştir
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Dosya yolu tanımlamaları
define('BASE_PATH', realpath(__DIR__ . '/..'));

// Gerekli dosyaları dahil et
require_once BASE_PATH . '/app/Veritabani.php';
require_once BASE_PATH . '/app/Kullanici.php';
require_once BASE_PATH . '/app/Yardimci.php';

use App\Veritabani;
use App\Kullanici;
use App\Yardimci;

// Kurulum tamamlandı mı kontrolü
if (file_exists(BASE_PATH . '/config/kurulum_tamamlandi.php')) {
    try {
        $kurulum_tamamlandi = require_once BASE_PATH . '/config/kurulum_tamamlandi.php';
        if ($kurulum_tamamlandi === true) {
            header('Location: index.php');
            exit;
        }
    } catch (Exception $e) {
        // Dosya var ama içeriği geçersiz ise devam et
        error_log('Kurulum dosyası içeriği geçersiz: ' . $e->getMessage());
    }
}

// Veritabanı bağlantı bilgileri
$default_config = [
    'db_host' => 'localhost',
    'db_name' => 'cloudflare_otomasyon',
    'db_user' => 'root',
    'db_pass' => '',
    'site_url' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/',
    'site_baslik' => 'Cloudflare Otomasyon Sistemi',
    'sayfa_basina_kayit' => 10
];

// Config dosyası var mı kontrolü
$config_dosyasi = BASE_PATH . '/config/uygulama.php';
$config = file_exists($config_dosyasi) ? require_once $config_dosyasi : $default_config;

// Eksik config değerleri için varsayılanları kullan
$config['db_host'] = $config['db_host'] ?? $default_config['db_host'];
$config['db_name'] = $config['db_name'] ?? $default_config['db_name'];
$config['db_user'] = $config['db_user'] ?? $default_config['db_user'];
$config['db_pass'] = $config['db_pass'] ?? $default_config['db_pass'];
$config['site_url'] = $config['site_url'] ?? $default_config['site_url'];
$config['site_baslik'] = $config['site_baslik'] ?? $default_config['site_baslik'];
$config['sayfa_basina_kayit'] = $config['sayfa_basina_kayit'] ?? $default_config['sayfa_basina_kayit'];

// Kurulum adımı
$adim = isset($_GET['adim']) ? (int)$_GET['adim'] : 1;

// Hata ve başarı mesajları
$hata = '';
$basarili = '';

// Form gönderildi mi kontrolü ve işlemleri yap
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Adım 1: Veritabanı bilgileri
    if ($adim === 1 && isset($_POST['db_host'], $_POST['db_name'], $_POST['db_user'])) {
        
        $db_host = trim($_POST['db_host']);
        $db_name = trim($_POST['db_name']);
        $db_user = trim($_POST['db_user']);
        $db_pass = isset($_POST['db_pass']) ? trim($_POST['db_pass']) : '';
        
        try {
            // Veritabanı bağlantısını test et
            $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Bağlantı başarılı, ayarları güncelle
            $config['db_host'] = $db_host;
            $config['db_name'] = $db_name;
            $config['db_user'] = $db_user;
            $config['db_pass'] = $db_pass;
            
            // Config dosyasını oluştur
            $config_content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
            
            // Config klasörü yoksa oluştur
            if (!is_dir(BASE_PATH . '/config')) {
                mkdir(BASE_PATH . '/config', 0755, true);
            }
            
            // Config dosyasını yaz
            if (file_put_contents($config_dosyasi, $config_content)) {
                $basarili = 'Veritabanı bağlantısı başarılı ve ayarlar kaydedildi.';
                
                // Veritabanı bağlantısı kur
                $db = new Veritabani();
                
                // Sonraki adıma yönlendir
                header('Location: kurulum.php?adim=2&basarili=' . urlencode($basarili));
                exit;
            } else {
                $hata = 'Config dosyası yazılamadı. Lütfen dizin izinlerini kontrol edin.';
            }
        } catch (PDOException $e) {
            $hata = 'Veritabanı bağlantı hatası: ' . $e->getMessage();
        }
    }
    
    // Adım 2: Site ayarları
    elseif ($adim === 2 && isset($_POST['site_url'], $_POST['site_baslik'])) {
        
        $site_url = trim($_POST['site_url']);
        $site_baslik = trim($_POST['site_baslik']);
        $sayfa_basina_kayit = isset($_POST['sayfa_basina_kayit']) ? (int)$_POST['sayfa_basina_kayit'] : 10;
        
        if (empty($site_url) || empty($site_baslik)) {
            $hata = 'Site URL ve başlık alanları zorunludur.';
        } else {
            // Ayarları güncelle
            $config['site_url'] = $site_url;
            $config['site_baslik'] = $site_baslik;
            $config['sayfa_basina_kayit'] = $sayfa_basina_kayit;
            
            // Config dosyasını oluştur
            $config_content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
            
            // Config dosyasını yaz
            if (file_put_contents($config_dosyasi, $config_content)) {
                $basarili = 'Site ayarları başarıyla kaydedildi.';
                
                // Sonraki adıma yönlendir
                header('Location: kurulum.php?adim=3&basarili=' . urlencode($basarili));
                exit;
            } else {
                $hata = 'Config dosyası yazılamadı. Lütfen dizin izinlerini kontrol edin.';
            }
        }
    }
    
    // Adım 3: Admin hesabı oluştur
    elseif ($adim === 3 && isset($_POST['admin_email'], $_POST['admin_sifre'], $_POST['admin_adsoyad'])) {
        
        $admin_email = trim($_POST['admin_email']);
        $admin_sifre = trim($_POST['admin_sifre']);
        $admin_adsoyad = trim($_POST['admin_adsoyad']);
        
        if (empty($admin_email) || empty($admin_sifre) || empty($admin_adsoyad)) {
            $hata = 'E-posta, şifre ve ad soyad alanları zorunludur.';
        } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $hata = 'Geçerli bir e-posta adresi giriniz.';
        } elseif (strlen($admin_sifre) < 6) {
            $hata = 'Şifre en az 6 karakter olmalıdır.';
        } else {
            try {
                // Veritabanı tabloları oluştur
                $db = Veritabani::baglan();
                
                // Kullanıcılar tablosu oluştur
                $db->tabloOlustur('kullanicilar', "
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    sifre VARCHAR(255) NOT NULL,
                    ad_soyad VARCHAR(255) NOT NULL,
                    yetki ENUM('admin', 'kullanici') NOT NULL DEFAULT 'kullanici',
                    durum TINYINT(1) NOT NULL DEFAULT 1,
                    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP
                ");
                
                // Cloudflare API anahtarları tablosu oluştur
                $db->tabloOlustur('cloudflare_api_anahtarlari', "
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    kullanici_id INT NOT NULL,
                    api_anahtari VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    aciklama VARCHAR(255) NOT NULL DEFAULT 'Cloudflare API',
                    durum TINYINT(1) NOT NULL DEFAULT 1,
                    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
                ");
                
                // İşlem logları tablosu oluştur
                $db->tabloOlustur('islem_loglari', "
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    kullanici_id INT NOT NULL,
                    islem_turu VARCHAR(50) NOT NULL,
                    domain_id INT,
                    aciklama TEXT NOT NULL,
                    ip_adresi VARCHAR(45) NOT NULL,
                    tarih DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
                ");
                
                // Kullanıcı sınıfını başlat
                $kullanici = new Kullanici();
                
                // Admin kullanıcısını ekle
                $admin_id = $kullanici->kullaniciEkle([
                    'email' => $admin_email,
                    'sifre' => $admin_sifre,
                    'ad_soyad' => $admin_adsoyad,
                    'yetki' => 'admin',
                    'durum' => 1
                ]);
                
                if ($admin_id) {
                    // Kurulum tamamlandı dosyası oluştur
                    $kurulum_dosyasi = BASE_PATH . '/config/kurulum_tamamlandi.php';
                    $kurulum_content = "<?php\n\nreturn true;\n";
                    
                    // Kurulum tamamlandı dosyasını yaz
                    if (file_put_contents($kurulum_dosyasi, $kurulum_content)) {
                        $basarili = 'Kurulum başarıyla tamamlandı! Admin hesabınız oluşturuldu.';
                        
                        // Kurulum tamamlandı, giriş sayfasına yönlendir
                        header('Location: kurulum.php?adim=4&basarili=' . urlencode($basarili));
                        exit;
                    } else {
                        $hata = 'Kurulum dosyası yazılamadı. Lütfen dizin izinlerini kontrol edin.';
                    }
                } else {
                    $hata = 'Admin hesabı oluşturulurken bir hata meydana geldi.';
                }
            } catch (Exception $e) {
                $hata = 'Kurulum sırasında bir hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

// URL'den gelen mesajları al
if (isset($_GET['hata'])) {
    $hata = $_GET['hata'];
}
if (isset($_GET['basarili'])) {
    $basarili = $_GET['basarili'];
}

// Sayfa başlığı
$sayfa_basligi = 'Kurulum - Adım ' . $adim;

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurulum - Cloudflare Otomasyon Sistemi</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .kurulum-container {
            max-width: 700px;
            margin: 50px auto;
        }
        .kurulum-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .kurulum-header h1 {
            color: #2c3e50;
        }
        .kurulum-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .kurulum-card .card-header {
            background-color: #3498db;
            color: white;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .form-label {
            font-weight: 500;
        }
        .adim-gosterge {
            margin-bottom: 30px;
        }
        .adim-gosterge .adim {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #dee2e6;
            color: #495057;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .adim-gosterge .adim.aktif {
            background-color: #3498db;
            color: white;
        }
        .adim-gosterge .adim.tamamlandi {
            background-color: #2ecc71;
            color: white;
        }
        .adim-gosterge .cizgi {
            flex-grow: 1;
            height: 3px;
            background-color: #dee2e6;
        }
        .adim-gosterge .cizgi.tamamlandi {
            background-color: #2ecc71;
        }
    </style>
</head>
<body>
    <div class="container kurulum-container">
        <div class="kurulum-header">
            <h1><i class="fas fa-cloud"></i> Cloudflare Otomasyon Sistemi</h1>
            <p class="text-muted">Kurulum Sihirbazı</p>
        </div>
        
        <!-- Adım Göstergesi -->
        <div class="adim-gosterge d-flex align-items-center justify-content-between mb-4">
            <div class="adim <?php echo $adim >= 1 ? 'aktif' : ''; ?> <?php echo $adim > 1 ? 'tamamlandi' : ''; ?>">1</div>
            <div class="cizgi <?php echo $adim > 1 ? 'tamamlandi' : ''; ?>"></div>
            <div class="adim <?php echo $adim >= 2 ? 'aktif' : ''; ?> <?php echo $adim > 2 ? 'tamamlandi' : ''; ?>">2</div>
            <div class="cizgi <?php echo $adim > 2 ? 'tamamlandi' : ''; ?>"></div>
            <div class="adim <?php echo $adim >= 3 ? 'aktif' : ''; ?> <?php echo $adim > 3 ? 'tamamlandi' : ''; ?>">3</div>
            <div class="cizgi <?php echo $adim > 3 ? 'tamamlandi' : ''; ?>"></div>
            <div class="adim <?php echo $adim >= 4 ? 'aktif' : ''; ?> <?php echo $adim > 4 ? 'tamamlandi' : ''; ?>">4</div>
        </div>
        
        <?php if (!empty($hata)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> <?php echo $hata; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($basarili)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $basarili; ?>
        </div>
        <?php endif; ?>
        
        <div class="card kurulum-card">
            <?php if ($adim === 1): ?>
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-database"></i> Adım 1: Veritabanı Ayarları</h4>
            </div>
            <div class="card-body">
                <form method="post" action="kurulum.php?adim=1">
                    <div class="mb-3">
                        <label for="db_host" class="form-label">Veritabanı Sunucusu</label>
                        <input type="text" class="form-control" id="db_host" name="db_host" value="<?php echo htmlspecialchars($config['db_host']); ?>" required>
                        <div class="form-text">Genellikle "localhost" veya "127.0.0.1"</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="db_name" class="form-label">Veritabanı Adı</label>
                        <input type="text" class="form-control" id="db_name" name="db_name" value="<?php echo htmlspecialchars($config['db_name']); ?>" required>
                        <div class="form-text">Önceden oluşturulmuş olmalıdır</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="db_user" class="form-label">Veritabanı Kullanıcısı</label>
                        <input type="text" class="form-control" id="db_user" name="db_user" value="<?php echo htmlspecialchars($config['db_user']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="db_pass" class="form-label">Veritabanı Şifresi</label>
                        <input type="password" class="form-control" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($config['db_pass']); ?>">
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            Devam Et <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>
            </div>
            
            <?php elseif ($adim === 2): ?>
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-cog"></i> Adım 2: Site Ayarları</h4>
            </div>
            <div class="card-body">
                <form method="post" action="kurulum.php?adim=2">
                    <div class="mb-3">
                        <label for="site_url" class="form-label">Site URL</label>
                        <input type="url" class="form-control" id="site_url" name="site_url" value="<?php echo htmlspecialchars($config['site_url']); ?>" required>
                        <div class="form-text">Örn: https://example.com/cloudflare/</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="site_baslik" class="form-label">Site Başlığı</label>
                        <input type="text" class="form-control" id="site_baslik" name="site_baslik" value="<?php echo htmlspecialchars($config['site_baslik']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sayfa_basina_kayit" class="form-label">Sayfa Başına Kayıt Sayısı</label>
                        <select class="form-select" id="sayfa_basina_kayit" name="sayfa_basina_kayit">
                            <option value="10" <?php echo $config['sayfa_basina_kayit'] == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $config['sayfa_basina_kayit'] == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $config['sayfa_basina_kayit'] == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $config['sayfa_basina_kayit'] == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="kurulum.php?adim=1" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Geri
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Devam Et <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>
            </div>
            
            <?php elseif ($adim === 3): ?>
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-user-shield"></i> Adım 3: Admin Hesabı Oluştur</h4>
            </div>
            <div class="card-body">
                <form method="post" action="kurulum.php?adim=3">
                    <div class="mb-3">
                        <label for="admin_email" class="form-label">E-posta Adresi</label>
                        <input type="email" class="form-control" id="admin_email" name="admin_email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="admin_sifre" class="form-label">Şifre</label>
                        <input type="password" class="form-control" id="admin_sifre" name="admin_sifre" required minlength="6">
                        <div class="form-text">En az 6 karakter olmalıdır</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="admin_adsoyad" class="form-label">Ad Soyad</label>
                        <input type="text" class="form-control" id="admin_adsoyad" name="admin_adsoyad" required>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="kurulum.php?adim=2" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Geri
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Kurulumu Tamamla <i class="fas fa-check-circle"></i>
                        </button>
                    </div>
                </form>
            </div>
            
            <?php elseif ($adim === 4): ?>
            <div class="card-header bg-success">
                <h4 class="mb-0"><i class="fas fa-check-circle"></i> Kurulum Tamamlandı</h4>
            </div>
            <div class="card-body text-center">
                <div class="mb-4">
                    <i class="fas fa-check-circle text-success" style="font-size: 60px;"></i>
                    <h3 class="mt-3">Tebrikler! Kurulum Başarıyla Tamamlandı</h3>
                    <p class="text-muted">Cloudflare Otomasyon Sistemi'ni kullanmaya başlayabilirsiniz.</p>
                </div>
                
                <div class="alert alert-info">
                    <p><i class="fas fa-info-circle"></i> Kurulumun ilk adımı olarak:</p>
                    <ol class="text-start">
                        <li>Admin hesabınızla giriş yapın</li>
                        <li>Cloudflare API anahtarlarınızı ekleyin</li>
                        <li>Domainlerinizi senkronize edin</li>
                    </ol>
                </div>
                
                <div class="d-grid">
                    <a href="giris.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-sign-in-alt"></i> Giriş Yap
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-4 text-muted">
            <p>Cloudflare Otomasyon Sistemi &copy; <?php echo date('Y'); ?> | <a href="https://www.ekayazilim.com.tr" target="_blank">EKA Yazılım</a></p>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 