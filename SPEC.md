# Layarku: Spesifikasi Produk

Aplikasi pemesanan tiket bioskop. Pelanggan melihat film yang sedang tayang, memilih jadwal, memilih kursi di peta kursi, membayar, lalu menerima tiket dengan kode QR. Petugas bioskop memindai QR itu di pintu studio.

Ini proyek portofolio. Tujuannya menunjukkan hal yang belum terlihat di Tokoku, Ambulansku, dan MySavings: **backend REST API sendiri (Laravel)**, **pembayaran**, **push notification dari server**, dan **pemesanan kursi yang tidak boleh bentrok**. Peta kursi juga membuatnya mudah didemokan dalam video.

> Dokumen ini menggantikan spesifikasi Janjiku (booking barbershop). Arsitektur dan fasenya sengaja dipertahankan, hanya domainnya yang berubah.

---

## 1. Keputusan awal (bisa diubah sebelum fase 1)

| Keputusan | Pilihan awal | Alasan |
|---|---|---|
| Domain | Jaringan bioskop **fiktif** bernama "Layar Kota Cinema" | Jangan memakai nama atau logo bioskop sungguhan. Aplikasi yang meniru merek nyata bisa dianggap menyesatkan. |
| Struktur repo | Satu repo (monorepo): `backend/` dan `android/` | Client yang membuka repo langsung melihat dua sisinya. |
| Android | Kotlin, Jetpack Compose, MVVM, Hilt | Sama dengan MySavings, jadi tidak perlu belajar ulang. |
| Backend | Laravel (versi stabil terbaru) + Sanctum | Sudah ada di CV, dan token API-nya sederhana. |
| Database | MySQL atau PostgreSQL (SQLite hanya untuk tes cepat) | Perlu kunci baris untuk mencegah dua pembeli mendapat kursi yang sama. SQLite tidak mendukungnya. |
| Data film | Diimpor sekali dari TMDB API ke database lewat perintah artisan | Poster dan sinopsis nyata membuat aplikasi terlihat hidup. **Cek dulu ketentuan TMDB terbaru** soal atribusi dan penyimpanan data. |
| Pembayaran | Midtrans Snap, mode **sandbox** | Umum di Indonesia, dan tidak ada uang sungguhan yang berpindah. |
| Notifikasi | Firebase Cloud Messaging (FCM) dari server | Pengingat dikirim server, jadi tetap sampai walau aplikasi tertutup. |
| Tiket | Kode QR yang berisi kode booking acak | Server yang menentukan keabsahan, bukan isi QR-nya. |
| Pembaruan peta kursi | Polling saat layar kursi terbuka, bukan WebSocket | Cukup untuk v1 dan jauh lebih sederhana. WebSocket atau SSE opsional di akhir. |
| Nama paket | `com.hanan.layarku` | Sama dengan pola `com.hanan.mysavings`. |
| Hosting backend | **Belum diputuskan** | Lihat bagian 14. Tanpa server publik, APK tidak bisa dicoba client. |

---

## 2. Peran pengguna

- **Pelanggan:** mendaftar, menjelajah film, memesan kursi, membayar, melihat tiket.
- **Petugas:** memindai QR tiket di pintu studio dan melihat jadwal hari itu.
- **Sistem:** melepas kursi yang tidak dibayar tepat waktu dan mengirim pengingat.

Pengelolaan film, jadwal, dan studio v1 dilakukan lewat seeder dan perintah artisan. Panel admin web (misalnya Filament) opsional di akhir.

---

## 3. Fitur

### MVP (wajib selesai)
**Pelanggan**
- Daftar, masuk, keluar, ubah nama dan nomor telepon.
- Daftar film dengan tab **Sedang tayang** dan **Segera tayang** (poster, judul, durasi, rating usia).
- Detail film: sinopsis, genre, durasi, rating usia.
- Memilih tanggal dan jam tayang, dikelompokkan per bioskop dan studio.
- **Peta kursi**: kursi tersedia, sedang ditahan, sudah terjual, dan kursi pilihan saya. Maksimal 6 kursi per pesanan, total harga tampil langsung.
- Ringkasan pesanan, lalu membayar lewat Midtrans.
- **Tiket** dengan kode QR, tetap bisa dibuka saat offline (disimpan di Room).
- Daftar tiket saya (akan datang dan riwayat).
- Pengingat 2 jam sebelum film dimulai.

**Petugas**
- Memindai QR tiket dengan kamera, lalu melihat hasil: sah, sudah dipakai, salah jadwal, atau tidak dikenal.
- Daftar jadwal hari ini.

### Setelah MVP (hanya kalau waktu masih ada)
- Jenis kursi (reguler dan premium dengan harga berbeda) dan kursi pasangan.
- Makanan dan minuman sebagai tambahan pesanan.
- Ulasan film.
- Panel admin web untuk film dan jadwal.
- Pembaruan peta kursi langsung (WebSocket atau SSE).

---

## 4. Aturan bisnis

### 4.1 Kursi dan harga
- Setiap studio punya denah kursi tetap. Tiap kursi punya baris (A, B, C...), nomor, dan posisi `grid_x` / `grid_y` supaya lorong dan celah bisa digambar.
- Harga per kursi ditentukan per jadwal tayang (`price_idr`). Harga tiket disimpan di booking saat dibuat, jadi perubahan harga kemudian tidak memengaruhi pesanan yang sudah ada.
- Satu pesanan boleh memuat **1 sampai 6 kursi** pada **satu jadwal tayang**.
- Jadwal yang sudah dimulai tidak bisa dipesan. Pemesanan ditutup **15 menit sebelum** film mulai.

### 4.2 Mencegah kursi ganda
Pembuatan booking dilakukan dalam **satu transaksi database**:
1. Kunci baris jadwal tayang (`lockForUpdate`), sehingga pemesanan untuk jadwal yang sama diproses satu per satu.
2. Periksa bahwa semua kursi yang diminta masih bebas.
3. Simpan booking dan baris `booking_seats`.

Jika ada kursi yang sudah diambil, seluruh permintaan ditolak dengan `409 seat_taken` dan respons menyertakan daftar kursi yang bentrok, sehingga aplikasi bisa menyegarkan peta dan memberi pesan jelas.

Sebagai pengaman kedua, tabel `booking_seats` punya **indeks unik** `(showtime_id, seat_id)`. Baris `booking_seats` dihapus saat booking dibatalkan atau kedaluwarsa, sehingga kursi bebas lagi. Tes konkurensi wajib ada.

### 4.3 Status booking
```
PENDING_PAYMENT ──bayar berhasil──> CONFIRMED ──scan di pintu──> USED
      │
      ├──kedaluwarsa 10 menit──> EXPIRED
      └──dibatalkan pelanggan──> CANCELLED
```
- `PENDING_PAYMENT` menahan kursi selama **10 menit**. Lewat itu, job terjadwal mengubahnya ke `EXPIRED` dan kursi dilepas.
- Perpindahan status di luar diagram ditolak dengan `422`.
- Booking `CONFIRMED` yang jadwalnya sudah lewat tanpa dipindai ditampilkan sebagai "kedaluwarsa" di aplikasi (tanpa status baru di database).

### 4.4 Pembatalan dan refund
- Pelanggan hanya bisa membatalkan booking yang masih `PENDING_PAYMENT`.
- Tiket yang sudah dibayar **tidak bisa dibatalkan dan tidak ada refund**, seperti kebanyakan bioskop. Ini keputusan produk yang disengaja, dan README menyebutnya.

### 4.5 Tiket dan QR
- Setiap booking `CONFIRMED` punya `ticket_code`: string acak panjang (minimal 12 karakter, tidak berurutan). QR hanya memuat kode ini.
- Petugas memindai QR, aplikasi memanggil `POST /staff/tickets/validate`, dan **server** menentukan hasilnya. Tiket yang sudah `USED` ditolak sehingga satu tiket tidak bisa dipakai dua kali.
- Pelanggan bisa membuka tiket saat offline karena kode dan detailnya disimpan di Room. Validasi tetap butuh internet di sisi petugas.

### 4.6 Waktu dan uang
- Semua waktu disimpan **UTC**. API memakai ISO 8601. Zona bioskop `Asia/Jakarta` dipakai untuk tampilan.
- Android memakai `java.time` dan `Clock` yang di-inject. Jangan memanggil `Instant.now()` langsung di logika.
- Uang disimpan sebagai **integer rupiah** (tanpa float), format tampilan dengan `formatRupiah` seperti di MySavings.

---

## 5. Model data (backend)

| Tabel | Kolom penting |
|---|---|
| `users` | id, name, email (unik), phone, password, role (`customer` / `staff`) |
| `cinemas` | id, name, city, address |
| `studios` | id, cinema_id, name |
| `seats` | id, studio_id, row_label, number, grid_x, grid_y |
| `movies` | id, tmdb_id (unik), title, overview, poster_path, backdrop_path, genres, duration_minutes, age_rating, release_date, status (`now_showing` / `coming_soon` / `ended`) |
| `showtimes` | id, movie_id, studio_id, starts_at, ends_at, price_idr |
| `bookings` | id, user_id, showtime_id, status, total_idr, ticket_code (unik, nullable sampai dibayar), expires_at, used_at |
| `booking_seats` | booking_id, showtime_id, seat_id, price_idr. **Unik** pada (showtime_id, seat_id) |
| `payments` | id, booking_id, provider, order_id (unik), gross_amount_idr, status, payment_type, paid_at, raw_notification (json) |
| `devices` | id, user_id, fcm_token (unik), platform, last_seen_at |
| `reminders_sent` | booking_id, kind, sent_at. Unik per (booking_id, kind) |

Indeks tambahan: `showtimes (movie_id, starts_at)` dan `bookings (user_id, created_at)`.

---

## 6. API

Semua di bawah `/api`. Kecuali auth dan webhook, semua endpoint membutuhkan header `Authorization: Bearer <token>`.

| Metode dan jalur | Fungsi |
|---|---|
| `POST /auth/register` · `/login` · `/logout` | Autentikasi (token Sanctum) |
| `GET /me` · `PATCH /me` | Profil |
| `GET /movies?status=now_showing\|coming_soon` | Daftar film dengan paginasi |
| `GET /movies/{id}` | Detail film |
| `GET /movies/{id}/showtimes?date=&cinema_id=` | Jadwal tayang untuk satu film pada satu tanggal |
| `GET /showtimes/{id}/seats` | Peta kursi: tiap kursi berstatus `available`, `held`, atau `sold` |
| `POST /bookings` | Body `{ showtime_id, seat_ids[] }`. Membuat booking `PENDING_PAYMENT` |
| `GET /bookings` | Tiket saya, dengan paginasi |
| `GET /bookings/{id}` | Detail booking, termasuk `ticket_code` setelah dibayar |
| `POST /bookings/{id}/cancel` | Batalkan (hanya yang belum dibayar) |
| `POST /bookings/{id}/pay` | Membuat transaksi Midtrans, mengembalikan `redirect_url` |
| `POST /payments/midtrans/webhook` | Notifikasi Midtrans (publik, **wajib diverifikasi signature-nya**) |
| `POST /devices` | Daftarkan token FCM |
| `POST /staff/tickets/validate` | Body `{ ticket_code }`. Hasil: `valid`, `already_used`, `wrong_showtime`, `unknown` (hanya petugas) |
| `GET /staff/showtimes?date=` | Jadwal hari itu (hanya petugas) |

Format error seragam: `{ "error": { "code": "seat_taken", "message": "...", "details": { "seat_ids": [12, 13] } } }`. Dokumentasi API dibuat otomatis (misalnya Scribe).

---

## 7. Arsitektur Android

Satu modul `:app`, paket per lapisan lalu per fitur, sama seperti MySavings.

```
com.hanan.layarku
├─ data/
│  ├─ remote/       Retrofit API, DTO, interceptor token, error mapper
│  ├─ local/        Room (cache tiket saya), DataStore (token, pengaturan)
│  ├─ repository/   implementasi repository + mapper DTO/entity <-> domain
│  └─ di/           modul Hilt
├─ domain/
│  ├─ model/        Movie, Showtime, Seat, SeatStatus, Booking, BookingStatus, Ticket
│  ├─ repository/   interface
│  └─ usecase/      satu aksi per kelas (termasuk aturan maksimal 6 kursi dan total harga)
└─ ui/
   ├─ theme/  navigation/  components/  util/
   ├─ auth/  movies/  moviedetail/  showtimes/  seatpicker/  checkout/  tickets/  profile/
   └─ staff/            (pemindai tiket dan jadwal hari ini)
```

- Aturan dependensi: `ui` → `domain` ← `data`. `domain` tidak meng-import Android.
- Pola layar: `XxxScreen` stateless dan punya preview, `XxxRoute` menghubungkan ke ViewModel, `XxxViewModel` mengeluarkan `StateFlow<XxxUiState>`.
- **Peta kursi** digambar dari `grid_x` / `grid_y` (misalnya di Compose `Canvas` atau grid biasa), dengan legenda, ukuran sentuh yang cukup, dan aksesibilitas (deskripsi tiap kursi untuk pembaca layar). Peta disegarkan berkala saat layar terbuka dan langsung setelah `409 seat_taken`.
- **QR**: dibuat di sisi aplikasi dari `ticket_code` (library pembuat QR, ditambahkan lewat version catalog). Pemindaian petugas memakai CameraX + ML Kit barcode scanning, seperti pengalaman di MySavings.
- Jaringan: Retrofit + OkHttp + kotlinx.serialization. Semua pemanggilan dibungkus hasil `Result` agar error jaringan, `409`, dan `422` punya tampilan jelas.
- Poster dimuat dengan library pemuat gambar (misalnya Coil), dengan tampilan cadangan saat gagal atau loading.
- Emulator mengakses backend lokal lewat `http://10.0.2.2:8000`.

---

## 8. Arsitektur backend

- Controller tipis. Logika ada di kelas aksi atau service (`CreateBooking`, `GetSeatMap`, `ExpireUnpaidBookings`, `CreateMidtransTransaction`, `ValidateTicket`).
- Validasi lewat Form Request, keluaran lewat API Resource.
- Job terjadwal (scheduler + queue): kedaluwarsakan booking tak dibayar, kirim pengingat.
- Perintah `php artisan movies:import` mengambil film dari TMDB dan membuat jadwal tayang demo untuk beberapa hari ke depan.
- Seeder membuat bioskop fiktif, studio dengan denah kursi, akun demo pelanggan dan petugas, dan beberapa booking contoh.
- Perintah `php artisan demo:reset` mengembalikan data demo ke kondisi awal, dan menggeser jadwal tayang ke tanggal terdekat supaya tidak ada jadwal yang sudah lewat.
- Rate limit pada auth dan pembuatan booking, karena API demo ini publik.

---

## 9. Pembayaran (Midtrans sandbox)

1. Aplikasi memanggil `POST /bookings/{id}/pay`.
2. Backend membuat transaksi Snap di Midtrans (server key hanya di `.env`) dan mengembalikan `redirect_url`.
3. Aplikasi membuka `redirect_url` di Custom Tab.
4. Midtrans memanggil `POST /payments/midtrans/webhook`. Backend memverifikasi `signature_key`, mencocokkan `order_id` dan nominal, lalu mengubah status booking ke `CONFIRMED` dan membuat `ticket_code`.
5. Aplikasi kembali, memuat ulang detail booking, dan menampilkan tiket. Push "Pembayaran berhasil" ikut dikirim.

Webhook harus **idempoten** dan menolak signature yang salah. Waktu tahan kursi (10 menit) harus lebih panjang dari waktu kedaluwarsa transaksi Midtrans, atau sebaliknya diselaraskan, supaya pelanggan tidak membayar untuk kursi yang sudah dilepas. Jika itu terjadi, pembayaran yang masuk setelah booking `EXPIRED` ditandai `REFUND_NEEDED` dan tidak mengubah status booking. Untuk tes lokal, webhook butuh URL publik (tunnel).

---

## 10. Notifikasi

- Aplikasi mengirim token FCM ke `POST /devices` setelah login dan setiap token berganti.
- Backend mengirim lewat FCM HTTP v1 memakai service account yang **tidak** masuk repo.
- Jenis: pembayaran berhasil, pengingat 2 jam sebelum film.
- `reminders_sent` mencegah pengingat terkirim dua kali. Token yang ditolak FCM dihapus dari `devices`.

---

## 11. Keamanan dan rahasia

- Tidak ada rahasia di repo: Midtrans server key, kredensial FCM, kunci TMDB, dan `APP_KEY` hanya di `.env` atau secret GitHub. Sediakan `.env.example` tanpa nilai asli.
- `google-services.json` untuk FCM boleh ada di repo, tapi service account server tidak.
- Cek kepemilikan di setiap endpoint: pelanggan hanya bisa membaca booking miliknya, dan `/staff/*` menolak peran lain dengan `403`.
- `ticket_code` acak dan tidak berurutan, dan hanya server yang menilai keabsahannya.
- Password di-hash, token Sanctum bisa dicabut, ada rate limit.
- Jangan meniru kesalahan Ambulansku: kunci API tidak boleh pernah ter-commit. Periksa `git diff --staged` sebelum commit.

---

## 12. Pengujian

**Backend (Pest atau PHPUnit)**
- Peta kursi: status `available`, `held`, `sold` tepat sesuai booking yang ada.
- **Kursi ganda:** dua permintaan bersamaan untuk kursi yang sama, hanya satu yang berhasil. Satu lagi untuk kursi yang saling tumpang tindih sebagian (A1–A3 dan A3–A5). Pakai database sungguhan, bukan SQLite.
- Batas 6 kursi, pemesanan ditutup 15 menit sebelum mulai, kursi dari studio lain ditolak.
- Kedaluwarsa: kursi terlepas dan bisa dipesan lagi.
- Perpindahan status yang sah dan yang ditolak.
- Webhook: signature benar, signature salah, notifikasi ganda, nominal tidak cocok, pembayaran masuk setelah `EXPIRED`.
- Validasi tiket: sah, sudah dipakai, jadwal salah, kode tidak dikenal, peran selain petugas ditolak.
- Otorisasi: pelanggan A tidak bisa membaca booking pelanggan B.

**Android (JUnit 4, MockK, Turbine, coroutines-test)**
- Use case dan ViewModel: pemilihan kursi (batas 6, total harga), pemetaan error `409` dan `422`.
- Pemformatan waktu dan zona (`Clock` di-inject).
- Test Room untuk cache tiket di `androidTest`.

---

## 13. Fase kerja

Satu fase per permintaan ke Claude Code, dengan rencana singkat dulu, lalu commit di akhir fase.

| Fase | Isi | Hasil yang bisa ditunjukkan |
|---|---|---|
| 0 | Repo monorepo, struktur folder, `.gitignore`, `.env.example`, README awal | Repo kosong yang rapi |
| 1 | Backend: proyek Laravel, migrasi, seeder bioskop dan studio, `movies:import`, auth Sanctum, `GET /movies`, `/showtimes`, `/seats` | Daftar film dan peta kursi lewat curl |
| 2 | Backend: `POST /bookings`, kunci transaksi, kedaluwarsa, pembatalan, tes konkurensi | Logika kursi yang teruji |
| 3 | Android: setup proyek, tema, navigasi, Hilt, Retrofit, layar masuk dan daftar | Login dari aplikasi ke backend lokal |
| 4 | Android: daftar film, detail, jadwal, **peta kursi**, ringkasan, cache Room (belum bayar) | Memesan kursi dari awal sampai ringkasan |
| 5 | Pembayaran: `/pay`, webhook, Custom Tab, tiket dengan QR yang tersimpan offline | Bayar di sandbox lalu tiket QR muncul |
| 6 | FCM: pendaftaran token, push dari server, pengingat terjadwal | Notifikasi sampai ke HP |
| 7 | Sisi petugas: pemindai QR, hasil validasi, jadwal hari ini | Dua peran bekerja |
| 8 | Deploy backend, `demo:reset`, GitHub Actions (tes backend dan build Android), APK ke GitHub Releases, README EN dan ID, atribusi TMDB, video | Siap dilihat client |

---

## 14. Risiko dan hal yang masih terbuka

- **Hosting backend.** APK portofolio hanya berguna kalau backend-nya hidup di internet. Pilih layanan hosting dan periksa batas paket gratisnya, karena ketentuannya sering berubah. README harus jelas soal apa yang terjadi bila server demo mati.
- **Ketentuan TMDB.** Periksa syarat atribusi, penyimpanan data, dan penggunaan gambar sebelum fase 1. Cantumkan atribusi di aplikasi (layar tentang) dan di README.
- **Merek.** Gunakan nama dan logo fiktif sepenuhnya. Jangan memakai nama atau logo bioskop sungguhan.
- **Webhook Midtrans butuh URL publik**, jadi pengujian lokal butuh tunnel.
- **Cakupan.** Fase 4 (peta kursi) dan fase 5 yang paling mudah membengkak. Bila waktu sempit, selesaikan fase 1–5 dulu. Pengingat dan sisi petugas bisa menyusul.
- **Jadwal demo basi.** Data tayang demo cepat kedaluwarsa. `demo:reset` harus menggeser jadwal ke tanggal terdekat, dan itu perlu dijalankan terjadwal di server demo.
- **Kursi ditahan.** Pelanggan yang meninggalkan layar pembayaran menahan kursi 10 menit. Itu perilaku yang diterima, tapi harus terlihat jelas di UI (hitung mundur).

---

## 15. Definisi selesai (siap ditunjukkan ke client)

- [ ] Alur pelanggan penuh jalan di HP sungguhan: daftar, pilih film, pilih kursi, bayar (sandbox), tiket QR muncul, pengingat sampai.
- [ ] Tiket tetap terbuka saat offline.
- [ ] Alur petugas jalan: pindai QR sah, sudah dipakai, dan salah jadwal menghasilkan hasil yang tepat.
- [ ] Tes backend lulus, termasuk tes kursi ganda.
- [ ] Tes Android lulus.
- [ ] CI hijau di GitHub Actions.
- [ ] Backend demo online dan data demo bisa direset.
- [ ] APK ditandatangani dan ada di GitHub Releases.
- [ ] README (EN dan ID) berisi cara instal, akun uji, batasan yang jujur (tanpa refund, data demo), keputusan desain, dan atribusi TMDB.
- [ ] Video demo singkat (60–90 detik) dan screenshot.
- [ ] Tidak ada rahasia di repo, termasuk di riwayat git.
