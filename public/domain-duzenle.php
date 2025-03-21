<?php
// Oturum başlat
session_start();

// Konfigürasyon dosyalarını yükle
$config = require_once __DIR__ . '/../config/uygulama.php';

// Gerekli sınıfları yükle
require_once __DIR__ . '/../app/Veritabani.php';
require_once __DIR__ . '/../app/Kullanici.php';
require_once __DIR__ . '/../app/CloudflareAPI.php';
require_once __DIR__ . '/../app/Domain.php';
require_once __DIR__ . '/../app/Yardimci.php';

use App\Kullanici;
use App\Domain;
use App\CloudflareAPI;
use App\Yardimci;

// Oturum kontrolü
$kullanici = new Kullanici();
$kullanici_bilgileri = $kullanici->oturumKontrol();

if (!$kullanici_bilgileri) {
    Yardimci::yonlendir('giris.php');
}

// Domain ID'sini al
$domain_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($domain_id <= 0) {
    $_SESSION['mesaj'] = 'Geçersiz domain ID.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('domainler.php');
}

// Domain sınıfı örneği oluştur
$domain = new Domain();

// Domain bilgilerini al
$domain_bilgi = $domain->domainGetir($domain_id, $kullanici_bilgileri['id']);

if (!$domain_bilgi) {
    $_SESSION['mesaj'] = 'Domain bulunamadı veya bu domain size ait değil.';
    $_SESSION['mesaj_tur'] = 'danger';
    Yardimci::yonlendir('domainler.php');
}

// Form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $ssl_modu = isset($_POST['ssl_modu']) ? $_POST['ssl_modu'] : '';
    $durum = isset($_POST['durum']) ? (int)$_POST['durum'] : 0;

    // Güncelleme için gerekli parametreleri hazırla
    $guncelleme_verileri = [
        'ssl_modu' => $ssl_modu,
        'durum' => $durum,
        'guncelleme_tarihi' => date('Y-m-d H:i:s')
    ];

    // API anahtarı seçilirse güncelle
    if (isset($_POST['api_anahtar_id']) && !empty($_POST['api_anahtar_id'])) {
        $api_anahtar_id = (int)$_POST['api_anahtar_id'];
        $guncelleme_verileri['api_anahtar_id'] = $api_anahtar_id;
    }

    // Domain'i güncelle
    $guncelleme_sonuc = $domain->domainGuncelle($domain_id, $guncelleme_verileri);

    if ($guncelleme_sonuc) {
        $_SESSION['mesaj'] = 'Domain başarıyla güncellendi.';
        $_SESSION['mesaj_tur'] = 'success';
    } else {
        $_SESSION['mesaj'] = 'Domain güncellenirken bir hata oluştu.';
        $_SESSION['mesaj_tur'] = 'danger';
    }

    // Başarılı olursa detay sayfasına yönlendir
    Yardimci::yonlendir('domain-detay.php?id=' . $domain_id);
}

// API anahtarlarını listele
$api_anahtarlari = $domain->apiAnahtarlariniListele($kullanici_bilgileri['id']);

// Header'ı yükle
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Domain Düzenle</h6>
            </div>
            <div class="card-body">
                <form action="domain-duzenle.php?id=<?php echo $domain_id; ?>" method="post">
                    <div class="form-group">
                        <label for="domain">Domain</label>
                        <input type="text" class="form-control" id="domain" value="<?php echo htmlspecialchars($domain_bilgi['domain']); ?>" readonly>
                        <small class="form-text text-muted">Domain adı değiştirilemez.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="zone_id">Zone ID</label>
                        <input type="text" class="form-control" id="zone_id" value="<?php echo htmlspecialchars($domain_bilgi['zone_id']); ?>" readonly>
                        <small class="form-text text-muted">Cloudflare Zone ID değiştirilemez.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="ssl_modu">SSL Modu</label>
                        <select class="form-control" id="ssl_modu" name="ssl_modu">
                            <option value="off" <?php echo ($domain_bilgi['ssl_modu'] == 'off') ? 'selected' : ''; ?>>Kapalı</option>
                            <option value="flexible" <?php echo ($domain_bilgi['ssl_modu'] == 'flexible') ? 'selected' : ''; ?>>Esnek</option>
                            <option value="full" <?php echo ($domain_bilgi['ssl_modu'] == 'full') ? 'selected' : ''; ?>>Tam</option>
                            <option value="strict" <?php echo ($domain_bilgi['ssl_modu'] == 'strict') ? 'selected' : ''; ?>>Katı</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="durum">Durum</label>
                        <select class="form-control" id="durum" name="durum">
                            <option value="1" <?php echo ($domain_bilgi['durum'] == 1) ? 'selected' : ''; ?>>Aktif</option>
                            <option value="0" <?php echo ($domain_bilgi['durum'] == 0) ? 'selected' : ''; ?>>Pasif</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="api_anahtar_id">API Anahtarı</label>
                        <select class="form-control" id="api_anahtar_id" name="api_anahtar_id">
                            <option value="">-- Mevcut API Anahtarını Kullan --</option>
                            <?php foreach ($api_anahtarlari as $anahtar): ?>
                                <option value="<?php echo (int)$anahtar['id']; ?>" <?php echo ($domain_bilgi['api_anahtar_id'] == $anahtar['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(($anahtar['aciklama'] ? $anahtar['aciklama'] : 'API Anahtarı #'.$anahtar['id']) . ' (' . $anahtar['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Bu domain için kullanılacak API anahtarını değiştirebilirsiniz.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Diğer Bilgiler</label>
                        <p>
                            <strong>Plan:</strong> <?php echo htmlspecialchars($domain_bilgi['plan']); ?><br>
                            <strong>Oluşturma Tarihi:</strong> <?php echo htmlspecialchars($domain_bilgi['olusturma_tarihi']); ?><br>
                            <strong>Güncelleme Tarihi:</strong> <?php echo $domain_bilgi['guncelleme_tarihi'] ? htmlspecialchars($domain_bilgi['guncelleme_tarihi']) : 'Güncellenmedi'; ?>
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                        <a href="domain-detay.php?id=<?php echo $domain_id; ?>" class="btn btn-secondary ml-2">İptal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı yükle
include_once __DIR__ . '/../resources/templates/footer.php';
?> 