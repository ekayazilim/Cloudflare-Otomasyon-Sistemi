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
use App\Yardimci;

// Oturum kontrolü
$kullanici = new Kullanici();
$kullanici_bilgileri = $kullanici->oturumKontrol();

if (!$kullanici_bilgileri) {
    Yardimci::yonlendir('giris.php');
}

// Domain sınıfı örneği oluştur
$domain = new Domain();

// API anahtarlarını getir
$api_anahtarlari = $kullanici->apiAnahtarlariniListele($kullanici_bilgileri['id']);

// Senkronizasyon işlemi
$mesaj = '';
$mesaj_tur = '';

if (isset($_GET['senkronize']) && !empty($_GET['api_anahtar_id'])) {
    $api_anahtar_id = (int)$_GET['api_anahtar_id'];
    
    // API anahtarının kullanıcıya ait olup olmadığını kontrol et
    $api_anahtar_var = false;
    foreach ($api_anahtarlari as $api) {
        if ($api['id'] == $api_anahtar_id) {
            $api_anahtar_var = true;
            break;
        }
    }
    
    if ($api_anahtar_var) {
        // Domainleri senkronize et
        $sonuc = $domain->domainleriSenkronizeEt($kullanici_bilgileri['id'], $api_anahtar_id);
        
        if ($sonuc) {
            $mesaj = "Domainler başarıyla senkronize edildi.";
            $mesaj_tur = "success";
        } else {
            $mesaj = "Domainler senkronize edilirken bir hata oluştu.";
            $mesaj_tur = "danger";
        }
    } else {
        $mesaj = "Geçersiz API anahtarı.";
        $mesaj_tur = "danger";
    }
}

// Sayfalama için parametreler
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
$limit = $config['sayfa_basina_kayit'] ?? 10;
$offset = ($sayfa - 1) * $limit;

// Arama parametresi
$arama = isset($_GET['arama']) ? Yardimci::temizle($_GET['arama']) : '';

// Domainleri listele
$domainler = $domain->domainleriListele($kullanici_bilgileri['id'], $arama, $limit, $offset);

// Toplam domain sayısı
$toplam_domain = $domain->domainSayisi($kullanici_bilgileri['id'], $arama);
$toplam_sayfa = ceil($toplam_domain / $limit);

// Hata ayıklama için log
error_log("Kullanıcı ID: " . $kullanici_bilgileri['id'] . ", Toplam Domain: " . $toplam_domain);
error_log("Domainler: " . print_r($domainler, true));

// Header'ı yükle
include_once __DIR__ . '/../resources/templates/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Domainler</h1>
            <div>
                <div class="dropdown d-inline-block me-2">
                    <button class="btn btn-success dropdown-toggle" type="button" id="senkronizeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-sync-alt"></i> Cloudflare ile Senkronize Et
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="senkronizeDropdown">
                        <?php if (empty($api_anahtarlari)): ?>
                        <li><a class="dropdown-item disabled" href="#">Önce API Anahtarı Ekleyin</a></li>
                        <?php else: ?>
                        <?php foreach ($api_anahtarlari as $api): ?>
                        <li><a class="dropdown-item" href="?senkronize=1&api_anahtar_id=<?php echo $api['id']; ?>"><?php echo $api['aciklama']; ?></a></li>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                <button type="button" class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#ipSorguModal">
                    <i class="fas fa-search"></i> IP ile Domain Sorgula
                </button>
                <a href="dns-toplu-guncelle.php" class="btn btn-warning me-2">
                    <i class="fas fa-exchange-alt"></i> Toplu DNS Güncelleme
                </a>
                <a href="domain-ekle.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Yeni Domain Ekle
                </a>
            </div>
        </div>
    </div>
</div>

<!-- IP Sorgu Modal -->
<div class="modal fade" id="ipSorguModal" tabindex="-1" aria-labelledby="ipSorguModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ipSorguModalLabel">IP Adresi ile Domain Sorgula</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <form id="ipSorguForm" action="ip-domain-sorgula.php" method="get">
                    <div class="mb-3">
                        <label for="arananIp" class="form-label">IP Adresi</label>
                        <input type="text" class="form-control" id="arananIp" name="ip" required placeholder="Örn: 192.168.1.1">
                    </div>
                    <div class="mb-3">
                        <label for="dns_tipi" class="form-label">DNS Kaydı Tipi</label>
                        <select class="form-select" id="dns_tipi" name="dns_tipi">
                            <option value="A" selected>A</option>
                            <option value="AAAA">AAAA</option>
                            <option value="CNAME">CNAME</option>
                            <option value="MX">MX</option>
                            <option value="TXT">TXT</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                <button type="submit" form="ipSorguForm" class="btn btn-primary">Sorgula</button>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($mesaj)): ?>
<div class="alert alert-<?php echo $mesaj_tur; ?> alert-dismissible fade show" role="alert">
    <?php echo $mesaj; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
</div>
<?php endif; ?>

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
                <h5 class="mb-0">Domain Listesi</h5>
            </div>
            <div class="col-md-6">
                <form action="" method="get" class="d-flex">
                    <input type="text" name="arama" class="form-control" placeholder="Domain ara..." value="<?php echo $arama; ?>">
                    <button type="submit" class="btn btn-light ms-2">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($domainler)): ?>
        <div class="alert alert-info">
            <?php if (!empty($arama)): ?>
            "<?php echo $arama; ?>" aramasına uygun domain bulunamadı.
            <?php else: ?>
            Henüz domain eklenmemiş. <a href="domain-ekle.php">Hemen ekleyin</a>.
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Domain</th>
                        <th>SSL Modu</th>
                        <th>Durum</th>
                        <th>DNS Kayıtları</th>
                        <th>Eklenme Tarihi</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($domainler as $d): ?>
                    <tr>
                        <td><?php echo $d['id']; ?></td>
                        <td><?php echo $d['domain']; ?></td>
                        <td>
                            <span class="ssl-badge ssl-<?php echo strtolower($d['ssl_modu']); ?>">
                                <?php echo strtoupper($d['ssl_modu']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($d['durum'] == 1): ?>
                            <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                            <span class="badge bg-danger">Pasif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            // DNS kayıt sayısını doğrudan domain tablosundan al
                            echo isset($d['dns_kayit_sayisi']) && $d['dns_kayit_sayisi'] > 0 ? $d['dns_kayit_sayisi'] : '0';
                            ?>
                        </td>
                        <td><?php echo Yardimci::tarihFormat($d['olusturma_tarihi']); ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="domain-detay.php?id=<?php echo $d['id']; ?>" class="btn btn-sm btn-info" title="Detay">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="domain-duzenle.php?id=<?php echo $d['id']; ?>" class="btn btn-sm btn-warning" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="dns-kayitlari.php?domain_id=<?php echo $d['id']; ?>" class="btn btn-sm btn-secondary" title="DNS Kayıtları">
                                    <i class="fas fa-server"></i>
                                </a>
                                <a href="firewall-kurallari.php?domain_id=<?php echo $d['id']; ?>" class="btn btn-sm btn-dark" title="Firewall Kuralları">
                                    <i class="fas fa-shield-alt"></i>
                                </a>
                                <a href="domain-sil.php?id=<?php echo $d['id']; ?>" class="btn btn-sm btn-danger delete-confirm" title="Sil">
                                    <i class="fas fa-trash"></i>
                                </a>
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
            <span>Toplam <?php echo $toplam_domain; ?> domain</span>
            <div>
                <a href="dns-toplu-guncelle.php" class="btn btn-warning btn-sm me-2">
                    <i class="fas fa-exchange-alt"></i> Toplu DNS Güncelleme
                </a>
                <a href="domain-ekle.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus-circle"></i> Yeni Domain Ekle
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Önbellek Yönetimi -->
<?php if ($kullanici_bilgileri['yetki'] === 'admin'): ?>
<div class="card mt-4">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0">Önbellek Yönetimi</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Tüm Önbellek</h5>
                        <p class="card-text">Tüm önbelleği temizler</p>
                        <a href="cache-temizle.php?tip=all" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Temizle
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Uygulama Önbelleği</h5>
                        <p class="card-text">Uygulama önbelleğini temizler</p>
                        <a href="cache-temizle.php?tip=app" class="btn btn-warning">
                            <i class="fas fa-broom"></i> Temizle
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Oturum Önbelleği</h5>
                        <p class="card-text">Oturum önbelleğini temizler</p>
                        <a href="cache-temizle.php?tip=session" class="btn btn-info">
                            <i class="fas fa-user-clock"></i> Temizle
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Dosya Önbelleği</h5>
                        <p class="card-text">Dosya önbelleğini temizler</p>
                        <a href="cache-temizle.php?tip=file" class="btn btn-secondary">
                            <i class="fas fa-file"></i> Temizle
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Footer'ı yükle
include_once __DIR__ . '/../resources/templates/footer.php';
?>
