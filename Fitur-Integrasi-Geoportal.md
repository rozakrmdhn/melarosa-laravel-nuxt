### **Judul Fitur: Integrasi Katalog Peta Geoportal Bojonegoro ke dalam Daftar Layer WebGIS Melarosa**

**1. Latar Belakang dan Tujuan**
Aplikasi WebGIS Melarosa perlu terhubung dengan Simpul Jaringan Geoportal Kabupaten Bojonegoro (`geoportal.bojonegorokab.go.id`). Dalam integrasi ini, Melarosa berperan sebagai portal pengguna ( *client* ) yang secara otomatis mengambil dan menampilkan daftar peta (layer) resmi yang telah dipublikasikan di Geoportal Bojonegoro, sehingga pengguna Melarosa dapat melihat dan menumpangsusunkan ( *overlay* ) peta-peta dari Geoportal langsung di dalam halaman peta Melarosa.

**2. Alur Kerja dan Kebutuhan Fungsional**

* **Penarikan Katalog Otomatis (Sinkronisasi Data):**
  * Sistem Melarosa harus dapat mengambil daftar seluruh katalog peta beserta informasi metadatanya (seperti Judul Peta, Kategori/Tema, Deskripsi Singkat, dan Wali Data/OPD pemilik) dari layanan katalog Geoportal Bojonegoro (`geoportal.bojonegorokab.go.id/csw/`).
  * Proses pengambilan katalog ini dapat berjalan secara terjadwal otomatis (misalnya satu kali sehari) dan/atau dilengkapi dengan tombol **"Sinkronisasi Katalog Geoportal"** pada halaman Admin Melarosa agar daftar peta selalu mutakhir mengikuti pembaruan di Geoportal tanpa perlu input manual satu per satu.
* **Tampilan Daftar Layer di Halaman Peta Melarosa:**
  * Pada panel menu *Layer* di aplikasi Melarosa, sediakan bagian atau tab khusus bernama **"Layer Geoportal Bojonegoro"** (terpisah atau berdampingan dengan layer internal Melarosa).
  * Daftar layer yang berhasil ditarik dari Geoportal dikelompokkan secara rapi berdasarkan kategori atau tema/urusan agar mudah dinavigasi oleh pengguna.
  * Sediakan kolom **Pencarian Cepat (Search)** di bagian atas daftar agar pengguna bisa menyaring nama layer Geoportal berdasarkan kata kunci.
* **Visualisasi Peta dan Legenda:**
  * Setiap nama layer pada daftar dilengkapi dengan kotak centang ( *checkbox* ) atau tombol aktif/nonaktif ( *toggle* ).
  * Ketika pengguna mencentang suatu layer, sistem langsung menampilkan visualisasi peta tersebut di atas kanvas peta Melarosa beserta simbol/gambar **Legenda** resminya di bawah nama layer yang sedang aktif.
  * Pengguna dapat mengatur tingkat transparansi ( *opacity* ) dari layer Geoportal yang sedang ditampilkan agar mudah dibandingkan dengan layer milik Melarosa.
* **Informasi Detail Objek Peta (Klik Peta) & Metadata:**
  * Ketika suatu layer dari Geoportal sedang aktif dan pengguna mengklik objek atau area pada peta tersebut, muncul jendela informasi ( *popup* ) yang menampilkan rincian data atribut dari objek yang diklik (misalnya nama ruas, nama desa, luas, atau keterangan atribut lainnya yang berasal dari Geoportal).
  * Sediakan ikon **"Info Metadata"** di samping nama layer pada daftar menu, yang jika diklik akan menampilkan ringkasan penjelasan peta tersebut (judul lengkap, deskripsi/abstrak, dan sumber data).

**3. Hasil Akhir yang Diharapkan (Output)**

1. Admin Melarosa tidak perlu mengunggah ulang file peta yang sudah ada di Geoportal Bojonegoro; cukup melalui fitur sinkronisasi otomatis.
2. Pengguna WebGIS Melarosa dapat mencari, mengaktifkan, melihat legenda, serta mengecek informasi atribut dari seluruh peta publik milik Geoportal Bojonegoro secara langsung dan lancar di dalam satu layar peta Melarosa.
