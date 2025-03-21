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
    exit;
}

// Mesaj değişkenleri
$hata = '';
$basarili = '';

// Form gönderildi mi kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Şifre değişikliği
    if (isset($_POST['sifre_degistir'])) {
        $mevcut_sifre = trim($_POST['mevcut_sifre'] ?? '');
        $yeni_sifre = trim($_POST['yeni_sifre'] ?? '');
        $yeni_sifre_tekrar = trim($_POST['yeni_sifre_tekrar'] ?? '');
        
        // Tüm alanlar doldurulmuş mu?
        if (empty($mevcut_sifre) || empty($yeni_sifre) || empty($yeni_sifre_tekrar)) {
            $hata = 'Lütfen tüm alanları doldurun.';
        } 
        // Yeni şifreler eşleşiyor mu?
        elseif ($yeni_sifre !== $yeni_sifre_tekrar) {
            $hata = 'Yeni şifreler eşleşmiyor.';
        }
        // Şifre minimum 6 karakter mi?
        elseif (strlen($yeni_sifre) < 6) {
            $hata = 'Yeni şifre en az 6 karakter olmalıdır.';
        }
        // Mevcut şifre doğru mu?
        elseif (!$kullanici->sifreKontrol($kullanici_bilgileri['id'], $mevcut_sifre)) {
            $hata = 'Mevcut şifre hatalı.';
        }
        else {
            // Şifreyi güncelle
            $guncelleme_sonucu = $kullanici->sifreGuncelle($kullanici_bilgileri['id'], $yeni_sifre);
            
            if ($guncelleme_sonucu) {
                $basarili = 'Şifreniz başarıyla güncellendi.';
                
                // Form verilerini temizle
                $mevcut_sifre = $yeni_sifre = $yeni_sifre_tekrar = '';
            } else {
                $hata = 'Şifre güncellenirken bir hata oluştu.';
            }
        }
    }
    // Profil bilgilerini güncelleme
    elseif (isset($_POST['profil_guncelle'])) {
        $ad = trim($_POST['ad'] ?? '');
        $soyad = trim($_POST['soyad'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        // Tüm alanlar doldurulmuş mu?
        if (empty($ad) || empty($soyad) || empty($email)) {
            $hata = 'Lütfen tüm alanları doldurun.';
        }
        // E-posta geçerli mi?
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $hata = 'Lütfen geçerli bir e-posta adresi girin.';
        }
        else {
            // Kullanıcı bilgilerini güncelle
            $guncelleme_verileri = [
                'ad' => $ad,
                'soyad' => $soyad,
                'email' => $email
            ];
            
            $guncelleme_sonucu = $kullanici->kullaniciGuncelle($kullanici_bilgileri['id'], $guncelleme_verileri);
            
            if ($guncelleme_sonucu) {
                $basarili = 'Profil bilgileriniz başarıyla güncellendi.';
                
                // Güncel bilgileri al
                $kullanici_bilgileri = $kullanici->kullaniciGetir($kullanici_bilgileri['id']);
            } else {
                $hata = 'Profil güncellenirken bir hata oluştu. E-posta adresi başka bir kullanıcı tarafından kullanılıyor olabilir.';
            }
        }
    }
}

// Sayfa başlığı
$sayfa_basligi = 'Profil Bilgilerim';
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mb-4">Profil Bilgilerim</h1>
            
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
            
            <div class="row">
                <!-- Profil Bilgileri -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0"><i class="fas fa-user"></i> Profil Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="ad" class="form-label">Ad</label>
                                    <input type="text" class="form-control" id="ad" name="ad" value="<?php echo htmlspecialchars($kullanici_bilgileri['ad'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="soyad" class="form-label">Soyad</label>
                                    <input type="text" class="form-control" id="soyad" name="soyad" value="<?php echo htmlspecialchars($kullanici_bilgileri['soyad'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">E-posta Adresi</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($kullanici_bilgileri['email'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="kullanici_adi" class="form-label">Kullanıcı Adı</label>
                                    <input type="text" class="form-control" id="kullanici_adi" value="<?php echo htmlspecialchars($kullanici_bilgileri['kullanici_adi'] ?? ''); ?>" disabled>
                                    <div class="form-text text-muted">Kullanıcı adı değiştirilemez</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="yetki" class="form-label">Yetki Seviyesi</label>
                                    <input type="text" class="form-control" id="yetki" value="<?php echo ucfirst($kullanici_bilgileri['yetki'] ?? ''); ?>" disabled>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="son_giris" class="form-label">Son Giriş Tarihi</label>
                                    <input type="text" class="form-control" id="son_giris" value="<?php echo !empty($kullanici_bilgileri['son_giris']) ? date('d.m.Y H:i', strtotime($kullanici_bilgileri['son_giris'])) : 'Bilgi Yok'; ?>" disabled>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" name="profil_guncelle" value="1" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Profil Bilgilerini Güncelle
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Şifre Değiştirme -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0"><i class="fas fa-key"></i> Şifre Değiştir</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="mevcut_sifre" class="form-label">Mevcut Şifre</label>
                                    <input type="password" class="form-control" id="mevcut_sifre" name="mevcut_sifre" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="yeni_sifre" class="form-label">Yeni Şifre</label>
                                    <input type="password" class="form-control" id="yeni_sifre" name="yeni_sifre" required minlength="6">
                                    <div class="form-text">En az 6 karakter olmalıdır</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="yeni_sifre_tekrar" class="form-label">Yeni Şifre (Tekrar)</label>
                                    <input type="password" class="form-control" id="yeni_sifre_tekrar" name="yeni_sifre_tekrar" required minlength="6">
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" name="sifre_degistir" value="1" class="btn btn-warning">
                                        <i class="fas fa-key"></i> Şifremi Değiştir
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Oturum Bilgileri -->
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="card-title mb-0"><i class="fas fa-history"></i> Son Oturum Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Kullanıcının son 5 oturumunu getir
                            $oturumlar = $kullanici->oturumlariGetir($kullanici_bilgileri['id'], 5);
                            
                            if (empty($oturumlar)) {
                                echo '<div class="alert alert-info">Henüz oturum kaydınız bulunmuyor.</div>';
                            } else {
                            ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>IP Adresi</th>
                                            <th>Tarayıcı</th>
                                            <th>Son Aktivite</th>
                                            <th>Oluşturma Tarihi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($oturumlar as $oturum): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($oturum['ip_adresi']); ?></td>
                                            <td><?php echo htmlspecialchars($oturum['tarayici']); ?></td>
                                            <td><?php echo date('d.m.Y H:i', strtotime($oturum['son_aktivite'])); ?></td>
                                            <td><?php echo date('d.m.Y H:i', strtotime($oturum['olusturma_tarihi'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php } ?>
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