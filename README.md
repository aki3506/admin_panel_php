# NK Teknoloji — Yönetim Paneli (Admin Panel)

[NK Teknoloji](https://www.nkteknoloji.com.tr) e-ticaret sitesi için geliştirilmiş, PHP + MySQL tabanlı basit ve bağımlılıksız (framework'süz) bir yönetim panelidir. Ürün, kategori, stok ve döviz/KDV bazlı fiyatlandırma yönetimini tek bir panelden yapmayı sağlar.

Bu repo, kendi küçük ölçekli e-ticaret sitesi için hafif bir admin panel örneği arayan geliştiriciler için referans/başlangıç noktası olarak paylaşılmıştır.

## Özellikler

- **Giriş sistemi:** `password_hash()` / `password_verify()` ile güvenli, bcrypt tabanlı admin girişi.
- **Ürün yönetimi:** Ürün ekleme, düzenleme, ana ve ek görsel yükleme, barkod ve kategori ilişkilendirme.
- **Kategori yönetimi:** Kategori oluşturma, düzenleme ve aktif/pasif yönetimi.
- **Stok yönetimi:** Stok hareketleri (giriş/çıkış), kritik stok seviyesi takibi.
- **Döviz & KDV motoru:** TCMB günlük kur servisinden otomatik USD kuru çekme (6 saatte bir), KDV dahil/hariç fiyat hesaplama ve gösterimi.
- **Ortam değişkeni (.env) tabanlı yapılandırma:** Veritabanı bilgileri koda gömülü değildir.

## Klasör Yapısı

```
nk-teknoloji-admin-panel/
├── admin/
│   ├── login.php         # Admin girişi
│   ├── dashboard.php     # Özet panel (ürün/kategori/stok istatistikleri)
│   ├── urunler.php       # Ürün yönetimi
│   ├── kategoriler.php   # Kategori yönetimi
│   ├── stok.php          # Stok hareketleri
│   └── logout.php        # Çıkış
├── config.php            # Ortak yapılandırma, DB sınıfı, yardımcı fonksiyonlar
├── .env.example           # Örnek ortam değişkenleri dosyası
└── .gitignore
```

## Gereksinimler

- PHP 7.4+ (PDO, cURL, SimpleXML, fileinfo eklentileri aktif olmalı)
- MySQL / MariaDB

## Kurulum

1. Depoyu klonlayın ve `.env.example` dosyasını `.env` olarak kopyalayın:

   ```bash
   cp .env.example .env
   ```

2. `.env` dosyasını kendi veritabanı bilgilerinizle doldurun.

3. Aşağıdaki tabloları veritabanınızda oluşturun (kod tabanından çıkarılmış, asgari şema — kendi ihtiyacınıza göre genişletebilirsiniz):

   ```sql
   CREATE TABLE admin_kullanicilar (
       admin_id INT AUTO_INCREMENT PRIMARY KEY,
       kullanici_adi VARCHAR(50) NOT NULL UNIQUE,
       sifre_hash VARCHAR(255) NOT NULL,
       ad_soyad VARCHAR(100),
       yetki_seviyesi VARCHAR(20) DEFAULT 'Admin',
       aktif TINYINT(1) DEFAULT 1,
       son_giris DATETIME NULL
   );

   CREATE TABLE kategoriler (
       kategori_id INT AUTO_INCREMENT PRIMARY KEY,
       kategori_adi VARCHAR(100) NOT NULL,
       aktif TINYINT(1) DEFAULT 1
   );

   CREATE TABLE urunler (
       urun_id INT AUTO_INCREMENT PRIMARY KEY,
       urun_adi VARCHAR(200) NOT NULL,
       barkod_no VARCHAR(50),
       kategori_id INT,
       satis_fiyati DECIMAL(10,2) DEFAULT 0,
       doviz_satis_fiyati DECIMAL(10,2) DEFAULT 0,
       fiyat_doviz VARCHAR(5) DEFAULT 'USD',
       stok_miktari INT DEFAULT 0,
       kritik_stok_seviyesi INT DEFAULT 5,
       ana_resim VARCHAR(255),
       aktif TINYINT(1) DEFAULT 1,
       olusturma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
       FOREIGN KEY (kategori_id) REFERENCES kategoriler(kategori_id)
   );

   CREATE TABLE stok_hareketleri (
       hareket_id INT AUTO_INCREMENT PRIMARY KEY,
       urun_id INT NOT NULL,
       hareket_tipi VARCHAR(10) NOT NULL, -- 'giris' / 'cikis'
       miktar INT NOT NULL,
       aciklama VARCHAR(255),
       olusturma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
       FOREIGN KEY (urun_id) REFERENCES urunler(urun_id)
   );

   CREATE TABLE doviz_kurlari (
       doviz VARCHAR(10) NOT NULL PRIMARY KEY,
       alis DECIMAL(10,4) NOT NULL,
       satis DECIMAL(10,4) NOT NULL,
       guncelleme_tarihi DATETIME NOT NULL
   );
   ```

4. İlk admin kullanıcınızı oluşturun. `sifre_hash` alanına düz metin şifre değil, `password_hash()` çıktısı yazılmalıdır:

   ```php
   <?php
   echo password_hash('sizin-guclu-sifreniz', PASSWORD_DEFAULT);
   ```

   Bu komutun çıktısını `admin_kullanicilar` tablosuna elle (phpMyAdmin vb. ile) ekleyin.

5. `uploads/products/` klasörünü oluşturun ve web sunucusunun yazma iznine sahip olduğundan emin olun.

6. `admin/login.php` üzerinden panele giriş yapın.

## Güvenlik Notları

- Bu panelin orijinal sürümünde, geliştirme kolaylığı için eklenmiş **"admin123" adında sabit bir yedek şifre** bulunuyordu; bu depo bu güvenlik açığı **giderilmiş** haliyle paylaşılmaktadır. Sadece `password_verify()` ile doğrulanan gerçek şifre hash'i kabul edilir.
- Veritabanı bilgileri `.env` dosyasından okunur; `.env` dosyanızı **kesinlikle** versiyon kontrolüne eklemeyin (`.gitignore` içinde zaten hariç tutulmuştur).
- Üretim ortamında `APP_DEBUG=false` olarak bırakın; aksi halde hata mesajları (ve olası dosya yolları) son kullanıcıya görünebilir.
- Bu panelde CSRF token kullanılmamaktadır; genel kullanıma açık bir sunucuya taşırken formlarınıza CSRF koruması eklemeniz önerilir.

## Lisans

Bu proje [MIT Lisansı](LICENSE) ile paylaşılmıştır. Kendi projeleriniz için özgürce kullanabilir ve uyarlayabilirsiniz.

## Katkı

Bu depo NK Teknoloji'nin kendi sitesinde kullandığı panelin sadeleştirilmiş bir örneğidir; issue ve pull request'lere açıktır.
