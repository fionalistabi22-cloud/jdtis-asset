# Sistem Pengurusan Aset JTDIS

Aplikasi web untuk pengurusan aset berbasis PHP dan MySQL.

## 📋 Persyaratan Sistem

- PHP 7.0 atau lebih tinggi
- MySQL 5.7 atau MariaDB 10.3 atau lebih tinggi
- Web Server (Apache, Nginx)
- XAMPP atau Stack Lokal Setara

## 🚀 Panduan Pemasangan Cepat

### Langkah 1: Persediaan Database

1. Buka **phpMyAdmin** - http://localhost/phpmyadmin
2. Cipta database baru bernama `jtdis_asset`
3. Pilih **Import** dan pilih file `jtdis_asset.sql`
4. Klik **Go** untuk import schema
5. Ulangi dengan file `setup_data.sql` untuk menambah data sampel

### Langkah 2: Akses Aplikasi

1. Buka browser dan akses: **http://localhost/jdtis_asset**
2. Anda akan dialihkan ke halaman login

### Langkah 3: Log Masuk

**Akaun Default:**
- **Emel:** admin@jtdis.gov.my
- **Kata Laluan:** 12345678

⚠️ **PENTING:** Tukar kata laluan admin selepas log masuk pertama untuk keselamatan.

## 🔍 Pemeriksaan Status Sistem

Buka halaman pemeriksaan untuk mengesahkan semua komponenkah berjaya:
- http://localhost/jdtis_asset/check.php

## 📁 Struktur Folder

```
jdtis_asset/
├── assets/                  # CSS, JS, Images
│   ├── css/
│   ├── js/
│   └── images/
├── includes/               # File Include/Utility
│   ├── config.php         # Konfigurasi Database
│   ├── auth.php           # Fungsi Autentikasi (akan diubah)
│   └── functions.php      # Fungsi Umum
├── pages/                 # Halaman Utama
│   ├── login.php         # Halaman Log Masuk
│   ├── dashboard.php     # Dashboard
│   ├── logout.php        # Log Keluar
│   ├── aset/             # Modul Aset
│   ├── pengguna/         # Modul Pengguna
│   └── laporan/          # Modul Laporan
├── index.php             # Halaman Utama/Redirect
├── setup.html            # Panduan Persediaan
├── check.php             # Pemeriksaan Sistem
├── jtdis_asset.sql      # Schema Database
└── setup_data.sql       # Data Sampel
```

## 🔐 Keamanan

- Semua kata laluan disimpan menggunakan hashing bcrypt
- Gunakan prepared statements untuk melindungi dari SQL injection
- Validasi input di sisi server dan klien
- Gunakan HTTPS dalam produksi
- Perbarui kata laluan secara berkala

## 📝 Catatan Penting

1. **Database Connection:**
   - Default menggunakan root tanpa kata laluan
   - Ubah dalam `includes/config.php` jika diperlukan

2. **Session Management:**
   - Session menyimpan data pengguna dan peranan
   - Sesi akan dihapuskan apabila log keluar

3. **Pengembangan Modul:**
   - Modul pengguna dan laporan masih dalam pembangunan
   - Gunakan struktur yang sama seperti modul aset

## 🔧 Pemecahan Masalah

### Database Connection Failed
- Pastikan MySQL/MariaDB sedang berjalan
- Periksa nama database dan kredensial di `includes/config.php`
- Buka http://localhost/jdtis_asset/check.php

### Blank Page / White Screen
- Periksa error log PHP
- Pastikan semua file PHP tersimpan dengan encoding UTF-8
- Buka http://localhost/jdtis_asset/check.php

### Session Not Working
- Periksa folder session writable
- Pastikan session.save_path dikonfigurasi dengan betul
- Buka http://localhost/jdtis_asset/check.php

## 📞 Hubungan

Untuk soalan atau laporan bug, sila hubungi:
- Email: support@jtdis.gov.my
- Dokumentasi: Lihat file setup.html

## 📄 Lesen

Proprietary - JTDIS 2026

---

**Versi:** 1.0  
**Tarikh:** April 2026  
**Status:** Development
