<?php

namespace App;

class Kullanici {
    private $db;
    private $oturum_suresi;
    
    public function __construct() {
        $this->db = Veritabani::baglan();
        $config = require __DIR__ . '/../config/uygulama.php';
        $this->oturum_suresi = isset($config['oturum_suresi']) ? $config['oturum_suresi'] : 3600;
        
        // Kullanıcılar tablosunu oluştur
        $this->tablolariOlustur();
    }
    
    /**
     * Gerekli tabloları oluşturur
     */
    private function tablolariOlustur() {
        // Kullanıcılar tablosu
        $this->db->tabloOlustur('kullanicilar', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            kullanici_adi VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            sifre VARCHAR(255) NOT NULL,
            ad VARCHAR(50) NOT NULL,
            soyad VARCHAR(50) NOT NULL,
            yetki ENUM('admin', 'kullanici') NOT NULL DEFAULT 'kullanici',
            durum TINYINT(1) NOT NULL DEFAULT 1,
            son_giris DATETIME,
            olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP
        ");
        
        // Cloudflare API Anahtarları tablosu
        $this->db->tabloOlustur('cloudflare_api_anahtarlari', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            kullanici_id INT NOT NULL,
            api_anahtari VARCHAR(255) NOT NULL,
            email VARCHAR(100) NOT NULL,
            aciklama VARCHAR(255),
            durum TINYINT(1) NOT NULL DEFAULT 1,
            olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
        ");
        
        // Oturumlar tablosu
        $this->db->tabloOlustur('oturumlar', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            kullanici_id INT NOT NULL,
            oturum_token VARCHAR(255) NOT NULL UNIQUE,
            ip_adresi VARCHAR(45) NOT NULL,
            tarayici VARCHAR(255),
            son_aktivite DATETIME NOT NULL,
            olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE
        ");
    }
    
    /**
     * Yeni kullanıcı kaydeder
     * 
     * @param array $veri Kullanıcı verileri
     * @return int|bool Eklenen kullanıcı ID'si veya hata durumunda false
     */
    public function kayit($veri) {
        // Şifreyi hashle
        $veri['sifre'] = password_hash($veri['sifre'], PASSWORD_DEFAULT);
        
        try {
            return $this->db->ekle('kullanicilar', $veri);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Kullanıcı girişi yapar
     * 
     * @param string $kullanici_adi Kullanıcı adı veya email
     * @param string $sifre Şifre
     * @return array|bool Kullanıcı bilgileri veya hata durumunda false
     */
    public function giris($kullanici_adi, $sifre) {
        // Email veya kullanıcı adı ile kullanıcıyı bul
        $sql = "SELECT * FROM kullanicilar WHERE (kullanici_adi = :kullanici_adi OR email = :email) AND durum = 1";
        $kullanici = $this->db->getir($sql, [
            'kullanici_adi' => $kullanici_adi,
            'email' => $kullanici_adi
        ]);
        
        if (!$kullanici) {
            return false;
        }
        
        // Şifreyi kontrol et
        if (!password_verify($sifre, $kullanici['sifre'])) {
            return false;
        }
        
        // Son giriş tarihini güncelle
        $this->db->guncelle('kullanicilar', 
            ['son_giris' => date('Y-m-d H:i:s')], 
            'id = :id', 
            ['id' => $kullanici['id']]
        );
        
        // Oturum oluştur
        $this->oturumOlustur($kullanici['id']);
        
        // Şifreyi kaldır
        unset($kullanici['sifre']);
        
        return $kullanici;
    }
    
    /**
     * Oturum oluşturur
     * 
     * @param int $kullanici_id Kullanıcı ID
     * @return string Oturum token
     */
    private function oturumOlustur($kullanici_id) {
        // Eski oturumları temizle
        $this->eskiOturumlariTemizle($kullanici_id);
        
        // Yeni token oluştur
        $token = bin2hex(random_bytes(32));
        
        // Tarayıcı bilgisi
        $tarayici = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        // IP adresi
        $ip_adresi = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // Oturumu kaydet
        $this->db->ekle('oturumlar', [
            'kullanici_id' => $kullanici_id,
            'oturum_token' => $token,
            'ip_adresi' => $ip_adresi,
            'tarayici' => $tarayici,
            'son_aktivite' => date('Y-m-d H:i:s')
        ]);
        
        // Cookie'ye kaydet
        setcookie('oturum_token', $token, time() + $this->oturum_suresi, '/', '', false, true);
        
        return $token;
    }
    
    /**
     * Eski oturumları temizler
     * 
     * @param int $kullanici_id Kullanıcı ID
     */
    private function eskiOturumlariTemizle($kullanici_id) {
        // Süresi dolmuş oturumları temizle
        $this->db->sil('oturumlar', 
            'son_aktivite < :zaman_asimi', 
            ['zaman_asimi' => date('Y-m-d H:i:s', time() - $this->oturum_suresi)]
        );
        
        // Kullanıcının mevcut oturumlarını temizle (opsiyonel)
        // $this->db->sil('oturumlar', 'kullanici_id = :kullanici_id', ['kullanici_id' => $kullanici_id]);
    }
    
    /**
     * Oturumu kontrol eder
     * 
     * @return array|bool Kullanıcı bilgileri veya oturum yoksa false
     */
    public function oturumKontrol() {
        if (!isset($_COOKIE['oturum_token'])) {
            return false;
        }
        
        $token = $_COOKIE['oturum_token'];
        
        // Oturumu bul
        $sql = "SELECT o.*, k.* FROM oturumlar o 
                JOIN kullanicilar k ON o.kullanici_id = k.id 
                WHERE o.oturum_token = :token AND k.durum = 1";
        
        $oturum = $this->db->getir($sql, ['token' => $token]);
        
        if (!$oturum) {
            $this->cikis();
            return false;
        }
        
        // Oturum süresi kontrolü
        $son_aktivite = strtotime($oturum['son_aktivite']);
        if (time() - $son_aktivite > $this->oturum_suresi) {
            $this->cikis();
            return false;
        }
        
        // Son aktivite zamanını güncelle
        $this->db->guncelle('oturumlar', 
            ['son_aktivite' => date('Y-m-d H:i:s')], 
            'oturum_token = :token', 
            ['token' => $token]
        );
        
        // Şifreyi kaldır
        unset($oturum['sifre']);
        
        return $oturum;
    }
    
    /**
     * Oturumu sonlandırır
     */
    public function cikis() {
        if (isset($_COOKIE['oturum_token'])) {
            // Cookie'yi sil
            setcookie('oturum_token', '', time() - 3600, '/', '', false, true);
            
            // Oturumu veritabanından sil
            $this->db->sil('oturumlar', 'oturum_token = :token', ['token' => $_COOKIE['oturum_token']]);
        }
    }
    
    /**
     * Kullanıcı bilgilerini günceller
     * 
     * @param int $kullanici_id Kullanıcı ID
     * @param array $veri Güncellenecek veriler
     * @return bool Güncelleme başarılı mı
     */
    public function guncelle($kullanici_id, $veri) {
        // Şifre güncellenmek isteniyorsa hashle
        if (isset($veri['sifre']) && !empty($veri['sifre'])) {
            $veri['sifre'] = password_hash($veri['sifre'], PASSWORD_DEFAULT);
        } else {
            unset($veri['sifre']);
        }
        
        try {
            $sonuc = $this->db->guncelle('kullanicilar', $veri, 'id = :id', ['id' => $kullanici_id]);
            return $sonuc > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Kullanıcı siler
     * 
     * @param int $kullanici_id Kullanıcı ID
     * @return bool Silme başarılı mı
     */
    public function sil($kullanici_id) {
        try {
            $sonuc = $this->db->sil('kullanicilar', 'id = :id', ['id' => $kullanici_id]);
            return $sonuc > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Tüm kullanıcıları listeler
     * 
     * @return array Kullanıcı listesi
     */
    public function tumKullanicilariListele() {
        $sql = "SELECT id, kullanici_adi, email, ad, soyad, yetki, durum, son_giris, olusturma_tarihi FROM kullanicilar ORDER BY id DESC";
        return $this->db->getirTumu($sql);
    }
    
    /**
     * Kullanıcı bilgilerini getirir
     * 
     * @param int $kullanici_id Kullanıcı ID
     * @return array|bool Kullanıcı bilgileri veya bulunamazsa false
     */
    public function kullaniciyiGetir($kullanici_id) {
        $sql = "SELECT id, kullanici_adi, email, ad, soyad, yetki, durum, son_giris, olusturma_tarihi FROM kullanicilar WHERE id = :id";
        return $this->db->getir($sql, ['id' => $kullanici_id]);
    }
    
    /**
     * Cloudflare API anahtarı ekler
     * 
     * @param array $veri API anahtar verileri
     * @return int|bool Eklenen API anahtar ID'si veya hata durumunda false
     */
    public function apiAnahtariEkle($veri) {
        try {
            return $this->db->ekle('cloudflare_api_anahtarlari', $veri);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Cloudflare API anahtarını günceller
     * 
     * @param int $anahtar_id API anahtar ID
     * @param array $veri Güncellenecek veriler
     * @return bool Güncelleme başarılı mı
     */
    public function apiAnahtariGuncelle($anahtar_id, $veri) {
        try {
            $sonuc = $this->db->guncelle('cloudflare_api_anahtarlari', $veri, 'id = :id', ['id' => $anahtar_id]);
            return $sonuc > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Cloudflare API anahtarını siler
     * 
     * @param int $anahtar_id API anahtar ID
     * @return bool Silme başarılı mı
     */
    public function apiAnahtariSil($anahtar_id) {
        try {
            $sonuc = $this->db->sil('cloudflare_api_anahtarlari', 'id = :id', ['id' => $anahtar_id]);
            return $sonuc > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Kullanıcının API anahtarlarını listeler
     * 
     * @param int $kullanici_id Kullanıcı ID
     * @return array API anahtarları listesi
     */
    public function apiAnahtarlariniListele($kullanici_id) {
        $sql = "SELECT * FROM cloudflare_api_anahtarlari WHERE kullanici_id = :kullanici_id ORDER BY id DESC";
        return $this->db->getirTumu($sql, ['kullanici_id' => $kullanici_id]);
    }
    
    /**
     * API anahtarı bilgilerini getirir
     * 
     * @param int $anahtar_id API anahtar ID
     * @return array|bool API anahtar bilgileri veya bulunamazsa false
     */
    public function apiAnahtariniGetir($anahtar_id) {
        $sql = "SELECT * FROM cloudflare_api_anahtarlari WHERE id = :id";
        return $this->db->getir($sql, ['id' => $anahtar_id]);
    }
    
    /**
     * API anahtarı bilgilerini getirir (alternatif isim)
     * 
     * @param int $anahtar_id API anahtar ID
     * @return array|bool API anahtar bilgileri veya bulunamazsa false
     */
    public function apiAnahtariGetir($anahtar_id) {
        return $this->apiAnahtariniGetir($anahtar_id);
    }
    
    /**
     * Kullanıcıları listeler
     * 
     * @param int $limit Limit
     * @param int $offset Offset
     * @param string $arama Arama kelimesi
     * @return array Kullanıcı listesi
     */
    public function kullanicilariListele($limit = 10, $offset = 0, $arama = '') {
        $params = [];
        
        $sql = "SELECT * FROM kullanicilar";
        
        if (!empty($arama)) {
            $sql .= " WHERE kullanici_adi LIKE :arama OR email LIKE :arama OR ad LIKE :arama OR soyad LIKE :arama";
            $params['arama'] = "%{$arama}%";
        }
        
        $sql .= " ORDER BY id DESC";
        
        if ($limit > 0) {
            $sql .= " LIMIT :offset, :limit";
            $params['offset'] = $offset;
            $params['limit'] = $limit;
        }
        
        return $this->db->getirTumu($sql, $params);
    }
    
    /**
     * Toplam kullanıcı sayısını getirir
     * 
     * @param string $arama Arama kelimesi
     * @return int Toplam kullanıcı sayısı
     */
    public function kullaniciSayisi($arama = '') {
        $params = [];
        
        $sql = "SELECT COUNT(*) as toplam FROM kullanicilar";
        
        if (!empty($arama)) {
            $sql .= " WHERE kullanici_adi LIKE :arama OR email LIKE :arama OR ad LIKE :arama OR soyad LIKE :arama";
            $params['arama'] = "%{$arama}%";
        }
        
        $sonuc = $this->db->getir($sql, $params);
        return $sonuc['toplam'];
    }
    
    /**
     * Kullanıcının oturum kayıtlarını getirir
     * 
     * @param int $kullanici_id Kullanıcı ID
     * @param int $limit Kaç kayıt getirileceği
     * @return array Oturum kayıtları
     */
    public function oturumlariGetir($kullanici_id, $limit = 5) {
        $sql = "SELECT * FROM oturumlar 
                WHERE kullanici_id = :kullanici_id 
                ORDER BY son_aktivite DESC 
                LIMIT :limit";
        
        return $this->db->getirTumu($sql, [
            'kullanici_id' => $kullanici_id,
            'limit' => $limit
        ]);
    }
}
