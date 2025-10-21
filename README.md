# 📊 **DELTA (Data Evaluasi Laporan Terpadu Analisis)**

**DELTA** adalah sistem informasi berbasis web yang dirancang untuk mempermudah proses **input**, **manajemen**, **visualisasi**, dan **evaluasi data laporan bulanan** di **Puskesmas Kuta Makmur**.  
Sistem ini memastikan data terekam secara **terpadu** dan disajikan secara **visual** untuk mendukung proses **analisis** dan **pengambilan keputusan**. ✨

---

## 🚀 **Fitur Utama**

| **Fitur** | **Deskripsi** |
|------------|---------------|
| **Dashboard** | Menyajikan riwayat input data terakhir dengan filter cepat *“Semua Bulan”* dan *“Semua Tahun”* (default). Dilengkapi tombol **Edit Data** dan **Lihat Visualisasi** langsung dari riwayat. |
| **Input Laporan** | Form input data yang **dinamis**. Mampu membaca parameter dari URL untuk otomatis mengisi filter dan beralih ke mode **Tambah** atau **Ubah/Edit**. |
| **Visualisasi Data** | Menampilkan dua grafik utama (**Per Desa** dan **Per Pemeriksaan**) yang dapat diekspor ke format **JPG** (resolusi tinggi) dan **Excel (XLSX)**. |
| **Manajemen Master** | Halaman login admin terpisah dengan background **buram**. Memungkinkan pengelolaan **Master Data** (Desa dan Jenis Pemeriksaan) yang digunakan di seluruh aplikasi. |
| **Sidebar Konsisten** | Navigasi sidebar yang konsisten di semua halaman, dengan menu **Master Data (Admin)** ditempatkan di bagian paling bawah. |

---

## 🛠️ **Struktur Aplikasi & Teknologi**

Proyek ini menggunakan arsitektur **client-server tradisional** yang sederhana.

### 💻 **Teknologi yang Digunakan**
- **Backend:** PHP (Native API Endpoint)  
- **Database:** MySQL / MariaDB  
- **Frontend:** HTML5, CSS (Tailwind CSS via CDN), JavaScript (Native)  
- **Visualisasi:** Chart.js, Chartjs-Plugin-Datalabels  

---

## 📁 **Struktur File Penting**

| **File / Folder** | **Fungsi** |
|--------------------|-------------|
| `index.html` | Dashboard utama |
| `input_data.html` | Halaman Input / Edit Laporan Bulanan |
| `visualisasi_data.html` | Halaman Grafik dan Visualisasi Data |
| `admin_master.html` | Halaman Login dan Pengelolaan Master Data |
| `api.php` | Endpoint API utama untuk semua interaksi data (CRUD) |
| `assets/` | Berisi aset seperti `bg-login-admin.jpg` |

---

## ⚙️ **Panduan Instalasi & Penggunaan**

### 1️⃣ Persiapan Lingkungan
1. Instal **XAMPP** atau **Laragon** untuk menyediakan lingkungan PHP dan MySQL.  
2. **Clone** repositori ini ke folder root server lokal Anda (misalnya: `htdocs/delta` di XAMPP).

---

### 2️⃣ Konfigurasi Database
1. Buat database baru dengan nama: **`delta_db`**  
2. Import skema database (pastikan sudah ada tabel `master_desa`, `master_pemeriksaan`, dan `laporan_bulanan`).  
3. Pastikan detail koneksi database di file `api.php` sesuai dengan konfigurasi server lokal Anda:

```php
// --- PENGATURAN KONEKSI DATABASE ---
$host = 'localhost'; 
$db   = 'delta_db';
$user = 'root'; // Sesuaikan
$pass = '';     // Sesuaikan
// ...
