// DOM yüklendikten sonra çalışacak kodlar
document.addEventListener('DOMContentLoaded', function() {
    // Alert mesajlarını otomatik kapatma
    var alertList = document.querySelectorAll('.alert');
    alertList.forEach(function(alert) {
        setTimeout(function() {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
    
    // DNS kaydı ekleme formunda tip değiştiğinde içerik alanını güncelleme
    var dnsTypeSelect = document.getElementById('dns_tip');
    if (dnsTypeSelect) {
        dnsTypeSelect.addEventListener('change', function() {
            var contentHelp = document.getElementById('content_help');
            var contentInput = document.getElementById('dns_icerik');
            
            switch (this.value) {
                case 'A':
                    contentHelp.textContent = 'IPv4 adresi girin (örn: 192.168.1.1)';
                    contentInput.placeholder = '192.168.1.1';
                    break;
                case 'AAAA':
                    contentHelp.textContent = 'IPv6 adresi girin';
                    contentInput.placeholder = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
                    break;
                case 'CNAME':
                    contentHelp.textContent = 'Hedef domain adı girin';
                    contentInput.placeholder = 'hedef.example.com';
                    break;
                case 'TXT':
                    contentHelp.textContent = 'Metin içeriği girin';
                    contentInput.placeholder = 'v=spf1 include:example.com ~all';
                    break;
                case 'MX':
                    contentHelp.textContent = 'Mail sunucusu girin';
                    contentInput.placeholder = 'mail.example.com';
                    break;
                default:
                    contentHelp.textContent = 'Kayıt içeriğini girin';
                    contentInput.placeholder = '';
            }
        });
    }
    
    // Firewall kuralı ekleme formunda aksiyon değiştiğinde renk güncelleme
    var firewallActionSelect = document.getElementById('firewall_aksiyon');
    if (firewallActionSelect) {
        firewallActionSelect.addEventListener('change', function() {
            var actionPreview = document.getElementById('action_preview');
            
            actionPreview.className = 'firewall-action';
            actionPreview.textContent = this.value.toUpperCase();
            
            switch (this.value) {
                case 'allow':
                    actionPreview.classList.add('action-allow');
                    break;
                case 'block':
                    actionPreview.classList.add('action-block');
                    break;
                case 'challenge':
                    actionPreview.classList.add('action-challenge');
                    break;
                case 'js_challenge':
                    actionPreview.classList.add('action-js_challenge');
                    break;
            }
        });
    }
    
    // SSL modu değiştiğinde önizleme güncelleme
    var sslModeSelect = document.getElementById('ssl_modu');
    if (sslModeSelect) {
        sslModeSelect.addEventListener('change', function() {
            var sslPreview = document.getElementById('ssl_preview');
            
            sslPreview.className = 'ssl-badge';
            sslPreview.textContent = this.value.toUpperCase();
            
            switch (this.value) {
                case 'off':
                    sslPreview.classList.add('ssl-off');
                    break;
                case 'flexible':
                    sslPreview.classList.add('ssl-flexible');
                    break;
                case 'full':
                    sslPreview.classList.add('ssl-full');
                    break;
                case 'strict':
                    sslPreview.classList.add('ssl-strict');
                    break;
            }
        });
    }
    
    // Silme işlemi onayı
    var deleteButtons = document.querySelectorAll('.delete-confirm');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            if (!confirm('Bu öğeyi silmek istediğinizden emin misiniz?')) {
                e.preventDefault();
            }
        });
    });
    
    // API anahtarı göster/gizle
    var apiKeyToggles = document.querySelectorAll('.toggle-api-key');
    apiKeyToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            var apiKeyElement = document.getElementById(this.dataset.target);
            
            if (apiKeyElement.type === 'password') {
                apiKeyElement.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                apiKeyElement.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });
    
    // Firewall filtre JSON doğrulama
    var filterInput = document.getElementById('firewall_filtre');
    if (filterInput) {
        filterInput.addEventListener('blur', function() {
            try {
                JSON.parse(this.value);
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } catch (e) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    }
});
