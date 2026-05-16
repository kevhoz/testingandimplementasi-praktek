# Praktek 9 — End-to-End Testing dengan Playwright

## Tujuan Pembelajaran

Setelah menyelesaikan praktek ini, mahasiswa mampu:
- Membuat aplikasi web sederhana sebagai target pengujian
- Menjalankan server lokal dan memverifikasi aplikasi bekerja sebelum ditulis tesnya
- Menginstal dan mengkonfigurasi **Playwright** untuk E2E testing
- Menulis test yang mensimulasikan interaksi pengguna: buka halaman, isi form, klik tombol, verifikasi hasil
- Memahami perbedaan auto-wait Playwright vs explicit wait Selenium (Praktek 8)

---

## Latar Belakang

### Apa itu End-to-End Testing?

E2E testing menguji aplikasi **dari sudut pandang pengguna**. Browser dibuka secara otomatis, tombol diklik, form diisi, dan hasilnya diverifikasi — persis seperti yang dilakukan pengguna nyata.

Contoh skenario E2E:
> *"Pengguna membuka halaman todo, mengetik 'Beli susu', menekan tombol Tambah, dan melihat item baru muncul di daftar."*

### Testing Pyramid

```
        ┌───────────┐
        │   E2E     │  ← Sedikit, lambat, tapi paling realistis
        ├───────────┤
        │    API    │  ← Sedang, menguji kontrak endpoint
        ├───────────┤
        │   Unit    │  ← Banyak, cepat, terisolasi
        └───────────┘
```

- **Unit test** membentuk fondasi — jumlah terbanyak, paling cepat
- **E2E test** berada di puncak — jumlah paling sedikit, paling lambat, paling realistis
- Keduanya saling melengkapi, tidak saling menggantikan

### Mengapa Playwright, Bukan Selenium?

| Fitur | Playwright | Selenium (Praktek 8) |
|-------|------------|----------------------|
| Auto-wait | **Ya** — otomatis tunggu elemen siap | Tidak — perlu `WebDriverWait` eksplisit |
| Bahasa | JavaScript/TypeScript, Python | Python, Java, C# |
| Multi-browser | Chromium, Firefox, WebKit built-in | Butuh driver terpisah |
| Instalasi | Satu perintah | Konfigurasi ChromeDriver manual |
| Standar industri | Semakin dominan (2020+) | Legacy, masih dipakai di korporat lama |

---

## Bagian 1 — Buat & Jalankan Aplikasi Target

Sebelum menulis test, kita perlu aplikasi yang akan diuji. Kita akan membuat **Todo App** sederhana berbasis HTML + JavaScript murni — tidak membutuhkan database atau backend.

### 1.1 Buat Folder dan File Aplikasi

Buat folder baru dan buat file `index.html` di dalamnya:

```bash
mkdir praktek9_e2e
cd praktek9_e2e
```

Buat file `index.html` dengan isi berikut:

```html
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Todo App Sederhana</title>
  <style>
    body { font-family: sans-serif; max-width: 480px; margin: 40px auto; padding: 0 16px; }
    h1   { font-size: 1.4rem; margin-bottom: 16px; }
    #input-row { display: flex; gap: 8px; margin-bottom: 24px; }
    #task-input { flex: 1; padding: 8px 12px; border: 1px solid #ccc;
                  border-radius: 4px; font-size: 1rem; }
    #add-btn { padding: 8px 16px; background: #2563eb; color: #fff;
               border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
    #add-btn:hover { background: #1d4ed8; }
    #empty-msg { color: #888; font-style: italic; }
    .todo-item { display: flex; align-items: center; gap: 8px;
                 padding: 8px 0; border-bottom: 1px solid #eee; }
    .todo-item input[type=checkbox] { cursor: pointer; }
    .todo-item span { flex: 1; }
    .todo-item.done span { text-decoration: line-through; color: #aaa; }
    .del-btn { background: #ef4444; color: #fff; border: none;
               border-radius: 4px; padding: 2px 8px; cursor: pointer; }
  </style>
</head>
<body>
  <h1>Todo App</h1>

  <div id="input-row">
    <input type="text" id="task-input" placeholder="Tugas baru..." />
    <button id="add-btn">Tambah</button>
  </div>

  <ul id="todo-list" style="list-style:none; padding:0; margin:0;"></ul>
  <p id="empty-msg">Belum ada tugas.</p>

  <script>
    const list     = document.getElementById('todo-list');
    const input    = document.getElementById('task-input');
    const addBtn   = document.getElementById('add-btn');
    const emptyMsg = document.getElementById('empty-msg');

    function render(items) {
      list.innerHTML = '';
      emptyMsg.style.display = items.length === 0 ? '' : 'none';
      items.forEach((item, idx) => {
        const li   = document.createElement('li');
        li.className = 'todo-item' + (item.done ? ' done' : '');

        const cb   = document.createElement('input');
        cb.type    = 'checkbox';
        cb.checked = item.done;
        cb.addEventListener('change', () => { items[idx].done = cb.checked; render(items); });

        const span = document.createElement('span');
        span.textContent = item.text;

        const del  = document.createElement('button');
        del.className   = 'del-btn';
        del.textContent = 'Hapus';
        del.addEventListener('click', () => { items.splice(idx, 1); render(items); });

        li.appendChild(cb);
        li.appendChild(span);
        li.appendChild(del);
        list.appendChild(li);
      });
    }

    const todos = [];
    render(todos);

    addBtn.addEventListener('click', () => {
      const text = input.value.trim();
      if (!text) return;
      todos.push({ text, done: false });
      input.value = '';
      render(todos);
    });

    input.addEventListener('keydown', e => { if (e.key === 'Enter') addBtn.click(); });
  </script>
</body>
</html>
```

### 1.2 Jalankan Server Lokal

Python sudah terinstall dari Praktek 8. Gunakan built-in HTTP server bawaan Python:

```bash
python -m http.server 8000
```

Buka browser ke: **http://localhost:8000**

Tampilan aplikasi:

![Todo App — Tampilan](images/p9_app_screenshot.png)

### 1.3 Verifikasi Aplikasi Berjalan Normal

Pastikan semua fitur bekerja **sebelum** menulis test:

| Aksi | Hasil yang Diharapkan |
|------|-----------------------|
| Buka `http://localhost:8000` | Halaman terbuka, tampil teks *"Belum ada tugas."* |
| Ketik tugas → klik **Tambah** | Item baru muncul di daftar |
| Ketik tugas → tekan **Enter** | Item baru muncul di daftar |
| Centang checkbox | Teks item menjadi coret (*strikethrough*) |
| Klik **Hapus** | Item hilang dari daftar |
| Hapus semua item | Teks *"Belum ada tugas."* muncul kembali |

Jika semua fitur bekerja, aplikasi siap diuji otomatis.

> **Biarkan server tetap berjalan** di terminal ini. Buka terminal baru untuk langkah berikutnya.

---

## Bagian 2 — Install Playwright

### 2.1 Prasyarat: Node.js

Playwright membutuhkan Node.js versi 18 atau lebih baru. Cek versi:

```bash
node --version   # harus >= v18.0.0
npm --version    # harus >= 8.0.0
```

Jika belum terinstall: unduh dari **https://nodejs.org** dan pilih versi **LTS**.

### 2.2 Inisialisasi Proyek Playwright

Masuk ke folder `praktek9_e2e` (terminal baru), lalu jalankan:

```bash
npm init playwright@latest
```

Saat ditanya, jawab seperti ini:

```
Where to put your end-to-end tests? › tests
Add a GitHub Actions workflow?       › n
Install Playwright browsers?         › y
```

Proses ini akan:
1. Membuat `package.json` dan `package-lock.json`
2. Membuat `playwright.config.js`
3. Mengunduh browser binaries Chromium, Firefox, WebKit (±250 MB, tunggu sampai selesai)

Setelah selesai, tampilan `package.json`:

![package.json](images/p9_package_json.png)

### 2.3 Konfigurasi `playwright.config.js`

Buka `playwright.config.js` yang terbentuk. Cari bagian `use:` dan tambahkan `baseURL`:

```javascript
use: {
  baseURL: 'http://localhost:8000',
  screenshot: 'only-on-failure',
},
```

Dengan `baseURL`, cukup tulis `page.goto('/')` di setiap test — tidak perlu mengetik URL penuh setiap kali.

Tampilan `playwright.config.js` setelah diedit:

![playwright.config.js](images/p9_playwright_config.png)

### 2.4 Verifikasi Instalasi

Hapus file contoh bawaan Playwright, lalu verifikasi instalasi:

```bash
# Hapus file contoh (bawaan dari init)
del tests\example.spec.js        # Windows
rm tests/example.spec.js         # Mac/Linux
```

Buat file test sementara `tests/cek.spec.js`:

```javascript
const { test, expect } = require('@playwright/test');

test('server dan playwright berjalan', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle('Todo App Sederhana');
});
```

Jalankan:

```bash
npx playwright test tests/cek.spec.js
```

Output yang diharapkan:

```
Running 1 test using 1 worker
  ✓  tests/cek.spec.js:3 › server dan playwright berjalan  (1.4s)
1 passed (2.3s)
```

Jika lulus: instalasi berhasil! Hapus file `tests/cek.spec.js` sebelum melanjutkan.

---

## Konsep Playwright

### Struktur Test Dasar

```javascript
const { test, expect } = require('@playwright/test');

test('nama test yang deskriptif', async ({ page }) => {
  // Arrange: buka halaman
  await page.goto('/');

  // Act: lakukan aksi
  await page.fill('#task-input', 'Beli susu');
  await page.click('#add-btn');

  // Assert: verifikasi hasil
  await expect(page.locator('.todo-item span')).toHaveText('Beli susu');
});
```

### Auto-Wait — Keunggulan Playwright vs Selenium

Playwright **menunggu secara otomatis** setiap kali berinteraksi dengan elemen. Tidak perlu `WebDriverWait` atau `time.sleep()`:

| Skenario | Selenium (Praktek 8) | Playwright |
|----------|---------------------|-----------|
| Tunggu elemen | `WebDriverWait(driver, 10).until(EC.visibility_of(...))` | **Otomatis** |
| Klik tombol | `find_element(By.ID, "btn").click()` | `await page.click('#btn')` |
| Verifikasi teks | `assert element.text == 'teks'` | `await expect(locator).toHaveText('teks')` — auto-retry |

### Navigasi dan Interaksi

| Method | Kegunaan | Contoh |
|--------|----------|--------|
| `page.goto('/')` | Buka halaman | `await page.goto('/')` |
| `page.fill(sel, text)` | Isi input field | `await page.fill('#task-input', 'Beli susu')` |
| `page.click(sel)` | Klik elemen | `await page.click('#add-btn')` |
| `page.press(sel, key)` | Tekan tombol keyboard | `await page.press('#task-input', 'Enter')` |
| `page.locator(sel)` | Referensi ke elemen | `page.locator('.todo-item')` |

### Assertions

| Assertion | Kegunaan |
|-----------|----------|
| `expect(page).toHaveTitle('judul')` | Cek judul tab browser |
| `expect(locator).toBeVisible()` | Elemen tampak di layar |
| `expect(locator).not.toBeVisible()` | Elemen tidak tampak |
| `expect(locator).toHaveText('teks')` | Elemen mengandung teks persis |
| `expect(locator).toContainText('teks')` | Elemen mengandung substring |
| `expect(locator).toHaveValue('')` | Nilai input field |
| `expect(locator).toHaveCount(n)` | Jumlah elemen yang ditemukan |

### Arsitektur Playwright

![Playwright Architecture](images/p9_playwright_arch.png)

---

## Demo Sederhana 1 — Cek Judul Halaman

Test paling dasar: buka halaman dan verifikasi judul tab browser.

Buat file `tests/simple1_judul.spec.js`. **Ketik seluruh kode dari gambar berikut** (jangan copy-paste):

![Demo Sederhana 1 — Kode](images/p9_simple1_code.png)

Jalankan dengan browser terlihat (`--headed`):

```bash
npx playwright test tests/simple1_judul.spec.js --headed
```

Output yang diharapkan:

```
Running 1 test using 1 worker
  ✓  tests/simple1_judul.spec.js:3 › judul halaman harus "Todo App Sederhana"  (1.2s)
1 passed (2.1s)
```

**Amati:** Browser Chrome akan terbuka secara otomatis, masuk ke `localhost:8000`, lalu menutup sendiri. Ini adalah Playwright mengendalikan browser secara programatik — sama seperti Selenium, tapi lebih ringkas.

---

## Demo Sederhana 2 — Tambah Item ke Daftar

Test interaksi: isi form, klik tombol, verifikasi item muncul dan input kembali kosong.

Buat file `tests/simple2_tambah_item.spec.js`. **Ketik seluruh kode dari gambar berikut:**

![Demo Sederhana 2 — Kode](images/p9_simple2_code.png)

Jalankan:

```bash
npx playwright test tests/simple2_tambah_item.spec.js --headed
```

Output yang diharapkan:

```
Running 1 test using 1 worker
  ✓  tests/simple2_tambah_item.spec.js:3 › dapat menambah item baru ke daftar  (1.8s)
1 passed (2.5s)
```

**Perhatikan perbedaan vs Selenium (Praktek 8):**
- Tidak ada `WebDriverWait` atau `EC` — Playwright auto-wait
- Tidak ada `driver.find_element(By.ID, ...)` — langsung `page.fill('#id', ...)`
- `await expect(locator).toHaveValue('')` otomatis retry sampai kondisi terpenuhi

---

## Demo Advanced — Test CRUD Lengkap

Test lebih lengkap dengan `test.describe` dan `test.beforeEach`. Kode ini **boleh di-copy** — fokus praktik ada di Soal.

Buat file `tests/todo.spec.js`:

```javascript
const { test, expect } = require('@playwright/test');

test.describe('Todo App', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('menampilkan pesan "Belum ada tugas" saat list kosong', async ({ page }) => {
    await expect(page.locator('#empty-msg')).toBeVisible();
    await expect(page.locator('#empty-msg')).toContainText('Belum ada tugas');
    await expect(page.locator('.todo-item')).toHaveCount(0);
  });

  test('dapat menambah item baru ke daftar', async ({ page }) => {
    await page.fill('#task-input', 'Beli susu');
    await page.click('#add-btn');

    await expect(page.locator('.todo-item span')).toHaveText('Beli susu');
    await expect(page.locator('#task-input')).toHaveValue('');
    await expect(page.locator('#empty-msg')).not.toBeVisible();
  });

  test('dapat menambah item dengan menekan Enter', async ({ page }) => {
    await page.fill('#task-input', 'Kerjakan PR');
    await page.press('#task-input', 'Enter');

    await expect(page.locator('.todo-item')).toHaveCount(1);
  });

  test('dapat menandai item sebagai selesai', async ({ page }) => {
    await page.fill('#task-input', 'Kerjakan PR');
    await page.click('#add-btn');

    await page.locator('.todo-item input[type=checkbox]').click();

    await expect(page.locator('.todo-item.done')).toHaveCount(1);
  });

  test('dapat menghapus item dari daftar', async ({ page }) => {
    await page.fill('#task-input', 'Tugas sementara');
    await page.click('#add-btn');

    await page.locator('.del-btn').click();

    await expect(page.locator('.todo-item')).toHaveCount(0);
    await expect(page.locator('#empty-msg')).toBeVisible();
  });

  test('alur CRUD lengkap: tambah 3 item, tandai 1, hapus semua', async ({ page }) => {
    for (const task of ['Beli susu', 'Kerjakan PR', 'Beli buku']) {
      await page.fill('#task-input', task);
      await page.click('#add-btn');
    }
    await expect(page.locator('.todo-item')).toHaveCount(3);

    await page.locator('.todo-item input[type=checkbox]').first().click();
    await expect(page.locator('.todo-item.done')).toHaveCount(1);

    await page.locator('.del-btn').first().click();
    await page.locator('.del-btn').first().click();
    await page.locator('.del-btn').first().click();

    await expect(page.locator('.todo-item')).toHaveCount(0);
    await expect(page.locator('#empty-msg')).toBeVisible();
  });

});
```

Jalankan semua test:

```bash
npx playwright test tests/todo.spec.js --headed
```

Output yang diharapkan:

![Test Output](images/p9_test_output.png)

---

## Cara Menjalankan Test

```bash
# Jalankan semua test (headless — browser tidak terlihat)
npx playwright test

# Jalankan dengan browser terlihat
npx playwright test --headed

# Jalankan satu file saja
npx playwright test tests/todo.spec.js

# Jalankan di browser tertentu
npx playwright test --project=firefox

# Buka HTML report interaktif
npx playwright show-report

# Mode debug — Playwright Inspector
npx playwright test --debug
```

Contoh output CLI:

![CLI Output](images/p9_cli_output.png)

---

## Soal

### Soal 1 — Setup (20 poin)

**Langkah yang harus dilakukan dan dikumpulkan:**

a) Buat folder `praktek9_e2e/` dan buat file `index.html` sesuai kode di atas.

b) Jalankan server lokal dan buka di browser. **Screenshot** halaman aplikasi di browser yang menunjukkan tampilan Todo App berjalan.

c) Inisialisasi Playwright dengan `npm init playwright@latest`.

d) Konfigurasi `playwright.config.js` dengan `baseURL: 'http://localhost:8000'`.

e) Jalankan test verifikasi (`page.goto('/') + toHaveTitle`) dan **screenshot** output terminal yang menunjukkan test lulus.

**Kriteria Soal 1:**
- Screenshot aplikasi berjalan di browser (5 poin)
- Playwright terinstall, `package.json` terbentuk (5 poin)
- `playwright.config.js` memiliki `baseURL` yang benar (5 poin)
- Test verifikasi lulus (5 poin)

---

### Soal 2 — Test Tambah Item (30 poin)

Buat file `tests/soal2_tambah.spec.js` dan implementasikan **3 test** berikut:

```javascript
const { test, expect } = require('@playwright/test');

test.describe('Soal 2 - Tambah Item', () => {

  test('dapat menambah satu item baru', async ({ page }) => {
    // TODO: buka halaman, isi input, klik Tambah
    // Verifikasi: item muncul di daftar, input kembali kosong
  });

  test('dapat menambah beberapa item sekaligus', async ({ page }) => {
    // TODO: tambah 3 item berbeda satu per satu
    // Verifikasi: daftar memiliki 3 item
  });

  test('pesan kosong hilang saat ada item', async ({ page }) => {
    // TODO: buka halaman, verifikasi pesan kosong muncul
    // Tambah satu item, verifikasi pesan kosong hilang
  });

});
```

**Petunjuk:**
- Gunakan `page.goto('/')` (baseURL sudah dikonfigurasi)
- Gunakan `page.fill('#task-input', 'teks')` untuk isi input
- Gunakan `page.click('#add-btn')` untuk klik tombol
- Gunakan `expect(page.locator('.todo-item')).toHaveCount(n)` untuk cek jumlah

**Kriteria Soal 2:**
- Test tambah satu item: lulus dengan assertion yang benar (10 poin)
- Test tambah beberapa item: lulus, `toHaveCount(3)` diverifikasi (10 poin)
- Test pesan kosong: lulus, `toBeVisible()` dan `not.toBeVisible()` digunakan (10 poin)

---

### Soal 3 — Test Tandai Selesai & Hapus (30 poin)

Buat file `tests/soal3_crud.spec.js` dan implementasikan **3 test** berikut:

```javascript
const { test, expect } = require('@playwright/test');

test.describe('Soal 3 - Tandai Selesai dan Hapus', () => {

  test.beforeEach(async ({ page }) => {
    await page.goto('/');
    // Siapkan: tambah item untuk test berikutnya
    await page.fill('#task-input', 'Tugas contoh');
    await page.click('#add-btn');
  });

  test('dapat menandai item sebagai selesai', async ({ page }) => {
    // TODO: klik checkbox item
    // Verifikasi: item memiliki class 'done'
  });

  test('dapat menghapus item', async ({ page }) => {
    // TODO: klik tombol Hapus
    // Verifikasi: item tidak ada, pesan kosong muncul
  });

  test('dapat menghapus semua item', async ({ page }) => {
    // TODO: tambah 2 item lagi, hapus semua satu per satu
    // Verifikasi: daftar kosong, pesan "Belum ada tugas" muncul
  });

});
```

**Petunjuk:**
- Locator checkbox: `page.locator('.todo-item input[type=checkbox]')`
- Locator tombol hapus: `page.locator('.del-btn')`
- Item yang sudah ditandai selesai memiliki class `done`: `.todo-item.done`

**Kriteria Soal 3:**
- Test tandai selesai: `toHaveCount(1)` pada `.todo-item.done` (10 poin)
- Test hapus item: `toHaveCount(0)` dan pesan kosong muncul (10 poin)
- Test hapus semua: loop hapus item sampai kosong (10 poin)

---

### Soal 4 — Cross-Browser Testing (10 poin)

a) Edit `playwright.config.js` untuk mengaktifkan tiga browser:

```javascript
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests',
  use: {
    baseURL: 'http://localhost:8000',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'firefox',  use: { ...devices['Desktop Firefox'] } },
    { name: 'webkit',   use: { ...devices['Desktop Safari'] } },
  ],
});
```

b) Jalankan semua test di ketiga browser:

```bash
npx playwright test
```

c) Buka HTML report:

```bash
npx playwright show-report
```

d) **Screenshot** HTML report yang menunjukkan semua test lulus di 3 browser.

**Kriteria Soal 4:**
- `playwright.config.js` memiliki 3 project browser (4 poin)
- Semua test lulus di Chromium, Firefox, WebKit (4 poin)
- Screenshot HTML report dengan 3 browser hijau semua (2 poin)

---

### Soal 5 — Refleksi (10 poin)

Jawab dalam file `REFLEKSI.md`:

**Pertanyaan 1 (4 poin):**
Jelaskan perbedaan antara *explicit wait* di Selenium dan *auto-wait* di Playwright. Kapan auto-wait bisa gagal (tidak cukup)? Berikan satu contoh skenario.

**Pertanyaan 2 (3 poin):**
Dalam test Soal 3, kita menggunakan `test.beforeEach` untuk menyiapkan data. Apa keuntungan menggunakan `beforeEach` dibanding menyalin kode tambah item ke setiap test secara manual?

**Pertanyaan 3 (3 poin):**
Sebutkan satu kasus di mana E2E testing **lebih tepat** dari unit testing, dan satu kasus di mana unit testing **lebih tepat** dari E2E testing. Jelaskan alasannya.

---

## Struktur Folder Akhir

```
praktek9_e2e/
├── index.html                  ← Aplikasi Todo
├── package.json                ← Konfigurasi Node.js
├── package-lock.json
├── playwright.config.js        ← Konfigurasi Playwright
└── tests/
    ├── simple1_judul.spec.js   ← Demo Sederhana 1 (ketik sendiri)
    ├── simple2_tambah_item.spec.js  ← Demo Sederhana 2 (ketik sendiri)
    ├── todo.spec.js            ← Demo Advanced (copy boleh)
    ├── soal2_tambah.spec.js    ← Jawaban Soal 2
    ├── soal3_crud.spec.js      ← Jawaban Soal 3
    └── REFLEKSI.md             ← Jawaban Soal 5
```

---

## Kriteria Penilaian

| Soal | Bobot | Kriteria |
|------|-------|----------|
| Soal 1 — Setup | 20 poin | App berjalan, Playwright terinstall, config baseURL, test verifikasi lulus |
| Soal 2 — Tambah Item | 30 poin | 3 test lulus: tambah satu, tambah banyak, pesan kosong |
| Soal 3 — Tandai & Hapus | 30 poin | 3 test lulus: tandai selesai, hapus item, hapus semua |
| Soal 4 — Cross-Browser | 10 poin | Config 3 browser, semua test hijau, screenshot report |
| Soal 5 — Refleksi | 10 poin | Jawaban tepat tentang auto-wait, beforeEach, E2E vs unit |

**Total: 100 poin**
