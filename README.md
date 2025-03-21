# Cloudflare Otomasyon Sistemi

Bu proje, Cloudflare hesaplarınızı ve domainlerinizi yönetmek için geliştirilmiş kapsamlı bir otomasyon sistemidir. Birden fazla hesap ve domainle çalışan kullanıcılar için toplu işlemleri kolaylaştıran, zaman tasarrufu sağlayan bir araçtır.

## Özellikler

### Hesap Yönetimi
- Birden fazla Cloudflare API anahtarı ekleme ve yönetme
- Kullanıcı bazlı yetkilendirme ve oturum yönetimi
- Yönetici ve normal kullanıcı ayrımı

### Domain Yönetimi
- Birden fazla domaini tek bir panelden yönetme
- Cloudflare ile otomatik senkronizasyon
- Domain ekle, sil, düzenle işlemleri
- SSL modu (Flexible, Full, Full Strict) ayarlama
- Plan bilgisi görüntüleme

### DNS Yönetimi
- Tüm DNS kayıtlarını görüntüleme ve düzenleme (A, AAAA, CNAME, MX, TXT, SRV)
- DNS kayıtlarını toplu olarak güncelleme
  - Belirli bir IP'yi içeren tüm kayıtları toplu değiştirme
  - Proxy durumunu toplu güncelleme (aktif/pasif)
- IP adresine göre domain sorgulama
- DNS önbelleğini temizleme

### Firewall Yönetimi
- Firewall kuralları oluşturma ve yönetme
- IP engelleme, aksiyon belirleme
- Firewall kurallarını düzenleme

### Öne Çıkan Özellikler
- **Toplu DNS Güncelleme**: Tüm domainlerdeki belirli bir IP adresini başka bir IP adresi ile değiştirme
- **IP Tabanlı Domain Sorgulama**: Belirli bir IP'yi kullanan tüm domainleri listeleme
- **Proxy Yönetimi**: DNS kayıtları için proxy durumunu (turuncu bulut) toplu olarak yönetme
- **Gerçek Zamanlı Loglama**: Tüm işlemlerin detaylı log kayıtları

### Kullanıcı Arayüzü
- Responsive modern tasarım
- Bootstrap tabanlı arayüz
- Toplu işlemler için bildirim sistemi
- AJAX tabanlı gerçek zamanlı güncelleme

### Teknik Özellikler
- PHP 7+ tabanlı nesne yönelimli mimari
- PDO ile veritabanı işlemleri
- Cloudflare API v4 entegrasyonu
- Oturum yönetimi ve güvenlik kontrolleri
- Temiz kod mimarisi

## Kurulum

1. Dosyaları sunucunuza yükleyin
2. `config/uygulama.php` dosyasındaki veritabanı bilgilerini kendi sunucunuza göre düzenleyin
3. Web tarayıcınızda `public/kurulum.php` adresini açarak kurulumu tamamlayın
4. Admin hesabınızla giriş yapın
5. Cloudflare API anahtarlarınızı ekleyin ve domainlerinizi senkronize edin

## Gereksinimler

- PHP 7.4 veya daha yeni
- MySQL 5.7 veya daha yeni
- PDO PHP eklentisi
- cURL PHP eklentisi
- mod_rewrite etkin (Apache için)

## Ekran Görüntüleri

![Dashboard](docs/img/dashboard.png)
*Dashboard Ekranı*

![Domainler](docs/img/domainler.png)
*Domain Yönetimi*

![DNS Yönetimi](docs/img/dns-kayitlari.png)
*DNS Kayıtları Yönetimi*

![Toplu DNS Güncelleme](docs/img/toplu-dns-guncelleme.png)
*Toplu DNS Kayıtları Güncelleme*

## Lisans

Bu proje MIT lisansı altında lisanslanmıştır. Daha fazla bilgi için [LICENSE](LICENSE) dosyasına bakınız.

## İletişim

Herhangi bir soru, öneri veya geri bildiriminiz varsa lütfen issue açarak veya aşağıdaki iletişim bilgileri üzerinden bize ulaşın:

- E-posta: info@ekayazilim.com.tr
- Web: [www.ekayazilim.com.tr](https://www.ekayazilim.com.tr)
- 🌐 [ekasunucu.com](https://www.ekasunucu.com)
## Katkıda Bulunma

Projeye katkıda bulunmak için lütfen fork edin ve pull request gönderin. Önerilerinizi ve geri bildirimlerinizi her zaman bekliyoruz. 
