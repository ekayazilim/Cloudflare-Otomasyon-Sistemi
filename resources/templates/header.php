<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloudflare Otomasyon</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $config['url']; ?>/assets/css/style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $config['url']; ?>/index.php">
                <i class="fas fa-cloud"></i> Cloudflare Otomasyon
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if (isset($kullanici_bilgileri)): ?>
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $config['url']; ?>/index.php">
                            <i class="fas fa-home"></i> Ana Sayfa
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $config['url']; ?>/domainler.php">
                            <i class="fas fa-globe"></i> Domainler
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $config['url']; ?>/api-anahtarlari.php">
                            <i class="fas fa-key"></i> API Anahtarları
                        </a>
                    </li>
                    <?php if ($kullanici_bilgileri['yetki'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $config['url']; ?>/kullanicilar.php">
                            <i class="fas fa-users"></i> Kullanıcılar
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="sistemDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cogs"></i> Sistem Yönetimi
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="sistemDropdown">
                            <li>
                                <a class="dropdown-item" href="<?php echo $config['url']; ?>/cache-yonetimi.php">
                                    <i class="fas fa-broom"></i> Önbellek Yönetimi
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $config['url']; ?>/sistem-bilgileri.php">
                                    <i class="fas fa-info-circle"></i> Sistem Bilgileri
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $config['url']; ?>/bakim-modu.php">
                                    <i class="fas fa-tools"></i> Bakım Modu
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $config['url']; ?>/gunluk-dosyalari.php">
                                    <i class="fas fa-file-alt"></i> Günlük Dosyaları
                                </a>
                            </li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user"></i> <?php echo $kullanici_bilgileri['ad'] . ' ' . $kullanici_bilgileri['soyad']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li>
                                <a class="dropdown-item" href="<?php echo $config['url']; ?>/profil.php">
                                    <i class="fas fa-user-cog"></i> Profil
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo $config['url']; ?>/cikis.php">
                                    <i class="fas fa-sign-out-alt"></i> Çıkış Yap
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Ana İçerik -->
    <div class="container mt-4">
        <?php if (isset($_SESSION['mesaj'])): ?>
            <div class="alert alert-<?php echo $_SESSION['mesaj_tur']; ?> alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['mesaj']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php 
                unset($_SESSION['mesaj']);
                unset($_SESSION['mesaj_tur']);
            ?>
        <?php endif; ?>
