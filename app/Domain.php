<?php

namespace App;

class Domain {
    private $db;
    private $cloudflareAPI;
    
    public function __construct($apiKey = null, $email = null) {
        $this->db = Veritabani::baglan();
        
        // Tabloları oluştur
        $this->tablolariOlustur();
        
        // API anahtarı ve email verilmişse CloudflareAPI nesnesini oluştur
        if ($apiKey && $email) {
            $this->cloudflareAPI = new CloudflareAPI($apiKey, $email);
        }
    }
    
    /**
     * CloudflareAPI nesnesini ayarlar
     * 
     * @param string $apiKey API anahtarı
     * @param string $email E-posta
     */
    public function setCloudflareAPI($apiKey, $email) {
        $this->cloudflareAPI = new CloudflareAPI($apiKey, $email);
    }
    
    /**
     * Gerekli tabloları oluşturur
     */
    private function tablolariOlustur() {
        // Domainler tablosu
        $this->db->tabloOlustur('domainler', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            kullanici_id INT NOT NULL,
            api_anahtar_id INT NOT NULL,
            zone_id VARCHAR(32) NOT NULL,
            domain VARCHAR(255) NOT NULL,
            plan VARCHAR(50),
            durum TINYINT(1) NOT NULL DEFAULT 1,
            ssl_modu VARCHAR(20) DEFAULT 'flexible',
            dns_kayit_sayisi INT NOT NULL DEFAULT 0,
            olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
            FOREIGN KEY (api_anahtar_id) REFERENCES cloudflare_api_anahtarlari(id) ON DELETE CASCADE
        ");
        
        // DNS Kayıtları tablosu
        $this->db->tabloOlustur('dns_kayitlari', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            domain_id INT NOT NULL,
            record_id VARCHAR(32) NOT NULL,
            tip VARCHAR(10) NOT NULL,
            isim VARCHAR(255) NOT NULL,
            icerik TEXT NOT NULL,
            ttl INT NOT NULL DEFAULT 1,
            oncelik INT NOT NULL DEFAULT 0,
            proxied TINYINT(1) NOT NULL DEFAULT 0,
            olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (domain_id) REFERENCES domainler(id) ON DELETE CASCADE
        ");
        
        // Firewall Kuralları tablosu
        $this->db->tabloOlustur('firewall_kurallari', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            domain_id INT NOT NULL,
            kural_id VARCHAR(32) NOT NULL,
            aciklama VARCHAR(255),
            filtre TEXT NOT NULL,
            aksiyon VARCHAR(20) NOT NULL,
            oncelik INT NOT NULL DEFAULT 1,
            durum TINYINT(1) NOT NULL DEFAULT 1,
            olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (domain_id) REFERENCES domainler(id) ON DELETE CASCADE
        ");
        
        // Özel Nameserver tablosu
        $this->db->tabloOlustur('ozel_nameserverlar', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id VARCHAR(32) NOT NULL,
            ns_id VARCHAR(32) NOT NULL,
            ns_name VARCHAR(255) NOT NULL,
            zone_tag VARCHAR(32),
            status VARCHAR(50),
            ns_set INT,
            olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP
        ");
        
        // Özel Nameserver DNS Kayıtları tablosu
        $this->db->tabloOlustur('ozel_nameserver_dns_kayitlari', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            ns_id INT NOT NULL,
            tip VARCHAR(10) NOT NULL,
            deger VARCHAR(255) NOT NULL,
            olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ns_id) REFERENCES ozel_nameserverlar(id) ON DELETE CASCADE
        ");
        
        // DNS Firewall Kümeleri tablosu
        $this->db->tabloOlustur('dns_firewall_kumeleri', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_id VARCHAR(32) NOT NULL,
            firewall_id VARCHAR(32) NOT NULL,
            isim VARCHAR(255) NOT NULL,
            upstream_ips TEXT NOT NULL,
            dns_firewall_ips TEXT DEFAULT NULL,
            deprecate_any_requests TINYINT(1) DEFAULT 0,
            origin_direct TINYINT(1) DEFAULT 0,
            aktif TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ");
        
        // DNS Firewall Reverse DNS tablosu
        $this->db->tabloOlustur('dns_firewall_reverse_dns', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            firewall_id INT NOT NULL,
            ptr VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (firewall_id) REFERENCES dns_firewall_kumeleri(id) ON DELETE CASCADE
        ");
    }
    
    // ... diğer metodlar
    
    /**
     * Kullanıcıya ait domainleri listeler
     * 
     * @param int $kullanici_id Kullanıcı ID
     * @param string $arama Arama kelimesi (opsiyonel)
     * @param int $limit Sayfa başına kayıt sayısı (opsiyonel)
     * @param int $offset Başlangıç kaydı (opsiyonel)
     * @return array Domainler
     */
    public function domainleriListele($kullanici_id, $arama = '', $limit = null, $offset = 0) {
        if (empty($kullanici_id)) {
            error_log("Kullanıcı ID boş olamaz");
            return [];
        }
        
        $sql = "SELECT d.*, ca.email as api_email, ca.api_anahtari as api_key 
                FROM domainler d 
                LEFT JOIN cloudflare_api_anahtarlari ca ON d.api_anahtar_id = ca.id 
                WHERE d.kullanici_id = ?";
        $params = [$kullanici_id];
        
        if (!empty($arama)) {
            $sql .= " AND d.domain LIKE ?";
            $params[] = "%$arama%";
        }
        
        $sql .= " ORDER BY d.olusturma_tarihi DESC";
        
        if ($limit !== null) {
            $sql .= " LIMIT ?, ?";
            $params[] = (int)$offset;
            $params[] = (int)$limit;
        }
        
        return $this->db->getirTumu($sql, $params);
    }
    
    /**
     * Kullanıcıya ait domain sayısını getirir
     * 
     * @param int $kullanici_id Kullanıcı ID
     * @param string $arama Arama kelimesi (opsiyonel)
     * @return int Domain sayısı
     */
    public function domainSayisi($kullanici_id, $arama = '') {
        if (empty($kullanici_id)) {
            error_log("Kullanıcı ID boş olamaz");
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as toplam FROM domainler d WHERE d.kullanici_id = ?";
        $params = [$kullanici_id];
        
        if (!empty($arama)) {
            $sql .= " AND d.domain LIKE ?";
            $params[] = "%$arama%";
        }
        
        $sonuc = $this->db->getir($sql, $params);
        return $sonuc ? $sonuc['toplam'] : 0;
    }
    
    /**
     * Domain için DNS kayıtlarını senkronize eder
     * 
     * @param int $domain_id Domain ID
     * @param string $zone_id Zone ID
     * @return bool İşlem sonucu
     */
    private function dnsKayitlariniSenkronizeEt($domain_id, $zone_id) {
        if (empty($domain_id) || empty($zone_id)) {
            error_log("Domain ID veya Zone ID boş olamaz");
            return false;
        }
        
        try {
            error_log("DNS kayıtları alınıyor: Zone ID: $zone_id");
            
            // DNS kayıtlarını Cloudflare'den al
            $dns_kayitlari = $this->cloudflareAPI->dnsKayitlariniListele($zone_id);
            
            // Yanıt formatını kontrol et
            if ($dns_kayitlari === false) {
                error_log("DNS kayıtları alınamadı: Zone ID: $zone_id");
                return false;
            }
            
            // Yanıt formatını kontrol et
            if (!is_array($dns_kayitlari)) {
                error_log("DNS kayıtları geçersiz format (dizi değil): " . gettype($dns_kayitlari));
                return false;
            }
            
            if (!isset($dns_kayitlari['success']) || !$dns_kayitlari['success']) {
                error_log("DNS kayıtları başarısız yanıt: " . json_encode($dns_kayitlari));
                return false;
            }
            
            if (!isset($dns_kayitlari['result']) || !is_array($dns_kayitlari['result'])) {
                error_log("DNS kayıtları sonuç dizisi bulunamadı veya geçersiz format");
                // Sonuç boş olsa bile işlemi başarılı sayalım, sadece kayıtları temizleyelim
                $silme_sonuc = $this->db->sil('dns_kayitlari', "domain_id = ?", [$domain_id]);
                error_log("Mevcut DNS kayıtları silindi: " . ($silme_sonuc !== false ? "Başarılı" : "Başarısız") . " - Domain ID: $domain_id");
                
                // Domain tablosunda DNS kayıt sayısını 0 olarak güncelle
                $guncelleme_sonuc = $this->db->guncelle('domainler', [
                    'dns_kayit_sayisi' => 0
                ], "id = :id", ['id' => $domain_id]);
                
                error_log("Domain DNS kayıt sayısı 0 olarak güncellendi: " . ($guncelleme_sonuc ? "Başarılı" : "Başarısız") . " - Domain ID: $domain_id");
                return true;
            }
            
            // Veritabanı tablosunu kontrol et
            $tablo_var_mi = $this->db->sorgu("SHOW TABLES LIKE 'dns_kayitlari'");
            if (!$tablo_var_mi || $tablo_var_mi->rowCount() === 0) {
                error_log("dns_kayitlari tablosu bulunamadı. Tablo oluşturuluyor...");
                $this->tablolariOlustur();
            }
            
            // Mevcut DNS kayıtlarını sil
            $silme_sonuc = $this->db->sil('dns_kayitlari', "domain_id = ?", [$domain_id]);
            error_log("Mevcut DNS kayıtları silindi: " . ($silme_sonuc !== false ? "Başarılı" : "Başarısız") . " - Domain ID: $domain_id");
            
            // Yeni DNS kayıtlarını ekle
            $eklenen_kayit_sayisi = 0;
            
            // Result dizisinin boş olup olmadığını kontrol et
            if (empty($dns_kayitlari['result'])) {
                error_log("DNS kayıtları boş: Zone ID: $zone_id");
                // Domain tablosunda DNS kayıt sayısını 0 olarak güncelle
                $guncelleme_sonuc = $this->db->guncelle('domainler', [
                    'dns_kayit_sayisi' => 0
                ], "id = :id", ['id' => $domain_id]);
                
                error_log("Domain DNS kayıt sayısı 0 olarak güncellendi: " . ($guncelleme_sonuc ? "Başarılı" : "Başarısız") . " - Domain ID: $domain_id");
                return true;
            }
            
            foreach ($dns_kayitlari['result'] as $kayit) {
                // Kayıt verilerini kontrol et
                if (!isset($kayit['id']) || !isset($kayit['type']) || !isset($kayit['name'])) {
                    error_log("Geçersiz DNS kaydı, atlanıyor: " . json_encode($kayit));
                    continue;
                }
                
                $eklenecek_veri = [
                    'domain_id' => $domain_id,
                    'record_id' => $kayit['id'],
                    'tip' => $kayit['type'],
                    'isim' => $kayit['name'],
                    'icerik' => isset($kayit['content']) ? $kayit['content'] : '',
                    'ttl' => isset($kayit['ttl']) ? $kayit['ttl'] : 1,
                    'proxied' => isset($kayit['proxied']) && $kayit['proxied'] ? 1 : 0
                ];
                
                $ekleme_sonuc = $this->db->ekle('dns_kayitlari', $eklenecek_veri);
                if ($ekleme_sonuc) {
                    $eklenen_kayit_sayisi++;
                } else {
                    error_log("DNS kaydı eklenemedi: " . json_encode($eklenecek_veri));
                }
            }
            
            error_log("Toplam " . count($dns_kayitlari['result']) . " DNS kaydından " . $eklenen_kayit_sayisi . " tanesi eklendi");
            
            // Domain tablosunda DNS kayıt sayısını güncelle
            $guncelleme_sonuc = $this->db->guncelle('domainler', [
                'dns_kayit_sayisi' => $eklenen_kayit_sayisi
            ], "id = :id", ['id' => $domain_id]);
            
            error_log("Domain DNS kayıt sayısı güncellendi: " . ($guncelleme_sonuc ? "Başarılı" : "Başarısız") . " - Domain ID: $domain_id - Kayıt sayısı: $eklenen_kayit_sayisi");
            
            return true;
        } catch (\Exception $e) {
            error_log("DNS kayıtları senkronizasyon hatası: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Domain için DNS kayıtlarını listeler
     * 
     * @param int $domain_id Domain ID
     * @return array DNS kayıtları
     */
    public function dnsKayitlariniListele($domain_id) {
        if (empty($domain_id)) {
            error_log("Domain ID boş olamaz");
            return [];
        }
        
        return $this->db->getirTumu("SELECT * FROM dns_kayitlari WHERE domain_id = ? ORDER BY isim ASC", [$domain_id]);
    }
    
    /**
     * Domain için Firewall kurallarını senkronize eder
     * 
     * @param int $domain_id Domain ID
     * @param string $zone_id Zone ID
     * @return bool İşlem sonucu
     */
    public function firewallKurallariniSenkronizeEt($domain_id, $zone_id) {
        if (empty($domain_id) || empty($zone_id)) {
            error_log("Domain ID veya Zone ID boş olamaz");
            return false;
        }
        
        try {
            error_log("Firewall kuralları alınıyor: Zone ID: $zone_id");
            
            // Firewall kurallarını Cloudflare'den al (Firewall Rules API)
            $firewall_kurallari = $this->cloudflareAPI->firewallKurallariListele($zone_id);
            
            // Yanıt formatını kontrol et
            if ($firewall_kurallari === false) {
                error_log("Firewall kuralları alınamadı: Zone ID: $zone_id");
                return false;
            }
            
            // Yanıt formatını kontrol et
            if (!is_array($firewall_kurallari)) {
                error_log("Firewall kuralları geçersiz format (dizi değil): " . gettype($firewall_kurallari));
                return false;
            }
            
            if (!isset($firewall_kurallari['success']) || !$firewall_kurallari['success']) {
                error_log("Firewall kuralları başarısız yanıt: " . json_encode($firewall_kurallari));
                return false;
            }
            
            if (!isset($firewall_kurallari['result']) || !is_array($firewall_kurallari['result'])) {
                error_log("Firewall kuralları sonuç dizisi bulunamadı veya geçersiz format");
                // Sonuç boş olsa bile işlemi başarılı sayalım, sadece kayıtları temizleyelim
                $silme_sonuc = $this->db->sil('firewall_kurallari', "domain_id = ?", [$domain_id]);
                error_log("Mevcut Firewall kuralları silindi: " . ($silme_sonuc !== false ? "Başarılı" : "Başarısız") . " - Domain ID: $domain_id");
                return true;
            }
            
            // Veritabanı tablosunu kontrol et
            $tablo_var_mi = $this->db->sorgu("SHOW TABLES LIKE 'firewall_kurallari'");
            if (!$tablo_var_mi || $tablo_var_mi->rowCount() === 0) {
                error_log("firewall_kurallari tablosu bulunamadı. Tablo oluşturuluyor...");
                $this->tablolariOlustur();
            }
            
            // Mevcut Firewall kurallarını sil
            $silme_sonuc = $this->db->sil('firewall_kurallari', "domain_id = ?", [$domain_id]);
            error_log("Mevcut Firewall kuralları silindi: " . ($silme_sonuc !== false ? "Başarılı" : "Başarısız") . " - Domain ID: $domain_id");
            
            // Result dizisinin boş olup olmadığını kontrol et
            if (empty($firewall_kurallari['result'])) {
                error_log("Firewall kuralları boş: Zone ID: $zone_id");
                return true;
            }
            
            // Yeni Firewall kurallarını ekle
            $eklenen_kural_sayisi = 0;
            foreach ($firewall_kurallari['result'] as $kural) {
                // Kural verilerini kontrol et
                if (!isset($kural['id'])) {
                    error_log("Kural ID bulunamadı, atlanıyor: " . json_encode($kural));
                    continue;
                }
                
                $eklenecek_veri = [
                    'domain_id' => $domain_id,
                    'kural_id' => $kural['id'],
                    'aciklama' => isset($kural['description']) ? $kural['description'] : $kural['id'],
                    'filtre' => isset($kural['filter']) ? json_encode($kural['filter']) : '{}',
                    'aksiyon' => isset($kural['action']) ? $kural['action'] : 'unknown',
                    'oncelik' => isset($kural['priority']) ? $kural['priority'] : 0,
                    'durum' => isset($kural['paused']) ? ($kural['paused'] ? 0 : 1) : 1
                ];
                
                $ekleme_sonuc = $this->db->ekle('firewall_kurallari', $eklenecek_veri);
                if ($ekleme_sonuc) {
                    $eklenen_kural_sayisi++;
                } else {
                    error_log("Firewall kuralı eklenemedi: " . json_encode($eklenecek_veri));
                }
            }
            
            error_log("Toplam " . count($firewall_kurallari['result']) . " Firewall kuralından " . $eklenen_kural_sayisi . " tanesi eklendi");
            
            return true;
        } catch (\Exception $e) {
            error_log("Firewall kuralları senkronizasyon hatası: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Kullanıcıya ait domainleri Cloudflare ile senkronize eder
     * 
     * @param int $kullanici_id Kullanıcı ID
     * @param int $api_anahtar_id API Anahtar ID (opsiyonel)
     * @return bool İşlem sonucu
     */
    public function domainleriSenkronizeEt($kullanici_id, $api_anahtar_id = null) {
        if (empty($kullanici_id)) {
            error_log("Kullanıcı ID boş olamaz");
            return false;
        }
        
        try {
            // API anahtarına göre filtrele
            $kosul = "d.kullanici_id = ?";
            $params = [$kullanici_id];
            
            if (!empty($api_anahtar_id)) {
                $kosul .= " AND d.api_anahtar_id = ?";
                $params[] = $api_anahtar_id;
            }
            
            // Kullanıcının domainlerini getir
            $domainler = $this->db->getirTumu("
                SELECT d.*, ca.email, ca.api_anahtari 
                FROM domainler d
                JOIN cloudflare_api_anahtarlari ca ON d.api_anahtar_id = ca.id
                WHERE $kosul
            ", $params);
            
            if (empty($domainler)) {
                error_log("Senkronize edilecek domain bulunamadı: $kosul");
                return false;
            }
            
            error_log("Kullanıcı ID: $kullanici_id, Toplam Domain: " . count($domainler));
            
            $basarili_sayisi = 0;
            $hata_sayisi = 0;
            
            foreach ($domainler as $domain) {
                try {
                    // Her domain için CloudflareAPI nesnesini oluştur
                    $this->cloudflareAPI = new CloudflareAPI($domain['api_anahtari'], $domain['email']);
                    
                    if (!$this->cloudflareAPI) {
                        error_log("Domain için CloudflareAPI nesnesi oluşturulamadı: " . $domain['domain']);
                        $hata_sayisi++;
                        continue;
                    }
                    
                    error_log("Domain senkronize ediliyor: " . $domain['domain'] . " (Zone ID: " . $domain['zone_id'] . ")");
                    
                    // Zone ID kontrolü
                    if (empty($domain['zone_id'])) {
                        error_log("Zone ID boş: " . $domain['domain']);
                        $hata_sayisi++;
                        continue;
                    }
                    
                    // Zone detaylarını al
                    $zone_detaylari = $this->cloudflareAPI->zoneDetay($domain['zone_id']);
                    
                    if (!is_array($zone_detaylari) || !isset($zone_detaylari['success']) || !$zone_detaylari['success']) {
                        error_log("Zone detayları alınamadı: " . $domain['domain'] . " - " . (is_array($zone_detaylari) ? json_encode($zone_detaylari) : "API yanıtı geçersiz"));
                        $hata_sayisi++;
                        continue;
                    }
                    
                    // DNS kayıtlarını senkronize et
                    $dns_sonuc = $this->dnsKayitlariniSenkronizeEt($domain['id'], $domain['zone_id']);
                    error_log("DNS kayıtları senkronizasyon sonucu: " . ($dns_sonuc ? "Başarılı" : "Başarısız") . " - " . $domain['domain']);
                    
                    // Firewall kurallarını senkronize et
                    $firewall_sonuc = $this->firewallKurallariniSenkronizeEt($domain['id'], $domain['zone_id']);
                    error_log("Firewall kuralları senkronizasyon sonucu: " . ($firewall_sonuc ? "Başarılı" : "Başarısız") . " - " . $domain['domain']);
                    
                    // Zone planını güncelle
                    if (isset($zone_detaylari['result']['plan']['name'])) {
                        $this->db->guncelle('domainler', [
                            'plan' => $zone_detaylari['result']['plan']['name']
                        ], "id = :id", ['id' => $domain['id']]);
                    }
                    
                    $basarili_sayisi++;
                } catch (\Exception $e) {
                    error_log("Domain senkronizasyon hatası (" . $domain['domain'] . "): " . $e->getMessage());
                    $hata_sayisi++;
                    // Hata olsa bile diğer domainlere devam et
                    continue;
                }
            }
            
            error_log("Domain senkronizasyonu tamamlandı. Toplam: " . count($domainler) . ", Başarılı: " . $basarili_sayisi . ", Hata: " . $hata_sayisi);
            
            return $basarili_sayisi > 0;
        } catch (\Exception $e) {
            error_log("Domainler senkronize edilirken bir hata oluştu: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Domain bilgilerini getirir
     * 
     * @param int $domain_id Domain ID
     * @param int $kullanici_id Kullanıcı ID (opsiyonel, güvenlik için)
     * @return array|bool Domain bilgileri veya false
     */
    public function domainGetir($domain_id, $kullanici_id = null) {
        if (empty($domain_id)) {
            error_log("Domain ID boş olamaz");
            return false;
        }
        
        $sql = "SELECT d.*, ca.email as api_email, ca.api_anahtari as api_key 
                FROM domainler d 
                LEFT JOIN cloudflare_api_anahtarlari ca ON d.api_anahtar_id = ca.id 
                WHERE d.id = ?";
        $params = [$domain_id];
        
        if ($kullanici_id) {
            $sql .= " AND d.kullanici_id = ?";
            $params[] = $kullanici_id;
        }
        
        return $this->db->getir($sql, $params);
    }
    
    /**
     * Domain için firewall kurallarını listeler
     * 
     * @param int $domain_id Domain ID
     * @return array Firewall kuralları
     */
    public function firewallKurallariniListele($domain_id) {
        if (empty($domain_id)) {
            error_log("Domain ID boş olamaz");
            return [];
        }
        
        return $this->db->getirTumu("SELECT * FROM firewall_kurallari WHERE domain_id = ? ORDER BY oncelik ASC", [$domain_id]);
    }
    
    /**
     * Domain için DNS kayıtlarını siler
     * 
     * @param int $domain_id Domain ID
     * @return bool İşlem sonucu
     */
    public function dnsKayitlariniSil($domain_id) {
        if (empty($domain_id)) {
            error_log("Domain ID boş olamaz");
            return false;
        }
        
        return $this->db->sil("dns_kayitlari", "domain_id = ?", [$domain_id]);
    }
    
    /**
     * Domain için firewall kurallarını siler
     * 
     * @param int $domain_id Domain ID
     * @return bool İşlem sonucu
     */
    public function firewallKurallariniSil($domain_id) {
        if (empty($domain_id)) {
            error_log("Domain ID boş olamaz");
            return false;
        }
        
        return $this->db->sil("firewall_kurallari", "domain_id = ?", [$domain_id]);
    }
    
    /**
     * Domaini siler
     * 
     * @param int $domain_id Domain ID
     * @return bool İşlem sonucu
     */
    public function domainSil($domain_id) {
        if (empty($domain_id)) {
            error_log("Domain ID boş olamaz");
            return false;
        }
        
        return $this->db->sil("domainler", "id = ?", [$domain_id]);
    }
    
    /**
     * Domain için API anahtarlarını listeler
     * 
     * @param int $kullanici_id Kullanıcı ID
     * @return array API anahtarları
     */
    public function apiAnahtarlariniListele($kullanici_id) {
        if (empty($kullanici_id)) {
            error_log("Kullanıcı ID boş olamaz");
            return [];
        }
        
        return $this->db->getirTumu("SELECT * FROM cloudflare_api_anahtarlari WHERE kullanici_id = ? ORDER BY id DESC", [$kullanici_id]);
    }
    
    /**
     * Domain bilgilerini günceller
     * 
     * @param int $domain_id Domain ID
     * @param array $veriler Güncellenecek veriler
     * @return bool İşlem sonucu
     */
    public function domainGuncelle($domain_id, $veriler) {
        if (empty($domain_id) || empty($veriler)) {
            error_log("Domain ID veya güncellenecek veriler boş olamaz");
            return false;
        }
        
        return $this->db->guncelle("domainler", $veriler, "id = ?", [$domain_id]);
    }
    
    /**
     * Belirli bir DNS kaydını getirir
     * 
     * @param int $kayit_id DNS kaydı ID
     * @return array|bool DNS kaydı bilgileri veya bulunamazsa false
     */
    public function dnsKaydiGetir($kayit_id) {
        if (empty($kayit_id)) {
            error_log("DNS kaydı ID boş olamaz");
            return false;
        }
        
        return $this->db->getir("SELECT * FROM dns_kayitlari WHERE id = ?", [$kayit_id]);
    }
    
    /**
     * DNS kaydı ekler
     * 
     * @param array $veri DNS kaydı verileri
     * @return int|bool Eklenen kaydın ID'si veya başarısızsa false
     */
    public function dnsKaydiEkle($veri) {
        if (empty($veri) || !isset($veri['domain_id'])) {
            error_log("DNS kaydı verileri geçersiz");
            return false;
        }
        
        $dns_id = $this->db->ekle("dns_kayitlari", $veri);
        
        if ($dns_id) {
            // Domain tablosunda DNS kayıt sayısını güncelle
            $dns_kayit_sayisi = $this->db->getir("SELECT COUNT(*) as toplam FROM dns_kayitlari WHERE domain_id = ?", [$veri['domain_id']]);
            
            if ($dns_kayit_sayisi) {
                $this->db->guncelle('domainler', [
                    'dns_kayit_sayisi' => $dns_kayit_sayisi['toplam']
                ], "id = ?", [$veri['domain_id']]);
            }
        }
        
        return $dns_id;
    }
    
    /**
     * DNS kaydını günceller
     * 
     * @param int $kayit_id DNS kaydı ID
     * @param array $veri Güncellenecek veriler
     * @return bool İşlem sonucu
     */
    public function dnsKaydiGuncelle($kayit_id, $veri) {
        if (empty($kayit_id) || empty($veri)) {
            error_log("DNS kaydı ID veya güncellenecek veriler boş olamaz");
            return false;
        }
        
        return $this->db->guncelle("dns_kayitlari", $veri, "id = :kayit_id", ["kayit_id" => $kayit_id]);
    }
    
    /**
     * DNS kaydını siler
     * 
     * @param int $kayit_id DNS kaydı ID
     * @return bool İşlem sonucu
     */
    public function dnsKaydiSil($kayit_id) {
        if (empty($kayit_id)) {
            error_log("DNS kaydı ID boş olamaz");
            return false;
        }
        
        // Önce domain_id'yi alalım
        $dns_kaydi = $this->dnsKaydiGetir($kayit_id);
        
        if (!$dns_kaydi) {
            return false;
        }
        
        $domain_id = $dns_kaydi['domain_id'];
        $silindi = $this->db->sil("dns_kayitlari", "id = ?", [$kayit_id]);
        
        if ($silindi) {
            // Domain tablosunda DNS kayıt sayısını güncelle
            $dns_kayit_sayisi = $this->db->getir("SELECT COUNT(*) as toplam FROM dns_kayitlari WHERE domain_id = ?", [$domain_id]);
            
            if ($dns_kayit_sayisi) {
                $this->db->guncelle('domainler', [
                    'dns_kayit_sayisi' => $dns_kayit_sayisi['toplam']
                ], "id = ?", [$domain_id]);
            }
        }
        
        return $silindi;
    }
    
    // ... diğer metodlar
}
