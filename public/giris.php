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

// Kullanıcı zaten giriş yapmışsa ana sayfaya yönlendir
$kullanici = new Kullanici();
$kullanici_bilgileri = $kullanici->oturumKontrol();

if ($kullanici_bilgileri) {
    Yardimci::yonlendir('index.php');
}

// Form gönderilmişse
$hata_mesaji = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kullanici_adi = Yardimci::post('kullanici_adi');
    $sifre = Yardimci::post('sifre');
    
    if (empty($kullanici_adi) || empty($sifre)) {
        $hata_mesaji = 'Kullanıcı adı ve şifre gereklidir.';
    } else {
        $giris_sonuc = $kullanici->giris($kullanici_adi, $sifre);
        
        if ($giris_sonuc) {
            // Başarılı giriş, ana sayfaya yönlendir
            Yardimci::yonlendir('index.php');
        } else {
            $hata_mesaji = 'Geçersiz kullanıcı adı veya şifre.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - Cloudflare Otomasyon</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $config['url']; ?>/assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container login-container">
        <div class="login-logo">
            <i class="fas fa-cloud"></i> Cloudflare Otomasyon
        </div>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Giriş Yap</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($hata_mesaji)): ?>
                <div class="alert alert-danger">
                    <?php echo $hata_mesaji; ?>
                </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="kullanici_adi" class="form-label">Kullanıcı Adı veya E-posta</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="kullanici_adi" name="kullanici_adi" placeholder="Kullanıcı adınızı veya e-posta adresinizi girin" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sifre" class="form-label">Şifre</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="sifre" name="sifre" placeholder="Şifrenizi girin" required>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="beni_hatirla" name="beni_hatirla">
                        <label class="form-check-label" for="beni_hatirla">Beni Hatırla</label>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Giriş Yap
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">Hesabınız yok mu? <a href="kayit.php">Kayıt Ol</a></p>
                <p class="mt-2 mb-0"><a href="sifremi-unuttum.php">Şifremi Unuttum</a></p>
            </div>
        </div>
        
        <div class="text-center mt-4 text-muted">
            <p>&copy; <?php echo date('Y'); ?> Cloudflare Otomasyon. Tüm hakları saklıdır.</p>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
