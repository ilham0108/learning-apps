# Recruitment LMS

Sistem untuk menyaring calon karyawan lewat **pembelajaran** dan **ujian**. HR menjalankan rekrutmen dari awal sampai keputusan akhir: mengelola kandidat, memberi materi belajar, membuat dan menugaskan ujian, menilai, mengevaluasi, lalu membuat laporan. Keputusan akhir tetap di tangan manusia; sistem menyajikan gambaran utuh per kandidat.

Dokumen terkait:

- [`docs/tahap-1.md`](docs/tahap-1.md): analisis kebutuhan, ERD, diagram status, matriks akses, dan risiko.
- [`rules.md`](rules.md): aturan bisnis dan aturan pengembangan yang menjadi acuan.

---

## Daftar isi

1. [Fitur](#fitur)
2. [Pengguna dan hak akses](#pengguna-dan-hak-akses)
3. [Teknologi](#teknologi)
4. [Instalasi](#instalasi)
5. [Menjalankan aplikasi](#menjalankan-aplikasi)
6. [Struktur kode](#struktur-kode)
7. [Konvensi pengembangan](#konvensi-pengembangan)
8. [Pengujian dan pemeriksaan kode](#pengujian-dan-pemeriksaan-kode)
9. [Impor bank soal dari Excel](#impor-bank-soal-dari-excel)
10. [Catatan deploy](#catatan-deploy)

---

## Fitur

| Area | Fitur |
| --- | --- |
| **Akses** | Login, lupa password, 2FA untuk staf, aktivasi akun kandidat lewat tautan (berlaku 72 jam, disalin HR), persetujuan pemrosesan data, kelola staf dan jabatan beserta hak aksesnya. |
| **Rekrutmen** | Master departemen dan posisi; rekrutmen (draft/open/closed) dengan tim HR; kandidat dan lamaran; alur tahap rekrutmen (default dan per rekrutmen) beserta riwayatnya; catatan internal; evaluasi dan rekomendasi. |
| **Data diri kandidat** | Data pribadi, riwayat pendidikan, pengalaman kerja, dan dokumen (disimpan di disk privat). |
| **Pembelajaran** | Program → modul → materi berurutan (artikel, PDF, gambar, video embed/unggah, tautan), wajib/opsional, penugasan program, progres per materi. |
| **Bank soal** | Kategori; 6 jenis soal (pilihan ganda, pilihan ganda kompleks, benar/salah, isian singkat, esai, studi kasus dengan sub-soal); cari dan filter; arsip; riwayat pemakaian; versioning copy-on-write; **impor dari Excel**; **hapus massal**. |
| **Ujian** | Konfigurasi lengkap (durasi, jadwal, navigasi bebas/maju saja, acak soal/pilihan, batas percobaan, kebijakan nilai tertinggi/terakhir, publikasi hasil), penugasan dengan jadwal per individu, duplikasi ujian. Ujian terkunci setelah ada pengerjaan. |
| **Pengerjaan ujian** | Snapshot soal saat mulai, autosave per jawaban, tandai ragu, timer dari server, pulih setelah refresh, submit idempotent, auto-submit oleh scheduler, log peristiwa. |
| **Penilaian** | Penilaian otomatis soal objektif, antrean penilaian manual (esai dan isian yang tidak cocok), nilai berbobot dan per kategori, publikasi hasil. |
| **Dashboard dan laporan** | Dashboard HR (dalam lingkup akses) dan dashboard kandidat; 8 laporan (kandidat, hasil ujian, nilai per kategori, progres belajar, tingkat kelulusan, materi belum selesai, belum mengerjakan, menunggu penilaian); ekspor CSV lewat queue. |
| **Audit** | Audit log untuk tindakan penting (perubahan nilai, tahap, hapus/arsip soal, ekspor, dsb.). |

## Pengguna dan hak akses

Ada tiga **role** (`users.role`):

| Role | Keterangan |
| --- | --- |
| `super_admin` | Memiliki semua hak. Dibuat oleh `ProductionSeeder` dari variabel `.env`. |
| `hr` | Staf perusahaan. Haknya ditentukan oleh **jabatan** (`job_roles`) yang dipegangnya. |
| `candidate` | Kandidat. Hanya melihat data miliknya sendiri. |

Hak staf diatur per jabatan di menu **Jabatan** (`/admin/job-roles`). Daftar hak ada di [`Permission.php`](app/Domain/Access/Enums/Permission.php). Tiga jabatan sistem (tidak bisa dihapus, tetapi haknya bisa diubah) dengan hak default:

| Jabatan | Hak default |
| --- | --- |
| **Head HR** | Semua hak HR Recruiter, ditambah melihat semua rekrutmen, mengatur tim HR per rekrutmen, alur tahap default, dan hak akses jabatan. |
| **HR Recruiter** | Mengelola rekrutmen yang ditugaskan: kandidat, data sensitif, tahap dan keputusan, catatan, evaluasi, materi, bank soal, ujian, penilaian, publikasi hasil, laporan dan ekspor. |
| **Interviewer** | Menulis catatan dan evaluasi untuk kandidat di rekrutmen yang ditugaskan. |

Prinsip akses:

- HR hanya melihat data dalam rekrutmen yang ditugaskan kepadanya, kecuali memiliki hak `campaigns.view_all`.
- Route admin memakai middleware `permission:<hak>`. Pengguna tanpa hak mendapat **404**, bukan 403, agar keberadaan halaman tidak terungkap.
- Kunci jawaban dan ID internal tidak pernah dikirim ke kandidat. Frontend hanya menerima `public_id` (ULID).

## Teknologi

| Komponen | Pilihan |
| --- | --- |
| Backend | PHP 8.3, Laravel 13, Laravel Fortify (autentikasi, 2FA) |
| Frontend | Inertia 3, React 19, TypeScript, Tailwind CSS 4, shadcn/ui, TipTap (editor teks) |
| Routing FE | Laravel Wayfinder (fungsi route bertipe di `resources/js/routes` dan `resources/js/actions`) |
| Database | MySQL. Kode tetap kompatibel dengan MySQL 5.7; produksi disarankan MySQL 8 / MariaDB 10.6+. |
| Queue, cache, session | Driver `database` |
| Spreadsheet | `openspout/openspout` (impor/template Excel bank soal) |
| Sanitasi HTML | `symfony/html-sanitizer` |
| Build dan lint FE | Vite+ (`vp build`, `vp check` = Oxlint + formatter, `vp test` = Vitest) |
| Kualitas backend | PHPUnit 12, Larastan (PHPStan), Laravel Pint |

## Instalasi

Kebutuhan: PHP 8.3 dengan ekstensi `zip`, `xml`, `xmlreader`, `gd`, `pdo_mysql`; Composer; Node.js 22+; MySQL.

```bash
git clone <url-repo> lms-react
cd lms-react

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Atur `.env`:

```dotenv
DB_DATABASE=recruitment_lms
DB_USERNAME=root
DB_PASSWORD=

# Super Admin pertama (dibuat oleh seeder jika keduanya terisi)
SUPER_ADMIN_NAME="Super Admin"
SUPER_ADMIN_EMAIL=admin@perusahaan.test
SUPER_ADMIN_PASSWORD=ganti-password-ini

# Semua waktu disimpan UTC, ditampilkan dalam zona ini
DISPLAY_TIMEZONE=Asia/Jakarta
```

Buat database lalu jalankan migrasi dan seeder:

```bash
php artisan migrate --seed
```

`DatabaseSeeder` selalu menjalankan `ProductionSeeder` (pengaturan sistem dan Super Admin). Jika `APP_ENV=local`, ia juga menjalankan `DemoSeeder`.

### Data demo (hanya `APP_ENV=local`)

`DemoSeeder` mengisi contoh posisi, rekrutmen, kandidat, program belajar, bank soal, dan ujian. Semua akun demo memakai password `password`:

| Akun | Email |
| --- | --- |
| HR Recruiter (Sales) | `hr.sales@demo.test` |
| HR Recruiter (Layanan) | `hr.layanan@demo.test` |
| Head HR | `head.hr@demo.test` |
| Interviewer | `interviewer@demo.test` |
| Kandidat | `kandidat@demo.test` |

Untuk database bersih **tanpa** data demo (menghapus semua data):

```bash
php artisan migrate:fresh
php artisan db:seed --class=ProductionSeeder
```

`DemoSeeder` menolak berjalan di produksi.

## Menjalankan aplikasi

Mode pengembangan menjalankan server PHP, queue, dan Vite sekaligus:

```bash
composer dev
```

Scheduler **tidak** ikut dijalankan oleh `composer dev`. Jalankan di terminal lain:

```bash
php artisan schedule:work
```

Jika ingin menjalankan semuanya secara terpisah:

```bash
php artisan serve          # aplikasi
npm run dev                # Vite (hot reload)
php artisan queue:work     # ekspor laporan
php artisan schedule:work  # tugas terjadwal (lihat di bawah)
```

Tugas terjadwal ([`routes/console.php`](routes/console.php)):

| Perintah | Jadwal | Fungsi |
| --- | --- | --- |
| `exams:close-expired` | tiap menit | Auto-submit pengerjaan ujian yang melewati batas waktu. |
| `exports:prune` | harian | Menghapus file ekspor laporan yang sudah lewat 7 hari. |

Route Wayfinder dibuat ulang otomatis oleh plugin Vite. Setelah menambah route atau controller tanpa Vite berjalan, jalankan:

```bash
php artisan wayfinder:generate --with-form
```

## Struktur kode

Aplikasi berbentuk **modular monolith**: logika bisnis dikelompokkan per domain di `app/Domain`.

```text
app/
├── Domain/
│   ├── Shared/        HasPublicId (ULID), sanitasi HTML, ActivityLogger, enum Role
│   ├── Access/        jabatan dan hak akses, aktivasi akun, persetujuan data
│   ├── Candidates/    departemen, posisi, rekrutmen, kandidat, lamaran, tahap, catatan, evaluasi
│   ├── Learning/      program, modul, materi, penugasan, progres
│   ├── QuestionBank/  kategori, soal, versioning, impor Excel
│   ├── Exams/         ujian, penugasan, attempt, snapshot, penilaian, publikasi hasil
│   └── Reporting/     dashboard, laporan, ekspor CSV
│       (tiap domain: Models/ Actions/ Policies/ Enums/ Queries/ ...)
├── Http/
│   ├── Controllers/Admin/       halaman staf (prefix /admin)
│   ├── Controllers/Candidate/   halaman kandidat
│   ├── Controllers/ExamRunner/  endpoint JSON pengerjaan ujian
│   ├── Requests/                validasi (Form Request)
│   └── Resources/               bentuk data ke frontend
└── Console/Commands/            perintah terjadwal
resources/js/
├── pages/            admin/, candidate/, auth/, settings/
├── components/       ui/ (shadcn), app/ (komponen bersama), <domain>/
├── i18n/id.ts        kamus teks antarmuka (helper t())
├── routes/, actions/ hasil Wayfinder (jangan diedit manual)
└── types/            tipe props dan resource
tests/Feature/<Domain>/   tes HTTP, otorisasi, dan aturan bisnis
docs/                     dokumen rancangan
```

Area URL:

| Prefix | Isi |
| --- | --- |
| `/admin/...` | Semua halaman staf: dashboard, rekrutmen, kandidat, alur tahap, program, bank soal, ujian, penilaian, laporan, staf, jabatan, audit log. |
| `/dashboard`, `/learn`, `/exams`, `/profile` | Halaman kandidat. |
| `/attempts/{attempt}/...` | Endpoint JSON pengerjaan ujian: `state`, `clock`, `answers`, `position`, `events`, `submit`, `result`. |
| `/activate/{user}/{token}`, `/consent` | Aktivasi akun dan persetujuan data kandidat. |
| `/settings/...` | Profil, password, dan 2FA. |

## Konvensi pengembangan

- **Controller tipis**: validasi lewat Form Request, otorisasi lewat Policy/middleware, lalu memanggil satu **Action** (mis. `SaveQuestion`, `StartAttempt`, `ChangeApplicationStage`). Transaction dan lock ada di Action.
- **ID publik**: model yang diakses frontend memakai trait `HasPublicId`. URL, props, dan JSON hanya memuat `public_id`, tidak pernah `id` internal.
- **Lingkup akses**: query yang dibatasi rekrutmen memakai scope yang sama dengan Policy, sehingga hanya ada satu sumber kebenaran.
- **Waktu**: disimpan dalam UTC dan dikonversi ke `DISPLAY_TIMEZONE` hanya saat ditampilkan.
- **HTML dari pengguna** selalu disanitasi di server (`HtmlSanitizer`). Pilihan jawaban disimpan sebagai teks biasa.
- **Tidak ada soft delete**. Data yang sudah dirujuk riwayat diarsipkan, bukan dihapus. Contoh: soal yang pernah dipakai ujian tidak bisa dihapus, hanya diarsipkan; soal yang dipakai ujian terkunci diubah lewat versi baru.
- **Teks antarmuka** ditulis di `resources/js/i18n/id.ts` dan dipanggil dengan `t('kunci')`; kunci yang salah tertangkap `tsc`. Pesan backend memakai `__()`.
- **Audit**: tindakan penting dicatat dengan `ActivityLogger`.
- **Toast**: controller mengirim `Inertia::flash('toast', ['type' => ..., 'message' => ...])`.

## Pengujian dan pemeriksaan kode

```bash
php artisan test          # seluruh tes PHPUnit
composer types:check      # PHPStan (Larastan)
composer lint             # format PHP dengan Pint
npm run types:check       # TypeScript
npm run check             # Oxlint + formatter frontend (check:fix untuk memperbaiki)
npm test                  # tes komponen (Vitest)
composer ci:check         # rangkaian lengkap seperti CI
```

Tes memakai database terpisah `recruitment_lms_testing` (lihat `phpunit.xml`), jadi data pengembangan tidak tersentuh. Buat database tersebut sekali sebelum menjalankan tes pertama kali.

## Impor bank soal dari Excel

Di halaman **Bank soal**, klik **Impor Excel → Unduh template**. Template berisi sheet *Soal* (dengan baris contoh), *Petunjuk*, dan *Kategori* (kategori yang tersedia).

| Kolom | Isi |
| --- | --- |
| `kategori` | Nama kategori yang **sudah ada** (tidak dibuat otomatis). |
| `jenis` | `pg`, `pg_kompleks`, `benar_salah`, `isian`, `esai`, `studi_kasus` |
| `kesulitan` | `mudah`, `sedang`, `sulit` |
| `pertanyaan` | Teks soal; baris kosong memisahkan paragraf. |
| `bobot` | 0,01–1000 (diabaikan untuk studi kasus). |
| `opsi_a` … `opsi_j` | Pilihan jawaban (template menyediakan A–E). |
| `kunci` | pg: `B` · pg_kompleks: `A,C` · benar_salah: `Benar`/`Salah` · isian: `Jakarta\|DKI Jakarta` |
| `kode`, `induk` | Studi kasus diberi `kode` (mis. `K1`); sub-soalnya mengisi `induk` = `K1`. |
| `topik`, `rubrik`, `penjelasan` | Opsional. |

Aturan impor:

- File `.xlsx`, maksimal 5 MB dan 1000 baris.
- **Semua atau tidak sama sekali**: satu baris salah membatalkan seluruh impor. Kesalahan ditampilkan per nomor baris Excel.
- Validasi sama dengan form tambah soal karena impor memakai `SaveQuestion`.

Kode terkait: [`ImportQuestions`](app/Domain/QuestionBank/Actions/ImportQuestions.php), [`QuestionSheet`](app/Domain/QuestionBank/Import/QuestionSheet.php), [`QuestionSheetReader`](app/Domain/QuestionBank/Import/QuestionSheetReader.php), [`QuestionTemplate`](app/Domain/QuestionBank/Import/QuestionTemplate.php).

## Catatan deploy

- Set `APP_ENV=production` dan `APP_DEBUG=false`. Data demo tidak akan diisi.
- Isi `SUPER_ADMIN_EMAIL` dan `SUPER_ADMIN_PASSWORD` sebelum menjalankan `php artisan db:seed --force`, lalu ganti password setelah login pertama.
- Jalankan `npm run build` dan `php artisan migrate --force`.
- Jalankan **queue worker** (`php artisan queue:work`) dengan supervisor.
- Daftarkan cron scheduler:

  ```cron
  * * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1
  ```

  Tanpa scheduler, ujian yang lewat waktu tidak ter-submit otomatis sampai kandidat membukanya lagi.
- File materi, dokumen kandidat, dan ekspor disimpan di disk privat (`storage/app/private`) dan hanya diunduh lewat controller yang memeriksa hak akses. Folder ini perlu ikut di-backup.
