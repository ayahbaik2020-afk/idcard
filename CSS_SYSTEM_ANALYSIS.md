# Analisis Sistem CSS - FTM Web

Dokumen ini mendefinisikan secara detail seluruh sistem CSS yang digunakan di aplikasi FTM Web, agar dapat diterapkan di aplikasi lain.

---

## 1. OVERVIEW ARSITEKTUR CSS

Aplikasi ini menggunakan **2 sistem desain utama** yang diterapkan pada halaman berbeda:

| Sistem | File yang Menggunakan | Karakteristik |
|--------|----------------------|---------------|
| **Light Dashboard** | `index.php`, `mesin.php` | Clean, Bootstrap 5, sidebar solid, gradient cards |
| **Dark Glassmorphism** | `hpi_nik.php`, `proses_download.php` | Modern dark, efek kaca (glass), backdrop blur, tema ungu |

Keduanya menggunakan **Bootstrap 5.3** sebagai foundation + **Font Awesome 6.4** untuk ikon.

---

## 2. SISTEM LIGHT DASHBOARD (`index.php`, `mesin.php`)

### 2.1 Foundation & Base
```css
body {
    background-color: #f4f6f9;           /* index.php */
    background-color: #f8f9fa;           /* mesin.php */
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
```

### 2.2 Sidebar System
```css
.sidebar {
    background: #2c3e50;                  /* Dark blue-gray solid */
    min-height: 100vh;
    color: #fff;
    width: 250px;
    flex-shrink: 0;
}

/* Link Items */
.sidebar a {
    color: #cfd8dc;                       /* Muted light blue-gray */
    text-decoration: none;
    padding: 15px;
    display: block;
    border-bottom: 1px solid #34495e;     /* Subtle divider */
    transition: 0.3s;
}

/* Hover & Active States */
.sidebar a:hover,
.sidebar a.active {
    background: #34495e;                  /* Slightly lighter */
    color: #fff;
    border-left: 4px solid #3498db;       /* Blue accent indicator */
}
```

**Pola Sidebar:**
- Background solid gelap (`#2c3e50`)
- Teks link redup (`#cfd8dc`)
- Hover: background sedikit terang + border-left biru (`#3498db`)
- Transition 0.3s untuk smoothness

### 2.3 Content Area
```css
.content {
    padding: 30px;
}
```

### 2.4 Gradient Card System (Stat Cards)

Sistem ini menggunakan **5 gradient preset** dengan arah `135deg`:

```css
.stat-card {
    border-radius: 12px;
    border: none;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    transition: transform 0.2s;
    position: relative;
    overflow: hidden;
}
.stat-card:hover {
    transform: translateY(-5px);          /* Lift effect on hover */
}

/* Gradient Presets */
.bg-gradient-primary { background: linear-gradient(135deg, #0d6efd, #0dcaf0); color: white; }
.bg-gradient-success { background: linear-gradient(135deg, #198754, #2dce89); color: white; }
.bg-gradient-warning { background: linear-gradient(135deg, #f39c12, #f1c40f); color: white; }
.bg-gradient-danger  { background: linear-gradient(135deg, #e74c3c, #ff7675); color: white; }
.bg-gradient-info    { background: linear-gradient(135deg, #8e44ad, #9b59b6); color: white; }
```

**Detail Gradient:**
| Class | Warna 1 | Warna 2 | Arah | Penggunaan |
|-------|---------|---------|------|------------|
| `.bg-gradient-primary` | `#0d6efd` | `#0dcaf0` | 135deg | Total Pegawai |
| `.bg-gradient-success` | `#198754` | `#2dce89` | 135deg | Log Hari Ini |
| `.bg-gradient-warning` | `#f39c12` | `#f1c40f` | 135deg | Total Mesin |
| `.bg-gradient-danger` | `#e74c3c` | `#ff7675` | 135deg | - |
| `.bg-gradient-info` | `#8e44ad` | `#9b59b6` | 135deg | NIK Khusus |

### 2.5 Decorative Icon Background
```css
.card-icon-bg {
    position: absolute;
    right: 15px;
    top: 15px;
    font-size: 4rem;
    opacity: 0.2;
    transform: rotate(-15deg);            /* Slight tilt */
}
```
- Ikon Font Awesome besar (4rem) dengan opacity 20%
- Diputar -15 derajat untuk efek dekoratif
- Posisi absolute di pojok kanan atas card

### 2.6 Table Styling
```css
.log-table th {
    background: #f8f9fa;
    font-weight: 600;
    font-size: 0.85rem;
    color: #6c757d;                       /* Bootstrap gray-600 */
    text-transform: uppercase;
}
.log-table td {
    font-size: 0.95rem;
    vertical-align: middle;
}
```

### 2.7 Modal Gradient Header
```css
.modal-header.bg-success {
    background: linear-gradient(45deg, #198754, #20c997) !important;
    color: white;
}
```
- Arah gradient: **45deg** (berbeda dari stat cards)
- Warna: hijau Bootstrap ke teal

---

## 3. SISTEM DARK GLASSMORPHISM (`hpi_nik.php`, `proses_download.php`)

### 3.1 Foundation & Base
```css
* { font-family: 'Inter', sans-serif; }   /* Google Font Inter */

body {
    background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
    min-height: 100vh;
}
```

**Detail Background Gradient:**
- Arah: `135deg`
- Warna 1: `#0f0c29` (very dark blue-purple)
- Warna 2: `#302b63` (medium purple)
- Warna 3: `#24243e` (dark slate)
- Efek: Deep space / dark mode premium

### 3.2 Glass Sidebar
```css
.sidebar {
    background: rgba(255,255,255,0.05);   /* Very subtle white tint */
    backdrop-filter: blur(20px);           /* Glass blur effect */
    border-right: 1px solid rgba(255,255,255,0.1);
    min-height: 100vh;
    width: 250px;
    flex-shrink: 0;
}

/* Brand Section */
.sidebar .brand {
    padding: 24px 16px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.sidebar .brand i {
    font-size: 2.5rem;
    color: #a78bfa;                        /* Light purple accent */
}
.sidebar .brand span {
    display: block;
    color: #fff;
    font-weight: 700;
    font-size: 1.1rem;
    margin-top: 8px;
}

/* Navigation Links */
.sidebar a {
    display: flex;
    align-items: center;
    gap: 10px;
    color: rgba(255,255,255,0.6);         /* Muted white */
    text-decoration: none;
    padding: 13px 20px;
    border-left: 3px solid transparent;    /* Invisible border default */
    transition: all .2s;
    font-size: .92rem;
}

/* Hover & Active */
.sidebar a:hover,
.sidebar a.active {
    color: #fff;
    background: rgba(167,139,250,0.15);   /* Purple tint background */
    border-left-color: #a78bfa;            /* Purple accent line */
}
```

**Perbedaan Sidebar Light vs Glass:**
| Aspek | Light | Glass |
|-------|-------|-------|
| Background | Solid `#2c3e50` | `rgba(255,255,255,0.05)` + blur |
| Border | `1px solid #34495e` | `1px solid rgba(255,255,255,0.1)` |
| Active Indicator | `#3498db` (blue) | `#a78bfa` (purple) |
| Active BG | `#34495e` | `rgba(167,139,250,0.15)` |

### 3.3 Glass Card System (`.card-glass`)
```css
.card-glass {
    background: rgba(255,255,255,0.07);    /* Subtle white tint */
    backdrop-filter: blur(20px);            /* Frosted glass */
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 16px;                    /* Large rounded corners */
    overflow: hidden;
}

.card-glass .card-header {
    background: rgba(255,255,255,0.05);    /* Slightly darker header */
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding: 16px 20px;
}
.card-glass .card-header h5 {
    color: #e2e8f0;                        /* Light gray-blue text */
    margin: 0;
    font-weight: 600;
    font-size: .95rem;
}
.card-glass .card-body {
    padding: 20px;
}
```

**Formula Glass Card:**
1. Background: `rgba(255,255,255,0.05 - 0.07)` (5-7% white opacity)
2. Backdrop filter: `blur(20px)`
3. Border: `1px solid rgba(255,255,255,0.10 - 0.15)`
4. Border radius: `16px`
5. Header slightly darker than body

### 3.4 Form Controls (Dark Theme)
```css
.form-label {
    color: rgba(255,255,255,0.7);
    font-size: .85rem;
    font-weight: 500;
    margin-bottom: 6px;
}

.form-control,
.form-select {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    color: #fff;
    border-radius: 10px;
    padding: 10px 14px;
}

.form-control:focus,
.form-select:focus {
    background: rgba(255,255,255,0.12);
    border-color: #a78bfa;                 /* Purple focus */
    color: #fff;
    box-shadow: 0 0 0 3px rgba(167,139,250,0.2);
}

.form-control::placeholder {
    color: rgba(255,255,255,0.3);
}
```

**Pola Form Dark:**
- Background input: 8% white opacity
- Border: 15% white opacity
- Focus: border ungu + glow ungu lembut
- Placeholder: 30% white opacity

### 3.5 Purple Gradient Button System
```css
.btn-purple {
    background: linear-gradient(135deg, #7c3aed, #a78bfa);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 10px 20px;
    font-weight: 600;
    transition: all .2s;
}
.btn-purple:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 20px rgba(124,58,237,0.4);
    color: #fff;
}
```

**Detail Gradient Button:**
- Warna 1: `#7c3aed` (deep purple)
- Warna 2: `#a78bfa` (light purple)
- Arah: `135deg`
- Hover: lift + shadow ungu

### 3.6 Outline Button (Dark)
```css
.btn-outline-light-custom {
    border: 1px solid rgba(255,255,255,0.2);
    color: rgba(255,255,255,0.7);
    border-radius: 10px;
    padding: 10px 20px;
    background: transparent;
    transition: all .2s;
}
.btn-outline-light-custom:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
}
```

### 3.7 Badge System
```css
.badge-nik {
    background: linear-gradient(135deg, #7c3aed, #a78bfa);
    color: #fff;
    padding: 4px 12px;
    border-radius: 20px;                   /* Pill shape */
    font-size: .78rem;
    font-weight: 600;
    font-family: 'Courier New', monospace;
    letter-spacing: 1px;
}

.count-badge {
    background: linear-gradient(135deg, #7c3aed, #a78bfa);
    color: #fff;
    border-radius: 20px;
    padding: 3px 12px;
    font-size: .78rem;
    font-weight: 700;
}
```

### 3.8 Custom Dark Table
```css
.table-dark-custom {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 6px;                 /* Row gap */
}

.table-dark-custom thead th {
    color: rgba(255,255,255,0.4);
    font-size: .75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 8px 14px;
    border: none;
}

.table-dark-custom tbody tr {
    background: rgba(255,255,255,0.05);
    transition: all .2s;
}
.table-dark-custom tbody tr:hover {
    background: rgba(167,139,250,0.1);     /* Purple tint on hover */
}

.table-dark-custom tbody td {
    color: #e2e8f0;
    padding: 12px 14px;
    border: none;
    vertical-align: middle;
}

/* Rounded row corners */
.table-dark-custom tbody td:first-child { border-radius: 10px 0 0 10px; }
.table-dark-custom tbody td:last-child { border-radius: 0 10px 10px 0; }
```

**Pola Table Dark:**
- Border spacing antar baris: 6px
- Setiap baris: background 5% white
- Hover: background ungu 10% opacity
- Sudut baris rounded (10px)

### 3.9 Icon Buttons
```css
.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .8rem;
    cursor: pointer;
    transition: all .2s;
}

.btn-icon-edit {
    background: rgba(96,165,250,0.15);     /* Blue tint */
    color: #60a5fa;                        /* Blue icon */
}
.btn-icon-edit:hover {
    background: rgba(96,165,250,0.3);
}

.btn-icon-del {
    background: rgba(248,113,113,0.15);    /* Red tint */
    color: #f87171;                        /* Red icon */
}
.btn-icon-del:hover {
    background: rgba(248,113,113,0.3);
}
```

### 3.10 Info Box
```css
.info-box {
    background: rgba(167,139,250,0.1);     /* Purple tint bg */
    border: 1px solid rgba(167,139,250,0.3);
    border-radius: 12px;
    padding: 14px 18px;
    color: rgba(255,255,255,0.7);
    font-size: .85rem;
    margin-bottom: 20px;
}
.info-box i {
    color: #a78bfa;
    margin-right: 8px;
}
```

### 3.11 Glass Modal
```css
.modal-glass .modal-content {
    background: #1e1a3c;                   /* Dark purple-blue solid */
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 16px;
    color: #fff;
}
.modal-glass .modal-header {
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.modal-glass .modal-footer {
    border-top: 1px solid rgba(255,255,255,0.1);
}
```

### 3.12 Search Box
```css
.search-box {
    position: relative;
}
.search-box i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255,255,255,0.3);
    font-size: .9rem;
}
.search-box input {
    padding-left: 40px;                    /* Space for icon */
}
```

### 3.13 Custom Toast Notification
```css
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
}

.toast-custom {
    background: rgba(30,26,60,0.95);
    border: 1px solid rgba(255,255,255,0.15);
    backdrop-filter: blur(20px);
    color: #fff;
    border-radius: 12px;
    padding: 14px 20px;
    min-width: 300px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.4);
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideIn .3s ease;
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to   { transform: translateX(0); opacity: 1; }
}

.toast-custom.success i { color: #34d399; }   /* Green */
.toast-custom.error i   { color: #f87171; }   /* Red */
```

### 3.14 Terminal / Log Console (proses_download.php)
```css
.log-terminal {
    background: #0d0d1a;                   /* Very dark bg */
    border-radius: 12px;
    padding: 16px;
    font-family: 'Courier New', monospace;
    font-size: .82rem;
    min-height: 280px;
    max-height: 420px;
    overflow-y: auto;
    border: 1px solid rgba(255,255,255,0.08);
}

.log-terminal .log-line {
    margin: 3px 0;
    display: flex;
    gap: 8px;
    align-items: flex-start;
}
.log-terminal .log-time { color: rgba(255,255,255,0.2); font-size: .75rem; flex-shrink: 0; }
.log-terminal .log-icon { flex-shrink: 0; }
.log-terminal .log-msg  { flex: 1; line-height: 1.4; }

/* Log Level Colors */
.log-terminal .log-success { color: #34d399; }   /* Emerald green */
.log-terminal .log-info    { color: #60a5fa; }   /* Blue */
.log-terminal .log-warning { color: #fbbf24; }   /* Amber */
.log-terminal .log-danger  { color: #f87171; }   /* Red */
```

### 3.15 File Chip
```css
.file-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 6px 12px;
    font-size: .82rem;
    color: #e2e8f0;
    font-family: monospace;
    margin: 4px;
}
.file-chip i { color: #a78bfa; }
```

### 3.16 Custom Scrollbar (Dark)
```css
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: rgba(255,255,255,0.03); }
::-webkit-scrollbar-thumb { background: rgba(167,139,250,0.3); border-radius: 100px; }
```

### 3.17 Main Action Button (proses_download.php)
```css
.btn-main {
    background: linear-gradient(135deg, #7c3aed, #a78bfa);
    color: #fff;
    border: none;
    border-radius: 12px;
    padding: 13px 24px;
    font-weight: 700;
    font-size: 1rem;
    width: 100%;
    cursor: pointer;
    transition: all .2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}
.btn-main:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(124,58,237,0.5);
}
.btn-main:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}
```

---

## 4. PALET WARNA UTAMA

### Light Theme
| Nama | Hex | Penggunaan |
|------|-----|------------|
| Primary Blue | `#0d6efd` | Gradient primary |
| Cyan | `#0dcaf0` | Gradient primary end |
| Success Green | `#198754` | Gradient success |
| Teal | `#20c997` | Modal header, gradient success end |
| Warning Orange | `#f39c12` | Gradient warning |
| Yellow | `#f1c40f` | Gradient warning end |
| Danger Red | `#e74c3c` | Gradient danger |
| Light Red | `#ff7675` | Gradient danger end |
| Purple | `#8e44ad` | Gradient info |
| Light Purple | `#9b59b6` | Gradient info end |
| Sidebar BG | `#2c3e50` | Sidebar background |
| Sidebar Hover | `#34495e` | Sidebar hover/active bg |
| Sidebar Text | `#cfd8dc` | Sidebar link text |
| Accent Blue | `#3498db` | Active indicator |

### Dark Glass Theme
| Nama | Hex | Penggunaan |
|------|-----|------------|
| Deep Purple | `#7c3aed` | Gradient button start |
| Light Purple | `#a78bfa` | Gradient button end, accent |
| Emerald | `#34d399` | Success state |
| Blue | `#60a5fa` | Info state, edit button |
| Red | `#f87171` | Danger state, delete button |
| Amber | `#fbbf24` | Warning state |
| Body Text | `#e2e8f0` | Primary text on dark |
| Muted Text | `rgba(255,255,255,0.5-0.7)` | Secondary text |
| Glass BG | `rgba(255,255,255,0.05-0.07)` | Card backgrounds |
| Glass Border | `rgba(255,255,255,0.10-0.15)` | Borders |
| Modal BG | `#1e1a3c` | Modal background |
| Terminal BG | `#0d0d1a` | Console background |

---

## 5. SISTEM TIPOGRAFI

| Elemen | Font | Size | Weight | Color |
|--------|------|------|--------|-------|
| Body (Light) | Segoe UI | default | 400 | default |
| Body (Dark) | Inter | default | 400 | `#fff` / `#e2e8f0` |
| Page Title (Dark) | Inter | 1.5-1.6rem | 700 | `#fff` |
| Page Subtitle (Dark) | Inter | 0.88-0.9rem | 400 | `rgba(255,255,255,0.4-0.5)` |
| Card Header | Inter | 0.92-0.95rem | 600 | `#e2e8f0` |
| Form Label | Inter | 0.85rem | 500 | `rgba(255,255,255,0.7)` |
| Table Header | Inter | 0.75rem | 600 | `rgba(255,255,255,0.4)` |
| Table Body | Inter | default | 400 | `#e2e8f0` |
| Badge/Chip | Inter / Courier | 0.78rem | 600-700 | `#fff` |
| Terminal | Courier New | 0.82rem | 400 | varies |

---

## 6. SISTEM SPACING & SIZING

### Border Radius Scale
| Token | Value | Penggunaan |
|-------|-------|------------|
| Small | 8px | Icon buttons, small chips |
| Medium | 10px | Form inputs, buttons |
| Large | 12px | Cards (light), terminal |
| XL | 16px | Glass cards, modals |
| Pill | 20px | Badges |

### Shadow Scale
| Token | Value | Penggunaan |
|-------|-------|------------|
| Card Light | `0 4px 6px rgba(0,0,0,0.05)` | Stat cards |
| Card Hover | `0 8px 20px rgba(124,58,237,0.4)` | Purple button hover |
| Card Hover 2 | `0 10px 30px rgba(124,58,237,0.5)` | Main button hover |
| Toast | `0 8px 32px rgba(0,0,0,0.4)` | Toast notification |

---

## 7. SISTEM TRANISI & ANIMATION

```css
/* Standard Transition */
transition: all .2s;          /* Buttons, links, cards */
transition: 0.3s;             /* Sidebar links (light) */

/* Transform Effects */
transform: translateY(-1px);  /* Subtle lift */
transform: translateY(-2px);  /* Stronger lift */
transform: translateY(-5px);  /* Card hover lift */
transform: rotate(-15deg);    /* Decorative icon */

/* Keyframes */
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to   { transform: translateX(0); opacity: 1; }
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
```

---

## 8. DEPENDENSI EKSTERNAL

```html
<!-- Bootstrap 5.3 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Font Awesome 6.4 -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<!-- Google Fonts: Inter (untuk dark theme) -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
```

---

## 9. CARA MENERAPKAN DI APLIKASI LAIN

### Untuk Tema Light Dashboard:
1. Include Bootstrap 5 + Font Awesome
2. Copy base styles (body, sidebar, content)
3. Pilih gradient preset yang diinginkan
4. Gunakan `.stat-card` untuk kartu statistik
5. Tambahkan `.card-icon-bg` untuk dekorasi ikon

### Untuk Tema Dark Glassmorphism:
1. Include Bootstrap 5 + Font Awesome + Google Font Inter
2. Set body background ke gradient ungu-gelap
3. Gunakan `.card-glass` untuk semua card
4. Gunakan `.sidebar` dengan backdrop-filter
5. Terapkan `.form-control` custom untuk input dark
6. Gunakan `.btn-purple` untuk CTA utama
7. Terapkan `.table-dark-custom` untuk tabel
8. Tambahkan custom scrollbar

---

*Dokumen ini mencakup 100% sistem CSS yang digunakan di aplikasi FTM Web.*

