# PLAN.md — Web Tarakan Art Class

> Ringkasan eksekusi dari PRD (v1.0, Juni 2026) untuk dieksekusi menggunakan **Laravel** (Blade/Livewire/Alpine.js + bootstrap).
> Stack disarankan: Laravel + MySQL/MariaDB, Livewire untuk komponen dinamis (dashboard, scheduler, payment), Alpine.js untuk interaksi ringan di frontend.

---

## 1. USER STORY & USE CASE

| ID | Role | User Story | Priority |
|----|------|------------|----------|
| US-01 | Super Admin | Melihat seluruh data bisnis agar dapat memantau performa Tarakan Art Class | **High** |
| US-02 | Super Admin | Mengelola akun Admin agar dapat mengatur akses sistem | **High** |
| US-03 | Super Admin | Melihat laporan keuangan agar dapat mengetahui kondisi bisnis | **High** |
| US-04 | Super Admin | Melihat analitik kelas terlaris agar dapat mengambil keputusan bisnis | Medium |
| US-05 | Admin | Menambahkan data murid baru agar data tersimpan secara digital | **High** |
| US-06 | Admin | Memperbarui data murid agar informasi selalu akurat | **High** |
| US-07 | Admin | Mengelola jadwal kelas agar kegiatan belajar berjalan sesuai rencana | **High** |
| US-08 | Admin | Mencatat pembayaran murid agar status pembayaran dapat dipantau | **High** |
| US-09 | Admin | Mengelola replacement class agar perubahan jadwal terdokumentasi | **High** |
| US-10 | Admin | Membuat raport siswa agar perkembangan siswa dapat dilihat orang tua | Medium |
| US-11 | Admin | Mengelola inventory agar stok perlengkapan dapat dipantau | Medium |
| US-12 | Admin | Melihat dashboard operasional agar mengetahui kondisi kelas & murid real-time | **High** |

**Use Case turunan (implisit dari role & flow):**
- Login/Logout & manajemen sesi (Authentication) — role: Super Admin & Admin
- Approve/Reject request replacement class (Scheduler) — role: Admin
- Generate & unduh laporan (Excel/PDF) — role: Super Admin, Admin
- Akses raport murid via credential key — role: Orang tua (guest, tanpa login sistem)
- CRUD akun user (Super Admin only) — role-based access control

---

## 2. FEATURES & REQUIREMENTS

| ID | Feature | Deskripsi | Priority | Acceptance Criteria |
|----|---------|-----------|----------|----------------------|
| F1 | Dashboard Analytics | Total murid, murid aktif/nonaktif, pendapatan, grafik pertumbuhan murid | **Must** | Data ter-update maks. 5 detik setelah perubahan |
| F2 | Student Management | CRUD data murid dan orang tua | **Must** | Data tersimpan & dapat dicari berdasarkan nama |
| F3 | Class Management | Kelola kelas, tutor, kapasitas, jadwal | **Must** | Admin dapat tambah/ubah/hapus data kelas |
| F4 | Scheduler | Kelola replacement class & perubahan jadwal | **Must** | Perubahan jadwal tersimpan & muncul di kalender |
| F5 | Attendance Tracking | Catat kehadiran murid per kelas | **Must** | Data absensi tersimpan & muncul di laporan |
| F6 | Payment Management | Catat pembayaran & status tagihan murid | **Must** | Status pembayaran otomatis berubah setelah transaksi disimpan |
| F7 | Financial Tracking | Catat pemasukan & pengeluaran operasional | **Must** | Total pemasukan/pengeluaran terhitung otomatis |
| F13 | User Management | Kelola akun Super Admin & Admin | **Must** | Super Admin dapat membuat/ubah/nonaktifkan akun |
| F14 | Role-Based Access | Pembatasan akses berdasarkan role | **Must** | Admin tidak dapat akses menu User Management |
| F8 | Student Report | Membuat & menyimpan raport perkembangan siswa | Should | Raport dapat dibuat, diperbarui, diakses kembali |
| F9 | Guest Report Access | Orang tua melihat raport via credential key | Should | Hanya credential valid yang dapat membuka raport |
| F10 | Inventory Management | Kelola stok barang & penjualan perlengkapan | Should | Stock in/out otomatis update stok tersedia |
| F11 | Analytics Dashboard | Kelas terlaris, revenue, pertumbuhan murid | Should | Grafik & scorecard sesuai data transaksi |
| F12 | Export Report | Export data murid/pembayaran/laporan ke Excel/PDF | Should | File terunduh sesuai format template |
| F15 | Activity Log | Catat aktivitas penting pengguna | Could | Aktivitas CRUD tercatat dengan user & timestamp |

### Catatan Payment Gateway (In Scope tapi belum ada Feature ID eksplisit)
PRD Scope of Work menyebutkan **Payment Gateway (QRIS, VA, Midtrans, Xendit)** sebagai in-scope namun tidak dirinci di tabel Features & Requirements. Perlu klarifikasi ke Product Manager apakah masuk Phase 1 (MVP) atau Phase 2, karena berdampak pada F6 (Payment Management) dan F7 (Financial Tracking).

### Out of Scope (jangan dikerjakan)
Mobile App, Parent Portal, Pendaftaran Online Publik, WhatsApp Notification/Reminder, Learning Management System, AI Analytics & Prediction, Integrasi Accounting (Accurate/Jurnal/ERP).

---

## 2.1 MATRIKS TUGAS SUPER ADMIN vs ADMIN PER FITUR

Berdasarkan PRD, Super Admin = **Full Access**, Admin = **Monitoring, Analytics, Report, Input**. Berikut breakdown per fitur supaya jelas untuk desain Policy/Gate di Laravel:

| Fitur | Super Admin | Admin |
|---|---|---|
| **F1 – Dashboard Analytics** | Lihat seluruh data dashboard (semua kelas, semua tutor, seluruh cabang jika ada) | Lihat dashboard operasional (kondisi kelas & murid real-time) — read only |
| **F2 – Student Management** | Full CRUD data murid & orang tua, termasuk hapus permanen | Tambah & update data murid (Input); tidak bisa hapus permanen (soft delete/nonaktifkan saja) |
| **F3 – Class Management** | Full CRUD kelas, tutor, kapasitas, jadwal | Bisa lihat & (opsional) update jadwal kelas sesuai kebutuhan operasional harian |
| **F4 – Scheduler** | Approve/reject request replacement class, monitoring semua perubahan jadwal | Input request replacement class, update jadwal, ajukan perubahan (status default Pending) |
| **F5 – Attendance Tracking** | Monitoring rekap kehadiran seluruh kelas | Input absensi murid per kelas (tugas harian utama Admin) |
| **F6 – Payment Management** | Monitoring seluruh transaksi, bisa koreksi/void pembayaran jika perlu | Input & catat pembayaran murid, update status Paid/Unpaid |
| **F7 – Financial Tracking** | Full akses laporan keuangan (income & expense), analisis kondisi bisnis | Input data pengeluaran/pemasukan operasional harian |
| **F8 – Student Report** | Monitoring seluruh raport yang dibuat Admin | Membuat & memperbarui raport perkembangan siswa (tugas utama) |
| **F9 – Guest Report Access** | Mengatur/generate credential key (jika perlu reset) | Generate credential key saat raport selesai dibuat, share ke orang tua |
| **F10 – Inventory Management** | Monitoring stok & penjualan perlengkapan | Input stock in/out, catat penjualan perlengkapan |
| **F11 – Analytics Dashboard** | Full akses (kelas terlaris, revenue, growth) untuk pengambilan keputusan bisnis | Lihat analytics sebagai referensi operasional (read only) |
| **F12 – Export Report** | Export semua jenis laporan (murid, pembayaran, keuangan) | Export laporan operasional (murid, absensi, pembayaran) sesuai kebutuhan tugas |
| **F13 – User Management** | **Khusus Super Admin** — buat, ubah, nonaktifkan akun Admin | Tidak ada akses (sesuai F14 Role-Based Access) |
| **F14 – Role-Based Access** | Mengatur/mendefinisikan akses per role | Terikat pembatasan akses (tidak bisa akses menu di luar scope-nya) |
| **F15 – Activity Log** | Full akses lihat log aktivitas seluruh user | Log aktivitasnya sendiri tercatat, tapi tidak bisa akses menu Activity Log |

### Ringkasan pembagian peran

**Super Admin (Full Access / Owner-level):**
- Approval & keputusan strategis (approve replacement class, koreksi pembayaran)
- Kelola akun Admin (buat/nonaktifkan)
- Akses penuh laporan keuangan & analytics untuk pengambilan keputusan bisnis
- Monitoring seluruh aktivitas sistem (activity log)
- Tidak melakukan input data harian — sifatnya oversight/kontrol

**Admin (Operasional harian — Monitoring, Analytics, Report, Input):**
- Input data harian: murid baru, absensi, pembayaran, replacement class request, pengeluaran/pemasukan
- Membuat & update raport siswa, generate credential key untuk orang tua
- Kelola inventory (stock in/out)
- Export laporan operasional
- Lihat dashboard & analytics sebagai referensi kerja, tapi tanpa akses ke User Management

> **Catatan:** PRD tidak merinci secara eksplisit batas Admin di beberapa fitur (misal apakah Admin boleh hapus data murid, atau hanya nonaktifkan). Disarankan dikonfirmasi ke Product Manager sebelum desain Policy/Gate final, terutama untuk aksi **delete/void** yang sifatnya destruktif.

---

## 3. PRIORITAS EKSEKUSI (Roadmap Development)

### 🔴 Phase 1 — MVP (Must Have)
Modul inti yang wajib jalan lebih dulu, sebagai fondasi sistem:

1. **Authentication & Role-Based Access** (Login, Super Admin/Admin, Role Master) — fondasi semua modul lain
2. **User Management** (F13, F14) — CRUD akun Super Admin/Admin
3. **Student Management** (F2) — Master data murid & orang tua
4. **Master Data Class** (F3) — Kelola kelas, tutor, kapasitas, jadwal
5. **Scheduler** (F4) — Replacement class & perubahan jadwal
6. **Attendance Tracking** (F5) — Absensi murid per kelas
7. **Payment Management** (F6) — Pencatatan pembayaran & status tagihan
8. **Financial Tracking** (F7) — Pemasukan/pengeluaran operasional
9. **Dashboard Analytics** (F1) — Ringkasan bisnis real-time

> Urutan build disarankan: Auth → User Mgmt → Student Mgmt → Class Mgmt → Scheduler → Attendance → Payment → Financial → Dashboard (dashboard butuh data dari semua modul sebelumnya).

### 🟡 Phase 2 — Should Have
Dikerjakan setelah MVP stabil:

10. **Student Report** (F8) — Raport perkembangan siswa
11. **Guest Report Access** (F9) — Akses raport via credential key (tanpa login sistem)
12. **Inventory Management** (F10) — Stok barang & penjualan perlengkapan
13. **Analytics Dashboard** (F11) — Kelas terlaris, revenue, growth chart
14. **Export Report** (F12) — Export Excel/PDF

### 🟢 Phase 3 — Could Have
15. **Activity Log** (F15) — Audit trail aktivitas user

---

## 3.1 URUTAN PENGERJAAN (Sequential Execution Order)

Urutan ini disusun berdasarkan **dependency antar modul** — modul di bawah butuh data/struktur dari modul di atasnya. Kerjakan dari atas ke bawah, jangan loncat.

| Urutan | Fitur | Kenapa harus di posisi ini |
|---|---|---|
| **1** | **Authentication & Role Management** (Login, User Master, Role Master) | Fondasi paling dasar. Tanpa ini, tidak ada middleware/guard untuk modul lain. |
| **2** | **User Management** (F13, F14) | Turunan langsung dari Auth — Super Admin butuh CRUD akun Admin sebelum modul lain dites multi-role. |
| **3** | **Student Management** (F2) | Master data utama, jadi dependency untuk hampir semua modul: Payment, Attendance, Scheduler, Report. |
| **4** | **Class Management** (F3) — termasuk Master Tutor | Dependency untuk Scheduler, Attendance, Dashboard. Harus ada sebelum murid bisa "masuk kelas". |
| **5** | **Scheduler** (F4) | Butuh Student + Class sudah ada. Termasuk fitur replacement class. |
| **6** | **Attendance Tracking** (F5) | Butuh Student + Class + Scheduler (jadwal jadi acuan absensi). |
| **7** | **Payment Management** (F6) | Butuh Student + Class (untuk hitung fee). Invoice auto-generate. |
| **8** | **Financial Tracking** (F7) | Butuh Payment sebagai sumber data pemasukan; pengeluaran bisa manual input. |
| **9** | **Dashboard Analytics** (F1) | Terakhir di Phase 1 karena butuh agregasi data dari SEMUA modul di atas (murid, kelas, absensi, payment, financial). |
| **10** | **Student Report** (F8) | Butuh Student + Attendance sebagai data dasar raport. |
| **11** | **Guest Report Access** (F9) | Turunan langsung dari Student Report — butuh raport sudah bisa dibuat dulu. |
| **12** | **Inventory Management** (F10) | Berdiri sendiri (tidak bergantung modul lain), tapi Should-priority jadi dikerjakan setelah semua Must selesai. |
| **13** | **Analytics Dashboard** (F11) | Lanjutan dari Dashboard (F1), butuh data historis lebih matang untuk grafik tren/ranking. |
| **14** | **Export Report** (F12) | Butuh semua modul data sudah stabil (Student, Payment, Attendance) sebagai sumber export. |
| **15** | **Activity Log** (F15) | Paling akhir — sifatnya menempel/observer ke semua modul yang sudah jadi, jadi lebih aman dipasang setelah struktur modul lain stabil. |

### Ringkasan per Sprint (kalau mau dipecah per 1-2 minggu)

- **Sprint 1:** Auth + User Management + Student Management
- **Sprint 2:** Class Management + Scheduler
- **Sprint 3:** Attendance + Payment Management
- **Sprint 4:** Financial Tracking + Dashboard Analytics (F1) → **MVP selesai, siap testing/demo ke client**
- **Sprint 5:** Student Report + Guest Report Access
- **Sprint 6:** Inventory + Analytics Dashboard (F11) + Export Report
- **Sprint 7:** Activity Log + polishing/testing keseluruhan

---

## 4. MAPPING KE STRUKTUR LARAVEL (saran teknis)

| Modul PRD | Model utama | Relasi kunci | Catatan implementasi |
|---|---|---|---|
| Authentication | `User` | `role` (enum/relasi ke `Role`) | Gunakan Laravel Breeze/Fortify + middleware role |
| Student Management | `Student` | `belongsTo Parent/Guardian`, `belongsTo ClassRoom` | Auto-generate `Student ID` (STD001) via observer/boot |
| Class Management | `ClassRoom`, `Tutor` | `hasMany Student`, `belongsTo Tutor` | Auto-generate Class Code (CLS001) |
| Scheduler | `ClassSchedule`, `ReplacementRequest` | `belongsTo Student`, `belongsTo ClassRoom` | Status: Pending/Approved/Rejected (enum) |
| Attendance | `Attendance` | `belongsTo Student`, `belongsTo ClassSchedule` | Rekap harian → agregasi untuk dashboard |
| Payment | `Payment`, `Invoice` | `belongsTo Student` | Auto-generate Invoice Number (INV001); status Paid/Unpaid |
| Financial Tracking | `Transaction` (income/expense) | — | Untuk profit/loss report |
| Dashboard/Analytics | (query aggregat, bukan model baru) | — | Gunakan Livewire component + cache untuk performa <3 detik |
| Student Report | `StudentReport` | `belongsTo Student` | `Credential Key` auto-generate (TAC-2026-001), akses guest tanpa auth Laravel biasa (route khusus + token) |
| Inventory | `InventoryItem`, `StockMovement` | — | Stock in/out → hitung remaining stock otomatis (accessor/event) |
| User Management | `User` | `belongsTo Role` | Hanya Super Admin yang boleh akses (policy/gate) |
| Activity Log | `ActivityLog` (bisa pakai package `spatie/laravel-activitylog`) | polymorphic ke semua model | Phase 3 |

---

## 5. NON-FUNCTIONAL REQUIREMENTS (perlu diperhatikan sejak awal build)

| Kategori | Requirement | Implikasi teknis Laravel |
|---|---|---|
| Performance | < 3 detik page load | Eager loading, query caching, index database |
| Security | Role-based Access | Laravel Policy/Gate + middleware per route group |
| Availability | 99% uptime | Di luar scope kode, tapi perlu strategi hosting/queue worker |
| Backup | Daily Backup | `spatie/laravel-backup` + scheduler (`app/Console/Kernel.php`) |
| Responsive | Desktop, Tablet, Mobile | Bootsrap responsive utility, uji di breakpoint utama |

---

## 6. Hal yang perlu diklarifikasi sebelum coding
- Detail integrasi **Payment Gateway** (QRIS/VA/Midtrans/Xendit) — masuk Phase 1 atau 2?
- Apakah **Guest Report Access** butuh sistem login terpisah (magic link/credential key) atau cukup form input key sederhana tanpa autentikasi Laravel standar?
- Format template Export Report (Excel/PDF) — apakah sudah ada contoh dari client?
- Definisi role "Admin: Monitoring, Analytics, Report, Input" — perlu breakdown permission per modul lebih detail untuk desain Policy/Gate.
