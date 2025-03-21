<?php

namespace App;

class Veritabani {
    private static $instance = null;
    private $conn;
    private $config;
    
    private function __construct() {
        $this->config = require_once __DIR__ . '/../config/veritabani.php';
        
        try {
            $dsn = "mysql:host={$this->config['host']};dbname={$this->config['veritabani']};charset={$this->config['charset']};port={$this->config['port']}";
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new \PDO($dsn, $this->config['kullanici_adi'], $this->config['sifre'], $options);
        } catch (\PDOException $e) {
            throw new \Exception("Veritabanı bağlantı hatası: " . $e->getMessage());
        }
    }
    
    public static function baglan() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConn() {
        return $this->conn;
    }
    
    public function sorgu($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function getir($sql, $params = []) {
        $stmt = $this->sorgu($sql, $params);
        return $stmt->fetch();
    }
    
    public function getirTumu($sql, $params = []) {
        $stmt = $this->sorgu($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function ekle($tablo, $veri) {
        $alanlar = implode(', ', array_keys($veri));
        $yer_tutucular = ':' . implode(', :', array_keys($veri));
        
        $sql = "INSERT INTO {$tablo} ({$alanlar}) VALUES ({$yer_tutucular})";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($veri);
        
        return $this->conn->lastInsertId();
    }
    
    public function guncelle($tablo, $veri, $kosul, $kosul_degerleri = []) {
        $guncellemeler = [];
        
        foreach (array_keys($veri) as $alan) {
            $guncellemeler[] = "{$alan} = :{$alan}";
        }
        
        $guncellemeler_str = implode(', ', $guncellemeler);
        
        $sql = "UPDATE {$tablo} SET {$guncellemeler_str} WHERE {$kosul}";
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($veri as $alan => $deger) {
            $stmt->bindValue(":{$alan}", $deger);
        }
        
        if (strpos($kosul, '?') !== false) {
            foreach ($kosul_degerleri as $index => $deger) {
                $param_index = $index + 1;
                $stmt->bindValue($param_index, $deger);
            }
        } else {
            foreach ($kosul_degerleri as $alan => $deger) {
                $stmt->bindValue(":{$alan}", $deger);
            }
        }
        
        $stmt->execute();
        
        return $stmt->rowCount();
    }
    
    public function sil($tablo, $kosul, $kosul_degerleri = []) {
        $sql = "DELETE FROM {$tablo} WHERE {$kosul}";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($kosul_degerleri);
        
        return $stmt->rowCount();
    }
    
    public function tabloOlustur($tablo, $alanlar) {
        $sql = "CREATE TABLE IF NOT EXISTS {$tablo} ({$alanlar})";
        $this->conn->exec($sql);
    }
}
