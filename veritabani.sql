-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 21 Mar 2025, 11:42:06
-- Sunucu sürümü: 10.4.21-MariaDB
-- PHP Sürümü: 7.4.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `cloudflare_otomasyon`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `cloudflare_api_anahtarlari`
--

CREATE TABLE `cloudflare_api_anahtarlari` (
  `id` int(11) NOT NULL,
  `kullanici_id` int(11) NOT NULL,
  `api_anahtari` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `aciklama` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `durum` tinyint(1) NOT NULL DEFAULT 1,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `dns_firewall_kumeleri`
--

CREATE TABLE `dns_firewall_kumeleri` (
  `id` int(11) NOT NULL,
  `account_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `firewall_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isim` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `upstream_ips` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `dns_firewall_ips` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deprecate_any_requests` tinyint(1) DEFAULT 0,
  `origin_direct` tinyint(1) DEFAULT 0,
  `aktif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `dns_firewall_reverse_dns`
--

CREATE TABLE `dns_firewall_reverse_dns` (
  `id` int(11) NOT NULL,
  `firewall_id` int(11) NOT NULL,
  `ptr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `dns_kayitlari`
--

CREATE TABLE `dns_kayitlari` (
  `id` int(11) NOT NULL,
  `domain_id` int(11) NOT NULL,
  `record_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tip` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isim` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icerik` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ttl` int(11) NOT NULL DEFAULT 1,
  `oncelik` int(11) NOT NULL DEFAULT 0,
  `proxied` tinyint(1) NOT NULL DEFAULT 0,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `domainler`
--

CREATE TABLE `domainler` (
  `id` int(11) NOT NULL,
  `kullanici_id` int(11) NOT NULL,
  `api_anahtar_id` int(11) NOT NULL,
  `zone_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `domain` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `durum` tinyint(1) NOT NULL DEFAULT 1,
  `ssl_modu` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'flexible',
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `dns_kayit_sayisi` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `firewall_kurallari`
--

CREATE TABLE `firewall_kurallari` (
  `id` int(11) NOT NULL,
  `domain_id` int(11) NOT NULL,
  `kural_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `aciklama` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `filtre` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `aksiyon` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `oncelik` int(11) NOT NULL DEFAULT 1,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kullanicilar`
--

CREATE TABLE `kullanicilar` (
  `id` int(11) NOT NULL,
  `kullanici_adi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sifre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ad` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `soyad` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `yetki` enum('admin','kullanici') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'kullanici',
  `durum` tinyint(1) NOT NULL DEFAULT 1,
  `son_giris` datetime DEFAULT NULL,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `kullanicilar`
--

INSERT INTO `kullanicilar` (`id`, `kullanici_adi`, `email`, `sifre`, `ad`, `soyad`, `yetki`, `durum`, `son_giris`, `olusturma_tarihi`, `guncelleme_tarihi`) VALUES
(1, 'admin', 'ekasunucu@gmail.com', '$2y$10$RSGOdJPxdCnlFiwPmx/jKOSaDMEvX1mmQLjNLto7/Kby.qM5RhMxW', 'eka', 'sunucu', 'admin', 1, '2025-03-21 11:10:23', '2025-03-21 02:32:03', '2025-03-21 13:10:23');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `oturumlar`
--

CREATE TABLE `oturumlar` (
  `id` int(11) NOT NULL,
  `kullanici_id` int(11) NOT NULL,
  `oturum_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_adresi` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tarayici` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `son_aktivite` datetime NOT NULL,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `ozel_nameserverlar`
--

CREATE TABLE `ozel_nameserverlar` (
  `id` int(11) NOT NULL,
  `account_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ns_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ns_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone_tag` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ns_set` int(11) DEFAULT NULL,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `ozel_nameserver_dns_kayitlari`
--

CREATE TABLE `ozel_nameserver_dns_kayitlari` (
  `id` int(11) NOT NULL,
  `ns_id` int(11) NOT NULL,
  `tip` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deger` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `cloudflare_api_anahtarlari`
--
ALTER TABLE `cloudflare_api_anahtarlari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kullanici_id` (`kullanici_id`);

--
-- Tablo için indeksler `dns_firewall_kumeleri`
--
ALTER TABLE `dns_firewall_kumeleri`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `dns_firewall_reverse_dns`
--
ALTER TABLE `dns_firewall_reverse_dns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `firewall_id` (`firewall_id`);

--
-- Tablo için indeksler `dns_kayitlari`
--
ALTER TABLE `dns_kayitlari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `domain_id` (`domain_id`);

--
-- Tablo için indeksler `domainler`
--
ALTER TABLE `domainler`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kullanici_id` (`kullanici_id`),
  ADD KEY `api_anahtar_id` (`api_anahtar_id`);

--
-- Tablo için indeksler `firewall_kurallari`
--
ALTER TABLE `firewall_kurallari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `domain_id` (`domain_id`);

--
-- Tablo için indeksler `kullanicilar`
--
ALTER TABLE `kullanicilar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kullanici_adi` (`kullanici_adi`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Tablo için indeksler `oturumlar`
--
ALTER TABLE `oturumlar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `oturum_token` (`oturum_token`),
  ADD KEY `kullanici_id` (`kullanici_id`);

--
-- Tablo için indeksler `ozel_nameserverlar`
--
ALTER TABLE `ozel_nameserverlar`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `ozel_nameserver_dns_kayitlari`
--
ALTER TABLE `ozel_nameserver_dns_kayitlari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ns_id` (`ns_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `cloudflare_api_anahtarlari`
--
ALTER TABLE `cloudflare_api_anahtarlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `dns_firewall_kumeleri`
--
ALTER TABLE `dns_firewall_kumeleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `dns_firewall_reverse_dns`
--
ALTER TABLE `dns_firewall_reverse_dns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `dns_kayitlari`
--
ALTER TABLE `dns_kayitlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `domainler`
--
ALTER TABLE `domainler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `firewall_kurallari`
--
ALTER TABLE `firewall_kurallari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `kullanicilar`
--
ALTER TABLE `kullanicilar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `oturumlar`
--
ALTER TABLE `oturumlar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `ozel_nameserverlar`
--
ALTER TABLE `ozel_nameserverlar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `ozel_nameserver_dns_kayitlari`
--
ALTER TABLE `ozel_nameserver_dns_kayitlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `cloudflare_api_anahtarlari`
--
ALTER TABLE `cloudflare_api_anahtarlari`
  ADD CONSTRAINT `cloudflare_api_anahtarlari_ibfk_1` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `dns_firewall_reverse_dns`
--
ALTER TABLE `dns_firewall_reverse_dns`
  ADD CONSTRAINT `dns_firewall_reverse_dns_ibfk_1` FOREIGN KEY (`firewall_id`) REFERENCES `dns_firewall_kumeleri` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `dns_kayitlari`
--
ALTER TABLE `dns_kayitlari`
  ADD CONSTRAINT `dns_kayitlari_ibfk_1` FOREIGN KEY (`domain_id`) REFERENCES `domainler` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `domainler`
--
ALTER TABLE `domainler`
  ADD CONSTRAINT `domainler_ibfk_1` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `domainler_ibfk_2` FOREIGN KEY (`api_anahtar_id`) REFERENCES `cloudflare_api_anahtarlari` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `firewall_kurallari`
--
ALTER TABLE `firewall_kurallari`
  ADD CONSTRAINT `firewall_kurallari_ibfk_1` FOREIGN KEY (`domain_id`) REFERENCES `domainler` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `oturumlar`
--
ALTER TABLE `oturumlar`
  ADD CONSTRAINT `oturumlar_ibfk_1` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `ozel_nameserver_dns_kayitlari`
--
ALTER TABLE `ozel_nameserver_dns_kayitlari`
  ADD CONSTRAINT `ozel_nameserver_dns_kayitlari_ibfk_1` FOREIGN KEY (`ns_id`) REFERENCES `ozel_nameserverlar` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
