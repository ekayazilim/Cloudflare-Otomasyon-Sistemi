<?php

namespace App;

class CloudflareAPI {
    private $apiKey;
    private $email;
    private $apiUrl;
    private $timeout;
    
    public function __construct($apiKey, $email) {
        $config = require_once __DIR__ . '/../config/cloudflare.php';
        
        $this->apiKey = $apiKey;
        $this->email = $email;
        $this->apiUrl = is_array($config) && isset($config['api_url']) ? $config['api_url'] : 'https://api.cloudflare.com/client/v4';
        $this->timeout = is_array($config) && isset($config['timeout']) ? $config['timeout'] : 30;
    }
    
    /**
     * API isteği gönderir
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP metodu (GET, POST, PUT, DELETE)
     * @param array $data İstek verileri
     * @return array|bool Yanıt (başarısız olursa false döner)
     */
    private function apiIstek($endpoint, $method = 'GET', $data = null) {
        try {
            // URL'nin doğru formatta olduğundan emin ol
            if (empty($endpoint)) {
                throw new \Exception("API endpoint boş olamaz");
            }
            
            // URL'yi oluştur ve sonundaki slash'ı kontrol et
            $baseUrl = rtrim($this->apiUrl, '/');
            $endpoint = ltrim($endpoint, '/');
            $url = $baseUrl . '/' . $endpoint;
            
            $headers = [
                'X-Auth-Email: ' . $this->email,
                'X-Auth-Key: ' . $this->apiKey,
                'Content-Type: application/json'
            ];
            
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
            } else if ($method === 'PUT') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
            } else if ($method === 'DELETE') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
            }
            
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            if ($error) {
                error_log("API isteği başarısız: " . $error);
                return false;
            }
            
            if (empty($response)) {
                error_log("API yanıtı boş");
                return false;
            }
            
            $responseData = json_decode($response, true);
            
            // Yanıt başarısız veya boş ise kontrol et
            if ($responseData === false || $responseData === null) {
                error_log("API yanıtı geçersiz: " . $response);
                return false;
            }
            
            // Success kontrolü yapmadan önce responseData'nın bir dizi olduğunu ve 'success' anahtarının var olduğunu kontrol et
            if (is_array($responseData) && isset($responseData['success']) && !$responseData['success'] && $httpCode !== 200) {
                $hataMsg = isset($responseData['errors']) && is_array($responseData['errors']) && isset($responseData['errors'][0]['message']) 
                    ? $responseData['errors'][0]['message'] 
                    : 'Bilinmeyen API hatası';
                error_log("Cloudflare API Hatası: " . $hataMsg);
                return false;
            }
            
            return $responseData;
        } catch (\Exception $e) {
            error_log("API isteği hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Zone detaylarını getirir
     * 
     * @param string $zone_id Zone ID
     * @return array Zone detayları
     */
    public function zoneDetay($zone_id) {
        if (empty($zone_id)) {
            error_log("Zone ID boş olamaz");
            return false;
        }
        
        $response = $this->apiIstek("zones/{$zone_id}");
        
        // API yanıtını kontrol et
        if ($response === false) {
            error_log("Zone detayları alınamadı: API isteği başarısız");
            return false;
        }
        
        // Yanıt başarılı değilse veya gerekli alanlar yoksa false döndür
        if (!is_array($response) || !isset($response['success']) || !$response['success']) {
            $hata = is_array($response) && isset($response['errors']) && is_array($response['errors']) && isset($response['errors'][0]['message']) 
                ? $response['errors'][0]['message'] 
                : 'Bilinmeyen API hatası';
            error_log("Zone detayları alınamadı: " . $hata);
            return false;
        }
        
        return $response;
    }
    
    /**
     * Kullanıcı hesabına ait tüm domainleri listeler
     * 
     * @param int $page Sayfa numarası
     * @param int $per_page Sayfa başına kayıt sayısı
     * @param string $name Domain adı ile filtreleme
     * @return array Domain listesi
     */
    public function domainleriListele($page = 1, $per_page = 50, $name = null) {
        $params = [];
        
        if ($page) {
            $params['page'] = $page;
        }
        
        if ($per_page) {
            $params['per_page'] = $per_page;
        }
        
        if ($name) {
            $params['name'] = $name;
        }
        
        $query = !empty($params) ? '?' . http_build_query($params) : '';
        
        return $this->apiIstek('zones' . $query);
    }
    
    /**
     * Yeni domain ekler
     * 
     * @param string $domain Domain adı
     * @param string $type Domain tipi
     * @return array Eklenen domain bilgileri
     */
    public function domainEkle($domain, $type = 'full') {
        $data = [
            'name' => $domain,
            'type' => $type,
            'jump_start' => true
        ];
        
        return $this->apiIstek('zones', 'POST', $data);
    }
    
    /**
     * Domain bilgilerini günceller
     * 
     * @param string $zoneId Zone ID
     * @param array $data Güncellenecek veriler
     * @return array Güncellenen domain bilgileri
     */
    public function domainGuncelle($zoneId, $data) {
        return $this->apiIstek("zones/{$zoneId}", 'PATCH', $data);
    }
    
    /**
     * Domain siler
     * 
     * @param string $zoneId Zone ID
     * @return array Silme işlemi sonucu
     */
    public function domainSil($zoneId) {
        return $this->apiIstek("zones/{$zoneId}", 'DELETE');
    }
    
    /**
     * SSL ayarlarını getirir
     * 
     * @param string $zoneId Zone ID
     * @return array SSL ayarları
     */
    public function sslAyarlariniGetir($zoneId) {
        return $this->apiIstek("zones/{$zoneId}/ssl/universal/settings");
    }
    
    /**
     * SSL ayarlarını günceller
     * 
     * @param string $zoneId Zone ID
     * @param string $mode SSL modu (off, flexible, full, strict)
     * @return array Güncellenen SSL ayarları
     */
    public function sslAyarlariniGuncelle($zoneId, $mode) {
        $data = [
            'value' => $mode
        ];
        
        return $this->apiIstek("zones/{$zoneId}/settings/ssl", 'PATCH', $data);
    }
    
    /**
     * Firewall kurallarını listeler
     * 
     * @param string $zoneId Zone ID
     * @return array Firewall kuralları listesi
     */
    public function firewallKurallariniListele($zoneId) {
        return $this->apiIstek("zones/{$zoneId}/firewall/rules");
    }
    
    /**
     * Firewall kuralı ekler
     * 
     * @param string $zoneId Zone ID
     * @param string $description Kural açıklaması
     * @param array $filter Filtre ayarları
     * @param string $action Aksiyon (block, challenge, allow, js_challenge)
     * @param int $priority Öncelik
     * @return array Eklenen firewall kuralı bilgileri
     */
    public function firewallKuraliEkle($zoneId, $description, $filter, $action, $priority = 1) {
        $data = [
            'description' => $description,
            'filter' => $filter,
            'action' => $action,
            'priority' => $priority
        ];
        
        return $this->apiIstek("zones/{$zoneId}/firewall/rules", 'POST', $data);
    }
    
    /**
     * Firewall kuralı günceller
     * 
     * @param string $zoneId Zone ID
     * @param string $ruleId Kural ID
     * @param array $data Güncellenecek veriler
     * @return array Güncellenen firewall kuralı bilgileri
     */
    public function firewallKuraliGuncelle($zoneId, $ruleId, $data) {
        return $this->apiIstek("zones/{$zoneId}/firewall/rules/{$ruleId}", 'PUT', $data);
    }
    
    /**
     * Firewall kuralı siler
     * 
     * @param string $zoneId Zone ID
     * @param string $ruleId Kural ID
     * @return array Silme işlemi sonucu
     */
    public function firewallKuraliSil($zoneId, $ruleId) {
        return $this->apiIstek("zones/{$zoneId}/firewall/rules/{$ruleId}", 'DELETE');
    }
    
    /**
     * Zone için Firewall kurallarını listeler
     * 
     * @param string $zone_id Zone ID
     * @param array $params Ek parametreler
     * @return array|bool Firewall kuralları
     */
    public function firewallKurallariListele($zone_id, $params = []) {
        if (empty($zone_id)) {
            error_log("Zone ID boş olamaz");
            return false;
        }
        
        $endpoint = "zones/{$zone_id}/firewall/rules";
        
        // Ek parametreler varsa URL'ye ekle
        if (!empty($params)) {
            $query = http_build_query($params);
            $endpoint .= "?{$query}";
        }
        
        $response = $this->apiIstek($endpoint);
        
        // API yanıtını kontrol et ve uygun şekilde işle
        if ($response === false) {
            error_log("Firewall kuralları alınamadı: API isteği başarısız");
            return false;
        }
        
        // Yanıt başarılı değilse veya gerekli alanlar yoksa false döndür
        if (!is_array($response) || !isset($response['success']) || !$response['success']) {
            $hata = is_array($response) && isset($response['errors']) && is_array($response['errors']) && isset($response['errors'][0]['message']) 
                ? $response['errors'][0]['message'] 
                : 'Bilinmeyen API hatası';
            $this->logYaz("Firewall kuralları listelenemedi: " . $hata);
            return false;
        }
        
        // Sonuç dizisi yoksa boş bir dizi ile başarılı yanıt döndür
        if (!isset($response['result']) || !is_array($response['result'])) {
            error_log("Firewall kuralları alındı fakat sonuç boş");
            $response['result'] = [];
        }
        
        return $response;
    }
    
    /**
     * API anahtarının geçerliliğini kontrol eder
     * 
     * @param string $apiKey API anahtarı
     * @param string $email Email
     * @return bool API anahtarı geçerli mi
     */
    public function apiAnahtariKontrol() {
        try {
            // Kullanıcı bilgilerini getirerek API anahtarının geçerliliğini kontrol et
            $response = $this->apiIstek('user');
            return is_array($response) && isset($response['success']) && $response['success'] === true;
        } catch (\Exception $e) {
            error_log("API anahtarı kontrol hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Hesap için uygun özel nameserver alanlarını listeler
     * 
     * @param string $account_id Hesap ID
     * @return array|bool Uygun alanların listesi veya hata durumunda false
     */
    public function ozelNameserverUygunAlanlariListele($account_id) {
        try {
            if (empty($account_id)) {
                error_log("Hesap ID boş olamaz");
                return false;
            }
            
            return $this->apiIstek("accounts/{$account_id}/custom_ns/availability");
        } catch (\Exception $e) {
            error_log("Özel nameserver uygun alanları listeleme hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Hesabın özel nameserver'larını listeler
     * 
     * @param string $account_id Hesap ID
     * @return array|bool Özel nameserver listesi veya hata durumunda false
     */
    public function ozelNameserverListele($account_id) {
        try {
            if (empty($account_id)) {
                error_log("Hesap ID boş olamaz");
                return false;
            }
            
            return $this->apiIstek("accounts/{$account_id}/custom_ns");
        } catch (\Exception $e) {
            error_log("Özel nameserver listeleme hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Hesaba yeni özel nameserver ekler
     * 
     * @param string $account_id Hesap ID
     * @param string $ns_name Nameserver adı (örn: ns1.example.com)
     * @param array $dns_records DNS kayıtları (örn: [['type' => 'A', 'value' => '1.1.1.1']])
     * @param string $zone_tag Alan adı zone ID'si
     * @return array|bool Eklenen nameserver bilgileri veya hata durumunda false
     */
    public function ozelNameserverEkle($account_id, $ns_name, $dns_records, $zone_tag) {
        try {
            if (empty($account_id)) {
                error_log("Hesap ID boş olamaz");
                return false;
            }
            
            if (empty($ns_name)) {
                error_log("Nameserver adı boş olamaz");
                return false;
            }
            
            $data = [
                'ns_name' => $ns_name,
                'dns_records' => $dns_records
            ];
            
            if (!empty($zone_tag)) {
                $data['zone_tag'] = $zone_tag;
            }
            
            return $this->apiIstek("accounts/{$account_id}/custom_ns", 'POST', $data);
        } catch (\Exception $e) {
            error_log("Özel nameserver ekleme hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Hesaptan özel nameserver siler
     * 
     * @param string $account_id Hesap ID
     * @param string $custom_ns_id Özel nameserver ID
     * @return array|bool Silme işlemi sonucu veya hata durumunda false
     */
    public function ozelNameserverSil($account_id, $custom_ns_id) {
        try {
            if (empty($account_id)) {
                error_log("Hesap ID boş olamaz");
                return false;
            }
            
            if (empty($custom_ns_id)) {
                error_log("Özel nameserver ID boş olamaz");
                return false;
            }
            
            return $this->apiIstek("accounts/{$account_id}/custom_ns/{$custom_ns_id}", 'DELETE');
        } catch (\Exception $e) {
            error_log("Özel nameserver silme hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * DNS Firewall kümelerini listeler
     * 
     * @param string $account_id Hesap ID
     * @return array|bool DNS Firewall kümeleri listesi veya hata durumunda false
     */
    public function dnsFirewallListele($account_id) {
        try {
            if (empty($account_id)) {
                error_log("Hesap ID boş olamaz");
                return false;
            }
            
            return $this->apiIstek("accounts/{$account_id}/dns_firewall");
        } catch (\Exception $e) {
            error_log("DNS Firewall listeleme hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * DNS Firewall kümesi detaylarını getirir
     * 
     * @param string $account_id Hesap ID
     * @param string $dns_firewall_id DNS Firewall ID
     * @return array|bool DNS Firewall kümesi detayları veya hata durumunda false
     */
    public function dnsFirewallDetay($account_id, $dns_firewall_id) {
        try {
            if (empty($account_id)) {
                error_log("Hesap ID boş olamaz");
                return false;
            }
            
            if (empty($dns_firewall_id)) {
                error_log("DNS Firewall ID boş olamaz");
                return false;
            }
            
            return $this->apiIstek("accounts/{$account_id}/dns_firewall/{$dns_firewall_id}");
        } catch (\Exception $e) {
            error_log("DNS Firewall detay getirme hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Yeni DNS Firewall kümesi oluşturur
     * 
     * @param string $account_id Hesap ID
     * @param string $name Küme adı
     * @param array $upstream_ips Upstream DNS sunucuları IP adresleri
     * @param array $ek_parametreler Diğer parametreler (opsiyonel)
     * @return array|bool Oluşturulan DNS Firewall kümesi veya hata durumunda false
     */
    public function dnsFirewallOlustur($account_id, $name, $upstream_ips, $ek_parametreler = []) {
        try {
            if (empty($account_id)) {
                error_log("Hesap ID boş olamaz");
                return false;
            }
            
            if (empty($name)) {
                error_log("Küme adı boş olamaz");
                return false;
            }
            
            if (empty($upstream_ips) || !is_array($upstream_ips)) {
                error_log("Upstream IP adresleri geçerli değil");
                return false;
            }
            
            $data = array_merge([
                'name' => $name,
                'upstream_ips' => $upstream_ips
            ], $ek_parametreler);
            
            return $this->apiIstek("accounts/{$account_id}/dns_firewall", 'POST', $data);
        } catch (\Exception $e) {
            error_log("DNS Firewall oluşturma hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * DNS Firewall kümesini günceller
     * 
     * @param string $account_id Hesap ID
     * @param string $dns_firewall_id DNS Firewall ID
     * @param array $data Güncellenecek veriler
     * @return array|bool Güncellenen DNS Firewall kümesi veya hata durumunda false
     */
    public function dnsFirewallGuncelle($account_id, $dns_firewall_id, $data) {
        try {
            if (empty($account_id)) {
                error_log("Hesap ID boş olamaz");
                return false;
            }
            
            if (empty($dns_firewall_id)) {
                error_log("DNS Firewall ID boş olamaz");
                return false;
            }
            
            if (empty($data) || !is_array($data)) {
                error_log("Güncellenecek veriler geçerli değil");
                return false;
            }
            
            return $this->apiIstek("accounts/{$account_id}/dns_firewall/{$dns_firewall_id}", 'PATCH', $data);
        } catch (\Exception $e) {
            error_log("DNS Firewall güncelleme hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * DNS Firewall kümesini siler
     * 
     * @param string $account_id Hesap ID
     * @param string $dns_firewall_id DNS Firewall ID
     * @return array|bool Silme işlemi sonucu veya hata durumunda false
     */
    public function dnsFirewallSil($account_id, $dns_firewall_id) {
        try {
            if (empty($account_id)) {
                error_log("Hesap ID boş olamaz");
                return false;
            }
            
            if (empty($dns_firewall_id)) {
                error_log("DNS Firewall ID boş olamaz");
                return false;
            }
            
            return $this->apiIstek("accounts/{$account_id}/dns_firewall/{$dns_firewall_id}", 'DELETE');
        } catch (\Exception $e) {
            error_log("DNS Firewall silme hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * DNS Firewall kümesi için reverse DNS yapılandırmasını getirir
     * 
     * @param string $account_id Hesap ID
     * @param string $dns_firewall_id DNS Firewall ID
     * @return array|bool Reverse DNS yapılandırması veya hata durumunda false
     */
    public function dnsFirewallReverseDnsGetir($account_id, $dns_firewall_id) {
        try {
            if (empty($account_id)) {
                error_log("Hesap ID boş olamaz");
                return false;
            }
            
            if (empty($dns_firewall_id)) {
                error_log("DNS Firewall ID boş olamaz");
                return false;
            }
            
            return $this->apiIstek("accounts/{$account_id}/dns_firewall/{$dns_firewall_id}/reverse_dns");
        } catch (\Exception $e) {
            error_log("DNS Firewall reverse DNS getirme hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * DNS Firewall kümesi için reverse DNS yapılandırmasını günceller
     * 
     * @param string $account_id Hesap ID
     * @param string $dns_firewall_id DNS Firewall ID
     * @param string $ptr PTR kaydı
     * @return array|bool Güncellenen reverse DNS yapılandırması veya hata durumunda false
     */
    public function dnsFirewallReverseDnsGuncelle($account_id, $dns_firewall_id, $ptr) {
        try {
            if (empty($account_id)) {
                error_log("Hesap ID boş olamaz");
                return false;
            }
            
            if (empty($dns_firewall_id)) {
                error_log("DNS Firewall ID boş olamaz");
                return false;
            }
            
            if (empty($ptr)) {
                error_log("PTR kaydı boş olamaz");
                return false;
            }
            
            $data = [
                'ptr' => $ptr
            ];
            
            return $this->apiIstek("accounts/{$account_id}/dns_firewall/{$dns_firewall_id}/reverse_dns", 'PATCH', $data);
        } catch (\Exception $e) {
            error_log("DNS Firewall reverse DNS güncelleme hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * DNS Firewall kümesi için analitik raporu getirir
     * 
     * @param string $account_id Hesap ID
     * @param string $dns_firewall_id DNS Firewall ID
     * @param array $params Sorgu parametreleri
     * @return array|bool Analitik raporu veya hata durumunda false
     */
    public function dnsFirewallAnalitikRapor($account_id, $dns_firewall_id, $params = []) {
        try {
            if (empty($account_id)) {
                error_log("Hesap ID boş olamaz");
                return false;
            }
            
            if (empty($dns_firewall_id)) {
                error_log("DNS Firewall ID boş olamaz");
                return false;
            }
            
            $query = !empty($params) ? '?' . http_build_query($params) : '';
            
            return $this->apiIstek("accounts/{$account_id}/dns_firewall/{$dns_firewall_id}/dns_analytics/report{$query}");
        } catch (\Exception $e) {
            error_log("DNS Firewall analitik rapor getirme hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * DNS Firewall kümesi için zaman aralıklarına göre analitik raporu getirir
     * 
     * @param string $account_id Hesap ID
     * @param string $dns_firewall_id DNS Firewall ID
     * @param array $params Sorgu parametreleri
     * @return array|bool Analitik raporu veya hata durumunda false
     */
    public function dnsFirewallAnalitikRaporZamanAraliklarinGore($account_id, $dns_firewall_id, $params = []) {
        try {
            if (empty($account_id)) {
                error_log("Hesap ID boş olamaz");
                return false;
            }
            
            if (empty($dns_firewall_id)) {
                error_log("DNS Firewall ID boş olamaz");
                return false;
            }
            
            $query = !empty($params) ? '?' . http_build_query($params) : '';
            
            return $this->apiIstek("accounts/{$account_id}/dns_firewall/{$dns_firewall_id}/dns_analytics/report/bytime{$query}");
        } catch (\Exception $e) {
            error_log("DNS Firewall zaman aralıklarına göre analitik rapor getirme hatası: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Zone listesini getirir
     * 
     * @param array $params Sorgu parametreleri
     * @return array Zone listesi
     */
    public function zoneListele($params = []) {
        $query = !empty($params) ? '?' . http_build_query($params) : '';
        return $this->apiIstek("zones{$query}");
    }
    
    /**
     * Zone için ayarları getirir
     * 
     * @param string $zone_id Zone ID
     * @param string $setting_id Ayar ID (opsiyonel)
     * @return array Ayarlar
     */
    public function zoneAyarlariGetir($zone_id, $setting_id = null) {
        if (empty($zone_id)) {
            error_log("Zone ID boş olamaz");
            return false;
        }
        
        $endpoint = "zones/{$zone_id}/settings";
        if (!empty($setting_id)) {
            $endpoint .= "/{$setting_id}";
        }
        
        return $this->apiIstek($endpoint);
    }
    
    /**
     * Zone için ayar günceller
     * 
     * @param string $zone_id Zone ID
     * @param string $setting_id Ayar ID
     * @param mixed $value Ayar değeri
     * @return array İşlem sonucu
     */
    public function zoneAyariGuncelle($zone_id, $setting_id, $value) {
        if (empty($zone_id)) {
            error_log("Zone ID boş olamaz");
            return false;
        }
        
        if (empty($setting_id)) {
            error_log("Ayar ID boş olamaz");
            return false;
        }
        
        $data = [
            'value' => $value
        ];
        
        return $this->apiIstek("zones/{$zone_id}/settings/{$setting_id}", 'PATCH', $data);
    }
    
    /**
     * Zone için kullanılabilir planları listeler
     * 
     * @param string $zone_id Zone ID
     * @return array Kullanılabilir planlar
     */
    public function zonePlanlariniListele($zone_id) {
        if (empty($zone_id)) {
            error_log("Zone ID boş olamaz");
            return false;
        }
        
        return $this->apiIstek("zones/{$zone_id}/available_plans");
    }
    
    /**
     * Zone için plan detaylarını getirir
     * 
     * @param string $zone_id Zone ID
     * @param string $plan_id Plan ID
     * @return array Plan detayları
     */
    public function zonePlanDetay($zone_id, $plan_id) {
        if (empty($zone_id)) {
            error_log("Zone ID boş olamaz");
            return false;
        }
        
        if (empty($plan_id)) {
            error_log("Plan ID boş olamaz");
            return false;
        }
        
        return $this->apiIstek("zones/{$zone_id}/available_plans/{$plan_id}");
    }
    
    /**
     * Zone için hold durumunu getirir
     * 
     * @param string $zone_id Zone ID
     * @return array Hold durumu
     */
    public function zoneHoldDurumuGetir($zone_id) {
        if (empty($zone_id)) {
            error_log("Zone ID boş olamaz");
            return false;
        }
        
        return $this->apiIstek("zones/{$zone_id}/hold");
    }
    
    /**
     * Zone için hold oluşturur
     * 
     * @param string $zone_id Zone ID
     * @param array $data Hold verileri
     * @return array İşlem sonucu
     */
    public function zoneHoldOlustur($zone_id, $data) {
        if (empty($zone_id)) {
            error_log("Zone ID boş olamaz");
            return false;
        }
        
        return $this->apiIstek("zones/{$zone_id}/hold", 'POST', $data);
    }
    
    /**
     * Zone için hold günceller
     * 
     * @param string $zone_id Zone ID
     * @param array $data Güncellenecek hold verileri
     * @return array İşlem sonucu
     */
    public function zoneHoldGuncelle($zone_id, $data) {
        if (empty($zone_id)) {
            error_log("Zone ID boş olamaz");
            return false;
        }
        
        return $this->apiIstek("zones/{$zone_id}/hold", 'PATCH', $data);
    }
    
    /**
     * Zone için hold siler
     * 
     * @param string $zone_id Zone ID
     * @return array İşlem sonucu
     */
    public function zoneHoldSil($zone_id) {
        if (empty($zone_id)) {
            error_log("Zone ID boş olamaz");
            return false;
        }
        
        return $this->apiIstek("zones/{$zone_id}/hold", 'DELETE');
    }
    
    /**
     * DNS kayıtlarını listeler
     * 
     * @param string $zone_id Zone ID
     * @param array $params Sorgu parametreleri (opsiyonel)
     * @return array|bool DNS kayıtları veya hata durumunda false
     */
    public function dnsKayitlariniListele($zone_id, $params = []) {
        if (empty($zone_id)) {
            error_log("Zone ID boş olamaz");
            return false;
        }
        
        $query = !empty($params) ? '?' . http_build_query($params) : '';
        $endpoint = "zones/{$zone_id}/dns_records{$query}";
        
        $response = $this->apiIstek($endpoint);
        
        // API yanıtını kontrol et ve uygun şekilde işle
        if ($response === false) {
            error_log("DNS kayıtları alınamadı: API isteği başarısız");
            return false;
        }
        
        // Yanıt başarılı değilse veya gerekli alanlar yoksa false döndür
        if (!is_array($response) || !isset($response['success']) || !$response['success']) {
            $hata = is_array($response) && isset($response['errors']) && is_array($response['errors']) && isset($response['errors'][0]['message']) 
                ? $response['errors'][0]['message'] 
                : 'Bilinmeyen API hatası';
            error_log("DNS kayıtları alınamadı: " . $hata);
            return false;
        }
        
        // Sonuç dizisi yoksa boş bir dizi ile başarılı yanıt döndür
        if (!isset($response['result']) || !is_array($response['result'])) {
            error_log("DNS kayıtları alındı fakat sonuç boş");
            $response['result'] = [];
        }
        
        return $response;
    }
    
    /**
     * Yeni DNS kaydı ekler
     * 
     * @param string $zone_id Zone ID
     * @param array $data DNS kaydı verileri
     * @return array|bool İşlem sonucu veya hata durumunda false
     */
    public function dnsKaydiEkle($zone_id, $data) {
        if (empty($zone_id)) {
            error_log("Zone ID boş olamaz");
            return false;
        }
        
        if (empty($data)) {
            error_log("DNS kaydı verileri boş olamaz");
            return false;
        }
        
        return $this->apiIstek("zones/{$zone_id}/dns_records", 'POST', $data);
    }
    
    /**
     * DNS kaydını günceller
     * 
     * @param string $zone_id Zone ID
     * @param string $record_id DNS kaydı ID
     * @param array $data Güncellenecek veriler
     * @return array|bool İşlem sonucu veya hata durumunda false
     */
    public function dnsKaydiGuncelle($zone_id, $record_id, $data) {
        if (empty($zone_id)) {
            error_log("Zone ID boş olamaz");
            return false;
        }
        
        if (empty($record_id)) {
            error_log("DNS kaydı ID boş olamaz");
            return false;
        }
        
        if (empty($data)) {
            error_log("Güncellenecek veriler boş olamaz");
            return false;
        }
        
        return $this->apiIstek("zones/{$zone_id}/dns_records/{$record_id}", 'PUT', $data);
    }
    
    /**
     * DNS kaydını siler
     * 
     * @param string $zone_id Zone ID
     * @param string $record_id DNS kaydı ID
     * @return array|bool İşlem sonucu veya hata durumunda false
     */
    public function dnsKaydiSil($zone_id, $record_id) {
        if (empty($zone_id)) {
            error_log("Zone ID boş olamaz");
            return false;
        }
        
        if (empty($record_id)) {
            error_log("DNS kaydı ID boş olamaz");
            return false;
        }
        
        return $this->apiIstek("zones/{$zone_id}/dns_records/{$record_id}", 'DELETE');
    }
    
    /**
     * Yeni Zone oluşturur
     * 
     * @param string $name Alan adı
     * @param string $account_id Hesap ID
     * @param string $type Zone tipi (full, partial, secondary)
     * @param array $ek_parametreler Diğer parametreler (opsiyonel)
     * @return array İşlem sonucu
     */
    public function zoneOlustur($name, $account_id, $type = 'full', $ek_parametreler = []) {
        if (empty($name)) {
            error_log("Alan adı boş olamaz");
            return false;
        }
        
        if (empty($account_id)) {
            error_log("Hesap ID boş olamaz");
            return false;
        }
        
        $data = [
            'name' => $name,
            'account' => [
                'id' => $account_id
            ],
            'type' => $type
        ];
        
        // Ek parametreleri ekle
        if (!empty($ek_parametreler)) {
            $data = array_merge($data, $ek_parametreler);
        }
        
        return $this->apiIstek("zones", 'POST', $data);
    }
    
    /**
     * Zone günceller
     * 
     * @param string $zone_id Zone ID
     * @param array $data Güncellenecek veriler
     * @return array İşlem sonucu
     */
    public function zoneGuncelle($zone_id, $data) {
        if (empty($zone_id)) {
            error_log("Zone ID boş olamaz");
            return false;
        }
        
        if (empty($data)) {
            error_log("Güncellenecek veriler boş olamaz");
            return false;
        }
        
        return $this->apiIstek("zones/{$zone_id}", 'PATCH', $data);
    }
    
    /**
     * Zone siler
     * 
     * @param string $zone_id Zone ID
     * @return array İşlem sonucu
     */
    public function zoneSil($zone_id) {
        if (empty($zone_id)) {
            error_log("Zone ID boş olamaz");
            return false;
        }
        
        return $this->apiIstek("zones/{$zone_id}", 'DELETE');
    }
    
    /**
     * Zone için aktivasyon kontrolünü yeniden çalıştırır
     * 
     * @param string $zone_id Zone ID
     * @return array İşlem sonucu
     */
    public function zoneAktivasyonKontrolu($zone_id) {
        if (empty($zone_id)) {
            error_log("Zone ID boş olamaz");
            return false;
        }
        
        return $this->apiIstek("zones/{$zone_id}/activation_check", 'PUT');
    }
}
