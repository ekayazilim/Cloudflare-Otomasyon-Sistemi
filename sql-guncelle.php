<?php
try {
    $db = new PDO("mysql:host=localhost;dbname=cloudflare_otomasyon", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Sütun varlığını kontrol et
    $stmt = $db->query("SHOW COLUMNS FROM dns_kayitlari LIKE 'oncelik'");
    $sutun_var = $stmt->rowCount() > 0;
    
    if (!$sutun_var) {
        // Sütun yoksa ekle
        $sql = "ALTER TABLE dns_kayitlari ADD COLUMN oncelik INT NOT NULL DEFAULT 0 AFTER ttl";
        $db->exec($sql);
        echo "oncelik sütunu eklendi.\n";
    } else {
        echo "oncelik sütunu zaten var.\n";
    }
    
} catch (PDOException $e) {
    echo "Veritabanı hatası: " . $e->getMessage() . "\n";
}
?> 