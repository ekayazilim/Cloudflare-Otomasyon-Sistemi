    </div>
    <!-- /Ana İçerik -->
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Cloudflare Otomasyon</h5>
                    <p>Cloudflare hesaplarınızı ve domainlerinizi kolayca yönetin.</p>
                </div>
                <div class="col-md-3">
                    <h5>Hızlı Erişim</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo $config['url']; ?>/index.php" class="text-white">Ana Sayfa</a></li>
                        <li><a href="<?php echo $config['url']; ?>/domainler.php" class="text-white">Domainler</a></li>
                        <li><a href="<?php echo $config['url']; ?>/api-anahtarlari.php" class="text-white">API Anahtarları</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Yardım</h5>
                    <ul class="list-unstyled">
                        <li><a href="https://developers.cloudflare.com/api" target="_blank" class="text-white">Cloudflare API Dökümanı</a></li>
                        <li><a href="https://www.cloudflare.com/learning/" target="_blank" class="text-white">Cloudflare Eğitim</a></li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-12 text-center">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Cloudflare Otomasyon. Tüm hakları saklıdır.</p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo $config['url']; ?>/assets/js/script.js"></script>
</body>
</html>
