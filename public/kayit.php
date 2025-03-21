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
$basari_mesaji = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad = Yardimci::post('ad');
    $soyad = Yardimci::post('soyad');
    $kullanici_adi = Yardimci::post('kullanici_adi');
    $email = Yardimci::post('email');
    $sifre = Yardimci::post('sifre');
    $sifre_tekrar = Yardimci::post('sifre_tekrar');
    
    // Validasyon
    if (empty($ad) || empty($soyad) || empty($kullanici_adi) || empty($email) || empty($sifre) || empty($sifre_tekrar)) {
        $hata_mesaji = 'Tüm alanları doldurunuz.';
    } elseif ($sifre !== $sifre_tekrar) {
        $hata_mesaji = 'Şifreler eşleşmiyor.';
    } elseif (strlen($sifre) < 6) {
        $hata_mesaji = 'Şifre en az 6 karakter olmalıdır.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hata_mesaji = 'Geçerli bir e-posta adresi giriniz.';
    } else {
        // Kullanıcı kayıt
        $kullanici_veri = [
            'ad' => $ad,
            'soyad' => $soyad,
            'kullanici_adi' => $kullanici_adi,
            'email' => $email,
            'sifre' => $sifre,
            'yetki' => 'kullanici', // İlk kullanıcı admin olabilir
            'durum' => 1
        ];
        
        $kayit_sonuc = $kullanici->kayit($kullanici_veri);
        
        if ($kayit_sonuc) {
            $basari_mesaji = 'Kayıt başarılı! Şimdi giriş yapabilirsiniz.';
        } else {
            $hata_mesaji = 'Kayıt sırasında bir hata oluştu. Kullanıcı adı veya e-posta zaten kullanılıyor olabilir.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - Cloudflare Otomasyon</title>
    
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
                <h4 class="mb-0">Kayıt Ol</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($hata_mesaji)): ?>
                <div class="alert alert-danger">
                    <?php echo $hata_mesaji; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($basari_mesaji)): ?>
                <div class="alert alert-success">
                    <?php echo $basari_mesaji; ?>
                    <div class="mt-2">
                        <a href="giris.php" class="btn btn-success btn-sm">Giriş Yap</a>
                    </div>
                </div>
                <?php else: ?>
                
                <form method="post" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="ad" class="form-label">Ad</label>
                            <input type="text" class="form-control" id="ad" name="ad" placeholder="Adınız" required>
                        </div>
                        <div class="col-md-6">
                            <label for="soyad" class="form-label">Soyad</label>
                            <input type="text" class="form-control" id="soyad" name="soyad" placeholder="Soyadınız" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="kullanici_adi" class="form-label">Kullanıcı Adı</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="kullanici_adi" name="kullanici_adi" placeholder="Kullanıcı adınız" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">E-posta Adresi</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" placeholder="E-posta adresiniz" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sifre" class="form-label">Şifre</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="sifre" name="sifre" placeholder="Şifreniz (en az 6 karakter)" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sifre_tekrar" class="form-label">Şifre Tekrar</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="sifre_tekrar" name="sifre_tekrar" placeholder="Şifrenizi tekrar girin" required>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="sozlesme" name="sozlesme" required>
                        <label class="form-check-label" for="sozlesme">
                            <a href="kullanim-kosullari.php" target="_blank">Kullanım Koşullarını</a> ve 
                            <a href="gizlilik-politikasi.php" target="_blank">Gizlilik Politikasını</a> okudum ve kabul ediyorum.
                        </label>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Kayıt Ol
                        </button>
                    </div>
                </form>
                
                <?php endif; ?>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">Zaten hesabınız var mı? <a href="giris.php">Giriş Yap</a></p>
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
