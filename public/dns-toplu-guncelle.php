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
    exit;
}

// Sayfa başlığı
$sayfa_basligi = 'Toplu DNS Güncelleme';
include_once __DIR__ . '/../resources/templates/header.php';

// Veritabanı bağlantısı ve domain sınıfı örneği
$domain = new Domain();
$db = App\Veritabani::baglan();

// IP parametresi kontrolü
$dns_tipi = isset($_GET['dns_tipi']) ? trim($_GET['dns_tipi']) : 'A';
$kaynak_ip = isset($_GET['kaynak_ip']) ? trim($_GET['kaynak_ip']) : '';

// Ajax isteği için 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['islem']) && $_POST['islem'] === 'guncelle') {
    // Güvenlik için header ayarlama (headers already sent hatasını önlemek için kontrol ekliyoruz)
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    $kaynak_ip = trim($_POST['kaynak_ip'] ?? '');
    $hedef_ip = trim($_POST['hedef_ip'] ?? '');
    $dns_tipi = trim($_POST['dns_tipi'] ?? 'A');
    $proxied = isset($_POST['proxied']) && $_POST['proxied'] === '1';
    $proxy_durum = isset($_POST['proxy_durum']) ? $_POST['proxy_durum'] : 'korunsun';
    
    // Validasyon
    if (empty($kaynak_ip) || empty($hedef_ip)) {
        echo json_encode(['basarili' => false, 'mesaj' => 'Kaynak ve hedef IP adresleri gereklidir.']);
        exit;
    }
    
    try {
        // Kullanıcının tüm domainlerini al
        $domainler = $domain->domainleriListele($kullanici_bilgileri['id']);
        
        $sonuclar = [];
        
        foreach ($domainler as $domain_bilgi) {
            // API anahtarı bilgilerini al
            $api_anahtar = $kullanici->apiAnahtariGetir($domain_bilgi['api_anahtar_id']);
            
            if (!$api_anahtar) {
                $sonuclar[] = [
                    'domain' => $domain_bilgi['domain'],
                    'basarili' => false,
                    'mesaj' => 'API anahtarı bulunamadı'
                ];
                continue;
            }
            
            // CloudflareAPI nesnesini hazırla
            $cloudflare = new CloudflareAPI($api_anahtar['api_anahtari'] ?? '', $api_anahtar['email'] ?? '');
            
            // Domaine ait DNS kayıtlarını al
            $dns_kayitlari = $cloudflare->dnsKayitlariniListele($domain_bilgi['zone_id']);
            
            if (!isset($dns_kayitlari['success']) || !$dns_kayitlari['success']) {
                $sonuclar[] = [
                    'domain' => $domain_bilgi['domain'],
                    'basarili' => false,
                    'mesaj' => 'DNS kayıtları alınamadı'
                ];
                continue;
            }
            
            $guncellenen_kayit_sayisi = 0;
            $hatalar = [];
            
            // DNS kayıtlarını kontrol et
            foreach ($dns_kayitlari['result'] as $kayit) {
                // Sadece belirtilen DNS tipindeki kayıtları ve kaynak IP'yi içeren kayıtları güncelle
                if ($kayit['type'] === $dns_tipi && $kayit['content'] === $kaynak_ip) {
                    // Proxy durumunu belirle
                    $yeni_proxy_durumu = $kayit['proxied'];
                    
                    if ($proxy_durum === 'aktif') {
                        $yeni_proxy_durumu = true;
                    } elseif ($proxy_durum === 'pasif') {
                        $yeni_proxy_durumu = false;
                    } elseif ($proxied) {
                        // Eski proxy mantığı (checkbox işaretlenirse aktif et)
                        $yeni_proxy_durumu = true;
                    }
                    
                    // Güncelleme verilerini hazırla
                    $guncelleme_verileri = [
                        'type' => $kayit['type'],
                        'name' => $kayit['name'],
                        'content' => $hedef_ip,
                        'ttl' => $kayit['ttl'],
                        'proxied' => $yeni_proxy_durumu
                    ];
                    
                    // DNS kaydını güncelle
                    $guncelleme_sonucu = $cloudflare->dnsKaydiGuncelle(
                        $domain_bilgi['zone_id'],
                        $kayit['id'],
                        $guncelleme_verileri
                    );
                    
                    if (isset($guncelleme_sonucu['success']) && $guncelleme_sonucu['success']) {
                        $guncellenen_kayit_sayisi++;
                    } else {
                        $hata_mesaji = isset($guncelleme_sonucu['errors']) && isset($guncelleme_sonucu['errors'][0]['message']) 
                            ? $guncelleme_sonucu['errors'][0]['message'] 
                            : 'Bilinmeyen hata';
                        $hatalar[] = "Kayıt: {$kayit['name']} - Hata: {$hata_mesaji}";
                    }
                }
            }
            
            // Sonuçları kaydet
            if ($guncellenen_kayit_sayisi > 0) {
                $sonuclar[] = [
                    'domain' => $domain_bilgi['domain'],
                    'basarili' => true,
                    'mesaj' => "{$guncellenen_kayit_sayisi} kayıt güncellendi" . (count($hatalar) > 0 ? ", " . count($hatalar) . " hata oluştu" : "")
                ];
                
                // Domain için güncel DNS kayıtlarını veritabanına senkronize et
                // Özel metodu doğrudan çağırmak yerine, sınıf üzerinden çağırıyoruz
                $domain_obj = new Domain();
                $domain_obj->setCloudflareAPI($api_anahtar['api_anahtari'] ?? '', $api_anahtar['email'] ?? '');
                // Domain sınıfında dnsKayitlariniGetir metodu yok, onun yerine domainleriSenkronizeEt'i kullanıyoruz
                // Bu metod, ilgili domaine ait DNS kayıtlarını da Cloudflare ile senkronize edecektir
                $domain_obj->domainleriSenkronizeEt($kullanici_bilgileri['id'], $domain_bilgi['api_anahtar_id']);
            } else if (count($hatalar) > 0) {
                $sonuclar[] = [
                    'domain' => $domain_bilgi['domain'],
                    'basarili' => false,
                    'mesaj' => "Güncelleme yapılamadı: " . implode("; ", $hatalar)
                ];
            } else {
                $sonuclar[] = [
                    'domain' => $domain_bilgi['domain'],
                    'basarili' => false,
                    'mesaj' => "Değiştirilecek kayıt bulunamadı"
                ];
            }
            
            // İşlem sonucunu loglama
            $log_mesaji = date('Y-m-d H:i:s') . " - Domain: {$domain_bilgi['domain']} - ";
            $log_mesaji .= $guncellenen_kayit_sayisi > 0 
                ? "{$guncellenen_kayit_sayisi} adet {$dns_tipi} kaydı {$kaynak_ip} -> {$hedef_ip} olarak güncellendi" 
                : "Güncelleme yapılamadı";
                
            if (count($hatalar) > 0) {
                $log_mesaji .= " - Hatalar: " . implode("; ", $hatalar);
            }
            
            // Log kaydını veritabanına ekle
            $log_verileri = [
                'kullanici_id' => $kullanici_bilgileri['id'],
                'islem_turu' => 'dns_toplu_guncelleme',
                'domain_id' => $domain_bilgi['id'],
                'aciklama' => $log_mesaji,
                'ip_adresi' => $_SERVER['REMOTE_ADDR'],
                'tarih' => date('Y-m-d H:i:s')
            ];
            
            try {
                $db->ekle('islem_loglari', $log_verileri);
            } catch (Exception $e) {
                // Log eklenirken hata olursa görmezden gel
            }
        }
        
        echo json_encode(['basarili' => true, 'sonuclar' => $sonuclar]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['basarili' => false, 'mesaj' => 'Güncelleme işlemi sırasında bir hata oluştu: ' . $e->getMessage()]);
        exit;
    }
}

// DNS tipleri
$dns_tipleri = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'SRV'];
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Toplu DNS Kayıtları Güncelleme</h1>
            <div>
                <a href="domainler.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Domainlere Dön
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">DNS Kayıtlarını Toplu Güncelle</h5>
            </div>
            <div class="card-body">
                <form id="topluGuncelleForm">
                    <div class="mb-3">
                        <label for="dns_tipi" class="form-label">DNS Kaydı Tipi:</label>
                        <select class="form-select" id="dns_tipi" name="dns_tipi" required>
                            <?php foreach ($dns_tipleri as $tip): ?>
                                <option value="<?php echo $tip; ?>" <?php echo $tip === $dns_tipi ? 'selected' : ''; ?>><?php echo $tip; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Güncellenecek DNS kaydı tipini seçin.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="kaynak_ip" class="form-label">Aranacak IP/Değer:</label>
                        <input type="text" class="form-control" id="kaynak_ip" name="kaynak_ip" required 
                               placeholder="Örn: 192.168.1.1" value="<?php echo htmlspecialchars($kaynak_ip); ?>">
                        <div class="form-text">Değiştirilecek olan mevcut IP veya değer.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="hedef_ip" class="form-label">Yeni IP/Değer:</label>
                        <input type="text" class="form-control" id="hedef_ip" name="hedef_ip" required 
                               placeholder="Örn: 192.168.1.2">
                        <div class="form-text">Kaydın güncelleneceği yeni IP veya değer.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Proxy Durumu (Turuncu Bulut):</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="proxy_durum" id="proxyKorunsun" value="korunsun" checked>
                            <label class="form-check-label" for="proxyKorunsun">
                                Mevcut proxy durumunu koru
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="proxy_durum" id="proxyAktif" value="aktif">
                            <label class="form-check-label" for="proxyAktif">
                                Tüm kayıtlarda proxy'yi aktif et
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="proxy_durum" id="proxyPasif" value="pasif">
                            <label class="form-check-label" for="proxyPasif">
                                Tüm kayıtlarda proxy'yi pasif et
                            </label>
                        </div>
                        <div class="form-text">Güncellenen kayıtların proxy (turuncu bulut) durumunu belirleyin.</div>
                    </div>
                    
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary" id="guncellemeBaslat">
                            <i class="fas fa-sync-alt"></i> Güncellemeyi Başlat
                        </button>
                    </div>
                </form>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Bu işlem, tüm domainlerinizde seçilen DNS tipi için belirtilen mevcut IP/değerleri yeni değerle değiştirecektir.
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Güncelleme Sonuçları</h5>
                <button class="btn btn-sm btn-outline-secondary" id="sonuclariTemizle">
                    <i class="fas fa-eraser"></i> Temizle
                </button>
            </div>
            <div class="card-body">
                <div id="yukleniyor" class="text-center d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Yükleniyor...</span>
                    </div>
                    <p class="mt-2">Güncelleme işlemi devam ediyor. Lütfen bekleyin...</p>
                </div>
                
                <div id="sonuclarListesi" class="list-group"></div>
                
                <div id="sonucYok" class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> Henüz güncelleme yapılmadı.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('topluGuncelleForm');
    const sonuclarListesi = document.getElementById('sonuclarListesi');
    const sonucYok = document.getElementById('sonucYok');
    const yukleniyor = document.getElementById('yukleniyor');
    const sonuclariTemizle = document.getElementById('sonuclariTemizle');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Kullanıcıya onay sor
        if (!confirm('Bu işlem tüm domainlerde seçili kayıtları güncelleyecek. Devam etmek istediğinize emin misiniz?')) {
            return;
        }
        
        // Form verilerini al
        const formVeri = new FormData(form);
        formVeri.append('islem', 'guncelle');
        
        // Yükleniyor göster
        sonuclarListesi.innerHTML = '';
        sonucYok.classList.add('d-none');
        yukleniyor.classList.remove('d-none');
        
        // Ajax isteği gönder
        fetch('dns-toplu-guncelle.php', {
            method: 'POST',
            body: formVeri
        })
        .then(response => response.json())
        .then(data => {
            // Yükleniyor gizle
            yukleniyor.classList.add('d-none');
            
            if (data.basarili && data.sonuclar && data.sonuclar.length > 0) {
                // Sonuçları listele
                data.sonuclar.forEach(function(sonuc) {
                    const sonucItem = document.createElement('div');
                    sonucItem.className = 'list-group-item';
                    
                    const sonucBaslik = document.createElement('div');
                    sonucBaslik.className = 'd-flex justify-content-between align-items-center';
                    
                    const domainAdi = document.createElement('h6');
                    domainAdi.className = 'mb-1';
                    domainAdi.textContent = sonuc.domain;
                    
                    const durum = document.createElement('span');
                    durum.className = 'badge rounded-pill ' + (sonuc.basarili ? 'bg-success' : 'bg-danger');
                    durum.textContent = sonuc.basarili ? 'Başarılı' : 'Başarısız';
                    
                    sonucBaslik.appendChild(domainAdi);
                    sonucBaslik.appendChild(durum);
                    
                    const sonucMesaj = document.createElement('p');
                    sonucMesaj.className = 'mb-1 ' + (sonuc.basarili ? 'text-success' : 'text-danger');
                    sonucMesaj.textContent = sonuc.mesaj;
                    
                    const sonucZaman = document.createElement('small');
                    sonucZaman.className = 'text-muted';
                    sonucZaman.textContent = new Date().toLocaleTimeString();
                    
                    sonucItem.appendChild(sonucBaslik);
                    sonucItem.appendChild(sonucMesaj);
                    sonucItem.appendChild(sonucZaman);
                    
                    sonuclarListesi.appendChild(sonucItem);
                });
            } else {
                // Hata mesajı göster
                const hataItem = document.createElement('div');
                hataItem.className = 'alert alert-danger';
                hataItem.textContent = data.mesaj || 'Güncelleme sırasında bir hata oluştu.';
                sonuclarListesi.appendChild(hataItem);
            }
        })
        .catch(error => {
            // Yükleniyor gizle
            yukleniyor.classList.add('d-none');
            
            // Hata mesajı göster
            const hataItem = document.createElement('div');
            hataItem.className = 'alert alert-danger';
            hataItem.textContent = 'Sunucu ile iletişim sırasında bir hata oluştu: ' + error.message;
            sonuclarListesi.appendChild(hataItem);
        });
    });
    
    // Sonuçları temizle butonu
    sonuclariTemizle.addEventListener('click', function() {
        sonuclarListesi.innerHTML = '';
        sonucYok.classList.remove('d-none');
    });
});
</script>

<?php include_once __DIR__ . '/../resources/templates/footer.php'; ?> 