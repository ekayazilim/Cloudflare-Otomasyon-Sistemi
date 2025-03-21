<?php

namespace App;

class Yardimci {
    /**
     * Hata mesajı oluşturur
     * 
     * @param string $mesaj Hata mesajı
     * @return string JSON formatında hata mesajı
     */
    public static function hataMesaji($mesaj) {
        return json_encode([
            'basari' => false,
            'mesaj' => $mesaj
        ]);
    }
    
    /**
     * Başarı mesajı oluşturur
     * 
     * @param string $mesaj Başarı mesajı
     * @param array $veri Ek veri
     * @return string JSON formatında başarı mesajı
     */
    public static function basariMesaji($mesaj, $veri = []) {
        $yanit = [
            'basari' => true,
            'mesaj' => $mesaj
        ];
        
        if (!empty($veri)) {
            $yanit['veri'] = $veri;
        }
        
        return json_encode($yanit);
    }
    
    /**
     * Yönlendirme yapar
     * 
     * @param string $url Yönlendirilecek URL
     */
    public static function yonlendir($url) {
        header("Location: $url");
        exit;
    }
    
    /**
     * Oturum kontrolü yapar
     * 
     * @return bool Oturum açık mı
     */
    public static function oturumKontrol() {
        $kullanici = new Kullanici();
        $oturum = $kullanici->oturumKontrol();
        
        if (!$oturum) {
            self::yonlendir('giris.php');
            return false;
        }
        
        return true;
    }
    
    /**
     * Admin kontrolü yapar
     * 
     * @return bool Kullanıcı admin mi
     */
    public static function adminKontrol() {
        $kullanici = new Kullanici();
        $oturum = $kullanici->oturumKontrol();
        
        if (!$oturum) {
            self::yonlendir('giris.php');
            return false;
        }
        
        if ($oturum['yetki'] !== 'admin') {
            self::yonlendir('index.php');
            return false;
        }
        
        return true;
    }
    
    /**
     * Form verilerini temizler
     * 
     * @param mixed $veri Temizlenecek veri
     * @return mixed Temizlenmiş veri
     */
    public static function temizle($veri) {
        if (is_array($veri)) {
            foreach ($veri as $anahtar => $deger) {
                $veri[$anahtar] = self::temizle($deger);
            }
            return $veri;
        }
        
        // HTML etiketlerini kaldır
        $veri = strip_tags($veri);
        
        // Boşlukları temizle
        $veri = trim($veri);
        
        // Özel karakterleri dönüştür
        $veri = htmlspecialchars($veri, ENT_QUOTES, 'UTF-8');
        
        return $veri;
    }
    
    /**
     * POST verilerini alır ve temizler
     * 
     * @param string $anahtar POST anahtarı
     * @param mixed $varsayilan Varsayılan değer
     * @return mixed Temizlenmiş POST verisi
     */
    public static function post($anahtar, $varsayilan = '') {
        return isset($_POST[$anahtar]) ? self::temizle($_POST[$anahtar]) : $varsayilan;
    }
    
    /**
     * GET verilerini alır ve temizler
     * 
     * @param string $anahtar GET anahtarı
     * @param mixed $varsayilan Varsayılan değer
     * @return mixed Temizlenmiş GET verisi
     */
    public static function get($anahtar, $varsayilan = '') {
        return isset($_GET[$anahtar]) ? self::temizle($_GET[$anahtar]) : $varsayilan;
    }
    
    /**
     * Dosya boyutunu okunabilir formata dönüştürür
     * 
     * @param int $boyut Dosya boyutu (byte)
     * @param int $ondalik Ondalık basamak sayısı
     * @return string Formatlanmış dosya boyutu
     */
    public static function dosyaBoyutuFormatla($boyut, $ondalik = 2) {
        $birimler = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        $i = 0;
        while ($boyut >= 1024 && $i < count($birimler) - 1) {
            $boyut /= 1024;
            $i++;
        }
        
        return round($boyut, $ondalik) . ' ' . $birimler[$i];
    }
    
    /**
     * Tarih formatını düzenler
     * 
     * @param string $tarih Tarih
     * @param string $format Format
     * @return string Formatlanmış tarih
     */
    public static function tarihFormat($tarih, $format = 'd.m.Y H:i') {
        $dt = new \DateTime($tarih);
        return $dt->format($format);
    }
    
    /**
     * Sayfalama oluşturur
     * 
     * @param int $toplam_kayit Toplam kayıt sayısı
     * @param int $sayfa_no Aktif sayfa numarası
     * @param int $kayit_sayisi Sayfa başına kayıt sayısı
     * @param string $url Sayfalama URL'si
     * @return string Sayfalama HTML'i
     */
    public static function sayfalama($toplam_kayit, $sayfa_no, $kayit_sayisi, $url) {
        $toplam_sayfa = ceil($toplam_kayit / $kayit_sayisi);
        
        if ($toplam_sayfa <= 1) {
            return '';
        }
        
        $html = '<ul class="pagination">';
        
        // Önceki sayfa
        if ($sayfa_no > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?sayfa=' . ($sayfa_no - 1) . '">&laquo;</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
        }
        
        // Sayfa numaraları
        $baslangic = max(1, $sayfa_no - 2);
        $bitis = min($toplam_sayfa, $sayfa_no + 2);
        
        if ($baslangic > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?sayfa=1">1</a></li>';
            if ($baslangic > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        for ($i = $baslangic; $i <= $bitis; $i++) {
            if ($i == $sayfa_no) {
                $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?sayfa=' . $i . '">' . $i . '</a></li>';
            }
        }
        
        if ($bitis < $toplam_sayfa) {
            if ($bitis < $toplam_sayfa - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?sayfa=' . $toplam_sayfa . '">' . $toplam_sayfa . '</a></li>';
        }
        
        // Sonraki sayfa
        if ($sayfa_no < $toplam_sayfa) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $url . '?sayfa=' . ($sayfa_no + 1) . '">&raquo;</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
        }
        
        $html .= '</ul>';
        
        return $html;
    }
    
    /**
     * Dosya boyutunu okunabilir formata dönüştürür
     * 
     * @param int $boyut Dosya boyutu (byte)
     * @return string Okunabilir dosya boyutu
     */
    public static function dosyaBoyutu($boyut) {
        $birimler = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $i = 0;
        while ($boyut >= 1024 && $i < count($birimler) - 1) {
            $boyut /= 1024;
            $i++;
        }
        
        return round($boyut, 2) . ' ' . $birimler[$i];
    }
    
    /**
     * Güvenli şekilde dosya adı oluşturur
     * 
     * @param string $dosya_adi Orijinal dosya adı
     * @return string Güvenli dosya adı
     */
    public static function guvenliDosyaAdi($dosya_adi) {
        // Türkçe karakterleri dönüştür
        $tr = ['ç', 'Ç', 'ğ', 'Ğ', 'ı', 'İ', 'ö', 'Ö', 'ş', 'Ş', 'ü', 'Ü'];
        $eng = ['c', 'C', 'g', 'G', 'i', 'I', 'o', 'O', 's', 'S', 'u', 'U'];
        $dosya_adi = str_replace($tr, $eng, $dosya_adi);
        
        // Sadece alfanumerik karakterler, nokta, tire ve alt çizgi kalsın
        $dosya_adi = preg_replace('/[^a-zA-Z0-9\.\-\_]/', '', $dosya_adi);
        
        // Boşlukları tire ile değiştir
        $dosya_adi = str_replace(' ', '-', $dosya_adi);
        
        // Dosya adını küçük harfe çevir
        $dosya_adi = strtolower($dosya_adi);
        
        return $dosya_adi;
    }
    
    /**
     * Rastgele şifre oluşturur
     * 
     * @param int $uzunluk Şifre uzunluğu
     * @return string Rastgele şifre
     */
    public static function rastgeleSifre($uzunluk = 10) {
        $karakterler = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()_+';
        $sifre = '';
        
        for ($i = 0; $i < $uzunluk; $i++) {
            $sifre .= $karakterler[rand(0, strlen($karakterler) - 1)];
        }
        
        return $sifre;
    }
    
    /**
     * Kısaltılmış metin oluşturur
     * 
     * @param string $metin Orijinal metin
     * @param int $uzunluk Maksimum uzunluk
     * @return string Kısaltılmış metin
     */
    public static function kisaltMetin($metin, $uzunluk = 100) {
        if (strlen($metin) <= $uzunluk) {
            return $metin;
        }
        
        return substr($metin, 0, $uzunluk) . '...';
    }
    
    /**
     * SEO dostu URL oluşturur
     * 
     * @param string $metin Orijinal metin
     * @return string SEO dostu URL
     */
    public static function seoUrl($metin) {
        // Türkçe karakterleri dönüştür
        $tr = ['ç', 'Ç', 'ğ', 'Ğ', 'ı', 'İ', 'ö', 'Ö', 'ş', 'Ş', 'ü', 'Ü'];
        $eng = ['c', 'C', 'g', 'G', 'i', 'I', 'o', 'O', 's', 'S', 'u', 'U'];
        $metin = str_replace($tr, $eng, $metin);
        
        // Küçük harfe çevir
        $metin = strtolower($metin);
        
        // Alfanumerik olmayan karakterleri tire ile değiştir
        $metin = preg_replace('/[^a-z0-9]/', '-', $metin);
        
        // Birden fazla tireyi tek tireye dönüştür
        $metin = preg_replace('/-+/', '-', $metin);
        
        // Baştaki ve sondaki tireleri kaldır
        $metin = trim($metin, '-');
        
        return $metin;
    }
}
