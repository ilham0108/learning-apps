# Prompt Recruitment LMS (React + Inertia)

## Catatan sebelum dipakai

Salin semua bagian di bawah catatan ini ke AI. Beberapa keputusan bisnis belum ditentukan, jadi diisi dengan default yang sederhana dan aman. Periksa dan ubah jika perlu sebelum dipakai:

| Keputusan                     | Default yang dipakai                                                                      | Lokasi    |
| ----------------------------- | ----------------------------------------------------------------------------------------- | --------- |
| Lingkup akses HR              | HR hanya melihat gelombang rekrutmen yang ditugaskan kepadanya; Super Admin melihat semua | Bagian 4  |
| Nilai dari percobaan berulang | Dapat diatur per ujian; default nilai tertinggi                                           | Bagian 8  |
| Status kandidat               | Enum tetap, bukan dapat ditambah HR                                                       | Bagian 5  |
| Retensi data                  | 12 bulan setelah gelombang ditutup, lalu dianonimkan                                      | Bagian 13 |
| Video materi                  | Embed tautan (YouTube/Vimeo) dan unggah file maksimal 200 MB                              | Bagian 6  |
| Bahasa antarmuka              | Bahasa Indonesia, teks disiapkan agar mudah diterjemahkan                                 | Bagian 12 |
| Zona waktu tampilan           | Asia/Jakarta; disimpan dalam UTC                                                          | Bagian 3  |

---

## 1. Peran AI

Bertindaklah sebagai Senior Full-Stack Developer, Software Architect, UI/UX Designer, Database Designer, dan Business Analyst yang berpengalaman membangun Learning Management System (LMS), platform assessment rekrutmen, dan sistem pelatihan karyawan.

Tugas Anda adalah merancang dan membangun aplikasi web bernama **Recruitment LMS & Assessment System**.

Bangun sistem yang benar-benar berfungsi: backend, database, autentikasi, otorisasi, business logic, validasi, dan frontend. Jangan hanya membuat desain atau mockup. Aplikasi harus terstruktur, aman, responsif, mudah dikembangkan, dan siap produksi setelah pengujian yang memadai.

## 2. Tujuan Aplikasi

Aplikasi digunakan tim HR untuk mengelola pembelajaran dan pengujian calon karyawan. Sistem harus dapat:

- Mengelola data kandidat dan gelombang rekrutmen.
- Mengelola program pembelajaran dan materi.
- Mengelola bank soal dan paket ujian.
- Mengatur jadwal dan batas waktu ujian.
- Melaksanakan ujian online dengan waktu yang dikendalikan server.
- Mengoreksi jawaban objektif secara otomatis dan mendukung penilaian manual.
- Menyimpan riwayat pengerjaan ujian secara lengkap dan tidak berubah.
- Memantau progres pembelajaran kandidat.
- Membantu HR mengevaluasi kandidat berdasarkan beberapa kriteria, bukan satu nilai saja.
- Menghasilkan laporan evaluasi kandidat.
  Sistem harus dapat dipakai berulang untuk berbagai posisi, departemen, dan gelombang rekrutmen.

## 3. Teknologi dan Arsitektur

**Backend**

- Laravel 13 dengan PHP 8.3 atau versi lebih baru yang kompatibel dengan semua dependensi.
- MariaDB atau MySQL, Eloquent ORM.
- Migrations, seeders, factories, Form Requests, Policies, dan service classes.
- Queue (driver database untuk awal) untuk email, ekspor besar, dan pekerjaan latar belakang.
- Scheduler Laravel untuk menutup attempt ujian yang kedaluwarsa.
- Pest atau PHPUnit untuk pengujian backend.
  **Frontend**

- React dengan TypeScript melalui Inertia.js, dalam satu project Laravel yang sama. Jangan membuat SPA terpisah atau API publik kecuali benar-benar dibutuhkan.
- Gunakan starter kit React resmi Laravel sebagai fondasi autentikasi dan layout.
- Tailwind CSS dan shadcn/ui untuk komponen yang konsisten.
- Lucide React untuk ikon (lihat Bagian 12).
- Vite untuk bundling.
- Gunakan `useForm` dari Inertia untuk formulir. Validasi utama tetap di server melalui Form Request; validasi di klien hanya untuk kenyamanan.
- Definisikan tipe TypeScript untuk setiap props halaman dan model yang dikirim dari controller. Hindari `any`.
- Kirim ke frontend hanya field yang dibutuhkan halaman, melalui API Resource atau DTO. Jangan mengirim model Eloquent utuh.
- Halaman pengerjaan ujian memakai endpoint JSON khusus (autosave, sinkronisasi waktu, submit) melalui fetch/axios, bukan kunjungan Inertia penuh.
- Vitest dan React Testing Library untuk komponen penting.
- Playwright atau Pest browser testing untuk tes end-to-end alur utama.
  **Arsitektur**

- Modular monolith: kelompokkan kode per domain (Candidates, Learning, QuestionBank, Exams, Reporting).
- Controller tipis; business logic di service atau action class.
- Form Request untuk validasi, Policy untuk otorisasi.
- Database transaction dan row locking untuk proses yang membutuhkan konsistensi (mulai ujian, submit, penilaian).
- Simpan semua waktu dalam UTC; tampilkan dalam zona Asia/Jakarta.
- Gunakan PHP enum untuk status, bukan string bebas.
- Hindari duplikasi kode dan abstraksi berlebihan untuk kebutuhan sederhana.
  Jangan mengganti teknologi utama tanpa alasan teknis yang kuat dan persetujuan saya.

## 4. Role dan Hak Akses

Sediakan tiga role. Semua otorisasi diterapkan di server melalui Policy; menyembunyikan menu di frontend bukan pengamanan.

**Super Admin**: mengelola akun administrator dan HR, konfigurasi sistem, seluruh data, seluruh program dan ujian, seluruh laporan, serta penugasan HR ke gelombang rekrutmen.

**HR / Recruitment Administrator**: mengelola kandidat, gelombang, program, materi, kategori soal, bank soal, ujian, passing grade, jadwal, undangan, penilaian manual, status seleksi, dan ekspor laporan.

**Candidate**: login, melengkapi profil, melihat instruksi, mengakses materi yang ditugaskan, mengerjakan ujian yang tersedia, melihat hasil yang sudah dipublikasikan, dan melihat riwayat miliknya sendiri.

**Lingkup data HR (default)**: HR hanya dapat mengakses kandidat, attempt, hasil, dan laporan dari gelombang rekrutmen yang ditugaskan kepadanya (relasi many-to-many `campaign_user`). Bank soal, program, dan ujian bersifat bersama untuk semua HR. Terapkan lingkup ini di query (scope) dan di Policy, termasuk pada ekspor dan dashboard.

Kandidat tidak boleh mengakses data kandidat lain, bank soal, kunci jawaban yang belum dipublikasikan, catatan internal HR, atau halaman administrasi.

Buat matriks hak akses (role × aksi) pada Tahap 1, lalu tulis tes otorisasi berdasarkan matriks tersebut.

## 5. Modul Kandidat dan Gelombang Rekrutmen

**Gelombang rekrutmen** memiliki nama, posisi, departemen, periode, status (draft, open, closed), dan HR yang ditugaskan.

**Fitur kandidat**: tambah, edit, detail, nonaktifkan, pencarian, filter, dan pagination di server. Impor CSV dan undangan email masuk fase 2 (lihat Bagian 15).

**Data kandidat**: nama lengkap, email, nomor telepon, nomor kandidat (dibuat otomatis, unik), pendidikan terakhir (opsional), dan tanggal pendaftaran. Posisi, departemen, dan gelombang melekat pada **lamaran** (`recruitment_applications`), bukan pada profil, agar satu kandidat bisa melamar di gelombang berbeda.

**Catatan internal HR** disimpan terpisah, hanya terlihat oleh HR yang berwenang, dan tidak pernah dikirim ke halaman kandidat.

Pisahkan tiga jenis status:

| Jenis                         | Nilai                                                                      | Cara berubah                                   |
| ----------------------------- | -------------------------------------------------------------------------- | ---------------------------------------------- |
| Tahap rekrutmen (per lamaran) | invited, registered, under_review, shortlisted, rejected, hired, withdrawn | Diubah HR, setiap perubahan dicatat di riwayat |
| Progres belajar               | not_started, in_progress, completed                                        | Dihitung dari progres materi                   |
| Hasil ujian                   | lihat Bagian 9                                                             | Dihitung dari attempt dan penilaian            |

Gunakan PHP enum untuk tahap rekrutmen. Catat riwayat perubahan tahap (siapa, kapan, dari apa ke apa, alasan).

Jangan menyimpan data sensitif yang tidak dibutuhkan untuk seleksi (misalnya NIK, agama, status pernikahan, foto).

## 6. Program Pembelajaran dan Materi

**Program** memiliki nama, deskripsi, kategori, target posisi, status publikasi (draft, published, archived), estimasi durasi, daftar modul berurutan, dan ujian terkait.

**Modul** berisi materi berurutan. Setiap materi ditandai wajib atau opsional.

**Jenis materi dan aturan penyelesaian**:

| Jenis            | Penyimpanan                                        | Aturan selesai                                                   |
| ---------------- | -------------------------------------------------- | ---------------------------------------------------------------- |
| Artikel/teks     | Database (HTML disanitasi)                         | Tombol "Tandai selesai"                                          |
| PDF              | Storage privat, maks. 20 MB                        | Tombol "Tandai selesai"                                          |
| Gambar           | Storage privat, maks. 5 MB, JPG/PNG/WebP           | Tombol "Tandai selesai"                                          |
| Video            | Embed YouTube/Vimeo atau unggahan MP4 maks. 200 MB | Tombol "Tandai selesai"; aturan durasi tonton opsional di fase 2 |
| Tautan eksternal | URL tervalidasi                                    | Tombol "Tandai selesai"                                          |

Membuka halaman tidak dianggap selesai. Catat waktu pertama dibuka dan waktu selesai.

**Penugasan program** dapat ke individu, gelombang, posisi, atau kelompok kandidat terpilih. Simpan hasil penugasan sebagai baris per kandidat agar progres dan otorisasi mudah dihitung.

**Akses file**: semua file disimpan di disk privat dan dilayani melalui controller yang memeriksa Policy, atau melalui URL bertanda tangan yang berlaku singkat (misalnya 10 menit). Validasi tipe MIME sebenarnya, ekstensi, dan ukuran saat unggah.

**Mengubah program yang sudah ditugaskan**: tambahan materi wajib baru tidak menghapus progres lama; progres dihitung ulang terhadap daftar materi wajib saat ini.

## 7. Bank Soal

Setiap soal memiliki: pertanyaan, kategori, topik, tingkat kesulitan (easy, medium, hard), jenis soal, bobot default, status (active, archived), penjelasan jawaban, pembuat, dan timestamp.

**Jenis soal dan aturan penilaian**:

| Jenis           | Penilaian                                                                                  | Catatan                                                                            |
| --------------- | ------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------- |
| Multiple choice | Otomatis: penuh atau nol                                                                   | Tepat satu pilihan benar                                                           |
| Multiple select | Otomatis: default penuh hanya jika semua pilihan benar dipilih dan tidak ada pilihan salah | Opsi per ujian: nilai parsial (benar dipilih − salah dipilih, minimum 0)           |
| True/false      | Otomatis: penuh atau nol                                                                   | Disimpan sebagai dua pilihan                                                       |
| Short answer    | Otomatis jika cocok dengan salah satu jawaban yang diterima                                | Abaikan huruf besar/kecil dan spasi berlebih; tidak cocok → antre penilaian manual |
| Essay           | Manual                                                                                     | Rubrik dan nilai maksimal per soal                                                 |
| Case study      | Manual                                                                                     | Satu teks kasus dapat memiliki beberapa sub-pertanyaan dengan jenis apa pun        |

**Fitur**: pencarian, filter kategori/kesulitan/jenis/status, edit, arsip, dan riwayat pemakaian di ujian.

**Versioning**: soal yang sudah dipakai dalam attempt tidak boleh diubah secara diam-diam. Saat soal diedit, attempt lama tetap memakai snapshot (lihat Bagian 9). Soal yang sudah dipakai diarsipkan, bukan dihapus.

Kunci jawaban dan penjelasan tidak pernah dikirim ke browser kandidat sebelum kebijakan publikasi hasil mengizinkan. Pastikan ini di level API Resource, bukan hanya di tampilan.

## 8. Ujian

**Konfigurasi ujian**: nama, deskripsi, jenis (pre-test, post-test, pengetahuan umum, teknis posisi, pemahaman produk, SOP, studi kasus), soal dari bank soal, bobot per soal (menimpa bobot default bank soal), durasi dalam menit, passing grade (0–100), jumlah percobaan maksimal, waktu buka dan tutup, acak soal, acak pilihan, navigasi bebas atau maju saja, prasyarat materi wajib, kebijakan nilai percobaan (tertinggi, terakhir; default tertinggi), waktu publikasi nilai (langsung, setelah ujian ditutup, manual oleh HR), dan tampilkan pembahasan atau tidak.

Ujian yang sudah memiliki attempt tidak boleh diubah soal atau bobotnya. Sediakan fitur duplikasi untuk membuat versi baru.

**Penugasan ujian** ke individu, gelombang, posisi, atau kelompok terpilih, disimpan sebagai baris per kandidat. Penugasan individu dapat memiliki jadwal khusus (misalnya perpanjangan).

## 9. Mesin Pengerjaan Ujian

**Memulai ujian** (dalam satu transaction dengan lock):

1. Validasi akun aktif.
2. Validasi penugasan.
3. Validasi jadwal menurut waktu server.
4. Validasi jumlah percobaan.
5. Validasi prasyarat materi jika berlaku.
6. Jika ada attempt yang masih berjalan, lanjutkan attempt tersebut; jangan membuat yang baru.
7. Buat attempt dengan `started_at` dan `deadline_at = min(started_at + durasi, waktu tutup ujian)`.
8. Simpan **snapshot** soal, pilihan, bobot, dan kunci jawaban ke attempt, beserta urutan acak soal dan pilihan.
   **Selama ujian**:

- Jawaban disimpan otomatis per soal (debounce sekitar 2 detik dan saat pindah soal) ke tabel jawaban relasional, satu baris per soal.
- Kandidat dapat menandai soal untuk ditinjau.
- Sisa waktu dihitung dari `deadline_at` yang dikirim server; klien menyinkronkan ulang secara berkala dan saat tab kembali aktif.
- Server menolak penyimpanan jawaban setelah `deadline_at` atau setelah attempt dikunci.
- Refresh atau koneksi terputus aman: jawaban dan posisi dipulihkan dari server; jawaban yang gagal tersimpan diantrekan di klien dan dikirim ulang.
- Tampilkan indikator status simpan (tersimpan, menyimpan, gagal).
- Konfirmasi sebelum submit, menampilkan jumlah soal belum dijawab dan yang ditandai.
  **Mengakhiri ujian**:

- Submit manual oleh kandidat, atau auto-submit oleh server saat `deadline_at` tercapai.
- Auto-submit dijalankan dua cara: scheduled job setiap menit untuk attempt yang kedaluwarsa, dan pengecekan saat kandidat mengakses attempt tersebut.
- Submit harus **idempotent**: kunci baris attempt (`SELECT ... FOR UPDATE`), proses hanya jika status masih `in_progress`, lalu ubah status dan hitung nilai dalam transaction yang sama. Submit kedua mengembalikan hasil yang sama tanpa menilai ulang.
- Satu sesi aktif per attempt; catat IP, user agent, dan peristiwa pindah tab sebagai log. Log ini bahan pertimbangan HR, bukan bukti kecurangan otomatis.
  **Status attempt** dipisah menjadi tiga field:

| Field            | Nilai                                           |
| ---------------- | ----------------------------------------------- |
| `status`         | in_progress, submitted, auto_submitted, expired |
| `grading_status` | pending_manual, graded                          |
| `result`         | passed, failed, atau null jika belum final      |

"Not started" adalah keadaan penugasan tanpa attempt, bukan status attempt.

## 10. Penilaian

Nilai akhir = (total poin diperoleh ÷ total poin maksimal) × 100, dengan bobot dari snapshot attempt. Bulatkan ke dua desimal hanya saat ditampilkan; simpan dengan presisi `DECIMAL(6,2)` atau lebih.

- Hitung juga nilai per kategori soal.
- Jika ada soal manual yang belum dinilai, tampilkan nilai sementara dan tandai "belum final". `result` baru diisi setelah semua soal dinilai.
- Penilaian manual: HR memberi poin (0 sampai poin maksimal soal) dan catatan. Simpan siapa yang menilai dan kapan. Perubahan nilai setelah final dicatat di audit log dengan alasan.
- Nilai kandidat untuk ujian ditentukan oleh kebijakan percobaan (tertinggi atau terakhir).
- Kandidat hanya melihat nilai, kunci, dan pembahasan sesuai kebijakan publikasi ujian.
- Keputusan rekrutmen tidak ditentukan otomatis dari nilai. Sediakan form evaluasi HR berisi kriteria (misalnya hasil ujian, progres belajar, wawancara, catatan) dan rekomendasi akhir.

## 11. Dashboard HR dan Kandidat

**Dashboard HR** (hanya data dalam lingkup HR tersebut): total kandidat, kandidat baru, sedang belajar, sedang ujian, lulus, gagal, menunggu penilaian manual, rata-rata nilai, persentase penyelesaian materi, aktivitas terbaru, dan ujian yang akan ditutup dalam 7 hari. Filter: periode, posisi, departemen, gelombang, program, dan tahap rekrutmen. Semua angka dari query database aktual; gunakan agregasi di database, bukan perulangan di PHP.

**Dashboard kandidat**: profil, posisi dan tahap rekrutmen, program yang ditugaskan dengan persentase progres, materi yang harus diselesaikan, ujian tersedia beserta jadwal dan sisa percobaan, riwayat ujian, nilai yang sudah dipublikasikan, dan status evaluasi. Bedakan secara visual: belum dimulai, berlangsung, selesai, menunggu evaluasi.

## 12. Laporan dan UI/UX

**Laporan**: kandidat dan hasil ujian, rekap nilai, nilai per kategori, progres belajar, tingkat kelulusan, kandidat yang belum menyelesaikan materi, belum mengikuti ujian, dan menunggu penilaian manual. Ekspor CSV di MVP; ekspor Excel dan perbandingan antar-gelombang di fase 2. Ekspor besar diproses lewat queue dan file hasil hanya dapat diunduh oleh peminta. Ekspor dicatat di audit log.

**Gaya visual**: modern, minimalis, profesional. Dominan putih, biru profesional sebagai warna utama, netral untuk latar dan komponen, hijau untuk berhasil, merah untuk gagal dan tindakan berisiko. Sidebar untuk HR, navigasi sederhana untuk kandidat.

**Komponen konsisten** (shadcn/ui): tombol, formulir, modal, badge status, tabel dengan sorting dan pagination server, toast notifikasi, dialog konfirmasi, empty state, error state, loading state dan skeleton.

**Ikon**: gunakan hanya Lucide React (`lucide-react`), bawaan shadcn/ui; jangan mencampur library ikon lain.

- Impor per ikon (`import { Users } from 'lucide-react'`), jangan mengimpor seluruh library.
- Ukuran standar: 16 px di tombol dan tabel, 20 px di sidebar dan judul kartu.
- Ikon tanpa teks (misalnya tombol aksi di tabel) wajib memiliki `aria-label` dan tooltip.
- Ikon dekoratif di samping teks diberi `aria-hidden="true"`.
- Status tidak dibedakan hanya dengan ikon atau warna; selalu sertakan label teks.
- Petakan ikon untuk konsep yang berulang (kandidat, materi, ujian, timer, status) di satu file agar konsisten di seluruh aplikasi.
  **Responsif** di desktop, tablet, dan smartphone. Halaman ujian harus nyaman di layar 360 px.

**Aksesibilitas**: kontras warna memadai, navigasi keyboard, label pada setiap input, dan status yang tidak hanya dibedakan dengan warna.

**Bahasa**: antarmuka berbahasa Indonesia. Simpan teks di file terjemahan, bukan ditulis langsung di komponen, agar mudah ditambah bahasa lain.

## 13. Database, Keamanan, dan Privasi

**Rancangan awal tabel** (evaluasi, gabungkan, atau pisahkan sesuai kebutuhan):

- Pengguna: `users`, `candidate_profiles`, `campaign_user`
- Rekrutmen: `recruitment_campaigns`, `recruitment_applications`, `application_stage_histories`, `candidate_evaluations`, `internal_notes`
- Pembelajaran: `learning_programs`, `learning_modules`, `learning_materials`, `program_assignments`, `material_progress`
- Bank soal: `question_categories`, `questions`, `question_options`, `question_accepted_answers`
- Ujian: `exams`, `exam_questions`, `exam_assignments`
- Pengerjaan: `exam_attempts`, `attempt_questions` (snapshot dan urutan), `attempt_answers`, `attempt_answer_options`, `attempt_events`
- Lainnya: `activity_logs`, `jobs`, `failed_jobs`
  Gunakan foreign key, index pada kolom filter dan relasi, unique constraint (misalnya satu attempt aktif per penugasan, satu jawaban per soal per attempt), timestamp, dan soft delete hanya jika sesuai. Jangan menyimpan seluruh jawaban sebagai satu JSON besar. Buat migration, model, relasi, factory, dan seeder.

Pisahkan seeder: **produksi** (role dan Super Admin awal dari environment variable) dan **demo** (data contoh untuk pengembangan, tidak pernah dijalankan di produksi).

**Keamanan**:

- Autentikasi aman, rate limiting login dan endpoint sensitif (autosave, submit, ekspor).
- Otorisasi berdasarkan role, kepemilikan data, dan lingkup gelombang.
- Validasi input server, proteksi CSRF, proteksi mass assignment (`$fillable` eksplisit).
- Sanitasi HTML pada materi artikel dan soal untuk mencegah XSS.
- Validasi dan pembatasan akses file.
- Kunci jawaban tidak pernah dikirim ke klien sebelum diizinkan.
- Audit log untuk tindakan administratif penting: ubah status, ubah nilai, ekspor, ubah hak akses, hapus data.
- Sesi aman (cookie secure, httponly, samesite), regenerasi sesi saat login.
- Error produksi tanpa detail teknis (`APP_DEBUG=false`).
- Pengacakan soal dan menyembunyikan tombol bukan pengamanan.
  **Tidak mengekspos ID internal ke frontend**:

- Primary key auto-increment tetap dipakai di database untuk relasi dan performa, tetapi tidak pernah muncul di URL, props Inertia, respons JSON, nama file ekspor, atau atribut HTML.
- Setiap tabel yang diakses dari frontend memiliki kolom `public_id` bertipe ULID (unik, terindeks), diisi otomatis lewat trait `HasUlids` dengan `uniqueIds()` yang menunjuk ke kolom tersebut.
- Route model binding memakai `public_id` (`getRouteKeyName()`), misalnya `/candidates/01JC8X...`.
- API Resource dan DTO hanya mengirim `public_id` sebagai `id`. Foreign key (misalnya `campaign_id`, `user_id`) tidak dikirim; ganti dengan `public_id` relasinya bila diperlukan.
- Input dari frontend yang merujuk data lain (filter, pilihan dropdown, penugasan) menerima `public_id` dan divalidasi di Form Request (`exists` pada kolom `public_id` dan dalam lingkup akses pengguna), lalu diubah ke ID internal di server.
- Di halaman ujian, soal dan pilihan jawaban memakai ID acak per attempt, bukan ID bank soal, agar urutan, identitas soal, dan kunci tidak dapat ditebak atau dicocokkan antar-kandidat.
- Pesan error dan halaman 404/403 tidak menyebutkan ID atau keberadaan data milik pengguna lain; data di luar lingkup akses dikembalikan sebagai 404.
- ID tersamar bukan pengganti otorisasi. Setiap akses tetap diperiksa oleh Policy.
- Tambahkan tes yang memastikan props halaman dan respons JSON utama tidak memuat kunci `id` numerik atau foreign key internal.
  **Privasi (UU No. 27/2022 tentang Pelindungan Data Pribadi)**:

- Persetujuan pemrosesan data saat kandidat pertama kali login, disimpan beserta waktu dan versi kebijakan.
- Kumpulkan hanya data yang dibutuhkan untuk seleksi.
- Retensi default: 12 bulan setelah gelombang ditutup, lalu data pribadi dianonimkan oleh scheduled job. Nilai agregat tetap untuk laporan.
- Kandidat dapat mengajukan penghapusan data; Super Admin memprosesnya.
- Catatan evaluasi internal hanya untuk HR yang berwenang.

## 14. Testing

Tulis automated test untuk kasus normal, input tidak valid, akses tanpa izin, dan kondisi batas.

**Backend (Pest/PHPUnit)**:

- Login, logout, dan pembatasan percobaan login.
- Hak akses tiap role sesuai matriks, termasuk lingkup gelombang HR.
- Isolasi data antarkandidat.
- Program, penugasan materi, dan pelacakan progres.
- Pembuatan ujian dan larangan mengubah ujian yang sudah memiliki attempt.
- Validasi jadwal, jumlah percobaan, dan prasyarat materi.
- Penyimpanan jawaban dan penolakan setelah deadline.
- Penilaian tiap jenis soal, termasuk multiple select penuh dan parsial.
- Penghitungan nilai berbobot dan per kategori.
- Penilaian essay dan perubahan status ke final.
- Auto-submit oleh scheduled job (gunakan manipulasi waktu).
- Submit ganda bersamaan tidak menghasilkan penilaian ganda.
- Snapshot: mengedit soal tidak mengubah attempt lama.
- Pembatasan akses hasil dan kunci jawaban sebelum publikasi.
- ID internal tidak muncul di props halaman maupun respons JSON.
- Ekspor laporan sesuai lingkup.
- Validasi file unggahan (tipe, ukuran, file berbahaya).
  **Frontend (Vitest + React Testing Library)**: timer, navigasi soal, indikator simpan, dan dialog konfirmasi submit.

**End-to-end (Playwright atau Pest browser testing)**: HR membuat ujian dan menugaskannya; kandidat mengerjakan, refresh di tengah, lalu submit; HR menilai essay; kandidat melihat hasil yang dipublikasikan.

## 15. Ruang Lingkup MVP

**Masuk MVP**: semua bagian di atas, kecuali daftar fase 2.

**Fase 2**: impor kandidat dari CSV, undangan dan notifikasi email, ekspor Excel, perbandingan antar-gelombang, aturan durasi tonton video, nilai parsial lanjutan, dan status rekrutmen yang dapat dikonfigurasi HR.

Rancang database dan kode MVP agar fitur fase 2 dapat ditambahkan tanpa migrasi besar.

## 16. Dokumentasi dan Deployment

Sediakan `README.md` berisi: instalasi (Composer dan npm), konfigurasi `.env` dengan `.env.example`, migrasi dan seeding, pembuatan Super Admin pertama, menjalankan queue worker dan scheduler, panduan HR, panduan kandidat, struktur database (ERD dalam Mermaid), cara menjalankan semua tes, dan panduan deployment produksi (build aset, cache config/route/view, supervisor untuk queue, cron untuk scheduler, HTTPS, backup database dan storage).

Jangan menyimpan kredensial, `APP_KEY`, atau rahasia lain di repository.

## 17. Cara Pengerjaan

1. Analisis kebutuhan dan rancangan arsitektur.
2. Rancangan database dan migration.
3. Autentikasi, role, otorisasi, dan layout React/Inertia.
4. Modul kandidat dan gelombang rekrutmen.
5. Modul program pembelajaran dan materi.
6. Modul bank soal dan ujian.
7. Mesin pengerjaan ujian dan penilaian.
8. Dashboard HR dan kandidat.
9. Laporan dan ekspor.
10. Hardening keamanan, tes end-to-end, dokumentasi, dan deployment.
    Pada setiap tahap: jelaskan tujuan dan ruang lingkup, tampilkan struktur file yang dibuat atau diubah, implementasikan kode lengkap, jelaskan perubahan database, tulis dan jalankan tes, periksa integrasi dengan tahap sebelumnya, perbaiki error, lalu ringkas perubahan dan cara menjalankannya.

**Definition of Done setiap tahap**:

- `php artisan migrate:fresh --seed` berhasil.
- `php artisan test` dan `npm run test` lulus.
- `npm run build` dan pemeriksaan TypeScript (`tsc --noEmit`) tanpa error.
- Linter (Laravel Pint, ESLint) bersih.
- Tidak ada fitur yang diklaim berfungsi tanpa diverifikasi.
  Jangan membuat seluruh aplikasi dalam satu respons dengan mengorbankan kualitas. Berhenti di akhir setiap tahap dan tunggu persetujuan saya.

## 18. Instruksi Awal

Mulai dari Tahap 1. Jangan menulis kode dulu. Buat:

- Ringkasan kebutuhan bisnis.
- Daftar fitur MVP dan fase 2.
- Arsitektur aplikasi dan struktur folder (backend per domain, frontend `resources/js/pages`, `components`, `types`).
- ERD dan relasi database awal (Mermaid).
- Daftar halaman beserta role yang boleh mengakses.
- Matriks hak akses role × aksi.
- Diagram status: tahap rekrutmen, attempt ujian, dan penilaian.
- Alur kerja HR dan kandidat.
- Risiko teknis dan keamanan beserta mitigasinya.
- Rencana implementasi bertahap.
  Default keputusan bisnis sudah ditetapkan dalam prompt ini. Ajukan pertanyaan hanya untuk keputusan yang benar-benar kritis dan belum tercakup; untuk keputusan teknis yang tidak kritis, pilih default yang sederhana, aman, dan mudah dipelihara, lalu sebutkan pilihannya.

Setelah Tahap 1 selesai, berhenti dan tunggu persetujuan saya sebelum melanjutkan.
