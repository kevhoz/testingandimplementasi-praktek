# Praktek 5 — Database Testing dengan MySQL dan PHPUnit

## Tujuan Pembelajaran

Setelah menyelesaikan praktek ini, mahasiswa mampu:
- Memahami konsep database testing dan isolasi data antar test
- Menulis test untuk operasi CRUD menggunakan PHPUnit dan MySQL
- Menerapkan teknik transaction rollback agar setiap test berjalan bersih
- Memvalidasi constraint dan relasi antar tabel melalui test

---

## Latar Belakang

Pada Praktek 2 (Integration Testing) kita menguji interaksi antar service PHP tanpa database.
Pada praktek ini, kita akan menguji **interaksi kode dengan database MySQL secara nyata**.

### Mengapa Database Testing Penting?

Bisa saja kode PHP sudah benar, tapi:
- Query SQL mengandung typo nama kolom
- Kondisi `WHERE` menghasilkan data yang salah
- Constraint `UNIQUE` atau `NOT NULL` tidak berfungsi seperti yang diharapkan
- Relasi antar tabel (foreign key) tidak terjaga

Semua masalah ini **hanya bisa ditemukan** dengan menjalankan test langsung ke database.

### Tantangan: Isolasi Data

Setiap test harus **tidak saling mempengaruhi**. Solusi yang digunakan pada praktek ini adalah **Transaction Rollback**:

```
setUp()     → BEGIN TRANSACTION
test_xxx()  → INSERT / SELECT / UPDATE / DELETE
tearDown()  → ROLLBACK  ← data kembali seperti semula
```

Dengan cara ini, setiap test selalu mulai dari kondisi database yang bersih.

---

## Pola PDO yang Digunakan

Sebelum mulai coding, pahami 3 pola PDO yang akan sering digunakan:

### Pola 1 — INSERT dan ambil ID baru

```php
$stmt = $this->db->prepare(
    'INSERT INTO products (name, price) VALUES (:name, :price)'
);
$stmt->execute([':name' => 'Buku', ':price' => 50000]);
$id = (int) $this->db->lastInsertId();
```

### Pola 2 — SELECT satu baris

```php
$stmt = $this->db->prepare(
    'SELECT * FROM products WHERE id = :id'
);
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
// $row adalah array data, atau false jika tidak ada
```

### Pola 3 — SELECT banyak baris

```php
$stmt = $this->db->prepare(
    'SELECT * FROM products WHERE category = :cat'
);
$stmt->execute([':cat' => 'Elektronik']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
// $rows adalah array of array
```

### Pola 4 — UPDATE / DELETE, cek apakah berhasil

```php
$stmt = $this->db->prepare(
    'UPDATE products SET stock = :stock WHERE id = :id'
);
$stmt->execute([':stock' => 20, ':id' => $id]);
$affected = $stmt->rowCount();
// rowCount() > 0 berarti ada baris yang terubah
```

---

## Skenario

Anda diminta membuat sistem manajemen produk dan pesanan untuk **Toko Online "TokoKita"**.

Sistem memiliki dua tabel:
- **`products`** — menyimpan data produk (nama, harga, stok, kategori)
- **`orders`** — menyimpan data pesanan yang merujuk ke produk

---

## Setup Lingkungan

### 1. Prasyarat

Pastikan sudah terinstall:
- PHP >= 8.0
- Composer
- MySQL Server (XAMPP / Laragon / standalone)
- PHPUnit (diinstall via Composer)

### 2. Buat Database dan Tabel

Buka MySQL client (phpMyAdmin / HeidiSQL / terminal) dan jalankan script berikut:

![Setup SQL](images/p5_setup_sql.png)

> File lengkap tersedia di: `setup.sql` (ada di folder jawaban sebagai referensi)

### 3. Struktur Folder Project

Buat folder baru dengan nama `praktek5_database_testing`, lalu buat struktur berikut:

```
praktek5_database_testing/
├── composer.json
├── phpunit.xml
├── src/
│   ├── Database.php
│   ├── ProductRepository.php
│   └── OrderRepository.php
└── tests/
    ├── BaseTestCase.php
    ├── ProductRepositoryTest.php
    └── OrderRepositoryTest.php
```

### 4. Konfigurasi Composer

Buat file `composer.json`:

![composer.json](images/p5_composer_json.png)

Jalankan:
```bash
composer install
```

### 5. Konfigurasi PHPUnit

Buat file `phpunit.xml`:

![phpunit.xml](images/p5_phpunit_xml.png)

> Sesuaikan nilai `DB_USER` dan `DB_PASS` dengan konfigurasi MySQL Anda.

---

## Kelas yang Harus Diimplementasikan

### `src/Database.php`

![Database.php](images/p5_database_php.png)

Class ini bertanggung jawab membuka koneksi PDO ke MySQL.

Koneksi mengambil konfigurasi dari **environment variable** yang didefinisikan di `phpunit.xml`:
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_PORT`

Gunakan `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` agar error database langsung dilempar sebagai exception.

---

### `src/ProductRepository.php`

Gambar berikut menunjukkan **skeleton** class dan contoh dua method pertama:

![ProductRepository.php](images/p5_product_repository.png)

Perhatikan bahwa gambar hanya menampilkan `save()` dan `findById()`. Anda perlu menambahkan method-method lainnya.

**Contoh implementasi method yang tersisa:**

```php
public function findAll(): array
{
    $stmt = $this->db->query('SELECT * FROM products');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function findByCategory(string $category): array
{
    $stmt = $this->db->prepare(
        'SELECT * FROM products WHERE category = :category'
    );
    $stmt->execute([':category' => $category]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function updateStock(int $id, int $stock): bool
{
    $stmt = $this->db->prepare(
        'UPDATE products SET stock = :stock WHERE id = :id'
    );
    $stmt->execute([':stock' => $stock, ':id' => $id]);
    return $stmt->rowCount() > 0;
}

public function delete(int $id): bool
{
    $stmt = $this->db->prepare('DELETE FROM products WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}
```

---

**Tabel method yang harus ada di `ProductRepository`:**

| Method | Parameter | Return | Keterangan |
|--------|-----------|--------|------------|
| `__construct(PDO $db)` | PDO instance | — | Dependency injection |
| `save(array $data)` | `name`, `price`, `stock`, `category` | `int` (id baru) | Insert produk baru |
| `findById(int $id)` | id | `array\|null` | Cari produk by id |
| `findAll()` | — | `array` | Semua produk |
| `findByCategory(string $cat)` | category | `array` | Filter by kategori |
| `updateStock(int $id, int $stock)` | id, stok baru | `bool` | Update stok produk |
| `delete(int $id)` | id | `bool` | Hapus produk |

---

### `src/OrderRepository.php`

Class ini mengelola operasi database untuk tabel `orders`.

**Contoh implementasi `create()` dan `findById()`:**

```php
<?php
namespace App;

class OrderRepository
{
    public function __construct(private \PDO $db) {}

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO orders (product_id, quantity, total_price) '
            . 'VALUES (:product_id, :quantity, :total_price)'
        );
        $stmt->execute([
            ':product_id'  => $data['product_id'],
            ':quantity'    => $data['quantity'],
            ':total_price' => $data['total_price'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    // Lengkapi: findByStatus(), updateStatus(), countByProduct()
}
```

**Tabel method yang harus ada di `OrderRepository`:**

| Method | Parameter | Return | Keterangan |
|--------|-----------|--------|------------|
| `__construct(PDO $db)` | PDO instance | — | Dependency injection |
| `create(array $data)` | `product_id`, `quantity`, `total_price` | `int` (id baru) | Buat pesanan baru |
| `findById(int $id)` | id | `array\|null` | Cari pesanan by id |
| `findByStatus(string $status)` | status | `array` | Filter pesanan by status |
| `updateStatus(int $id, string $status)` | id, status baru | `bool` | Update status pesanan |
| `countByProduct(int $productId)` | product_id | `int` | Hitung jumlah pesanan per produk |

---

## Soal

### Soal 1 — Setup dan Koneksi Database (20 poin)

a) Buat database `tokokita_testing` dan kedua tabel sesuai skema di atas.

b) Implementasikan `src/Database.php`. Class ini harus:
   - Membaca konfigurasi dari environment variable
   - Mengembalikan instance PDO
   - Melempar exception bila koneksi gagal

c) Buat `tests/BaseTestCase.php` sebagai parent class untuk semua test. Class ini harus:
   - Membuka koneksi ke database di `setUp()`
   - Memulai transaksi (`beginTransaction`) di `setUp()`
   - Melakukan rollback (`rollBack`) di `tearDown()`

![BaseTestCase.php](images/p5_base_test_case.png)

Verifikasi: jalankan `vendor/bin/phpunit --list-tests` — harus tidak ada error koneksi.

---

### Soal 2 — Testing ProductRepository (40 poin)

Implementasikan `src/ProductRepository.php`, lalu buat `tests/ProductRepositoryTest.php` yang berisi minimal **6 test case** berikut.

Gambar di bawah menunjukkan struktur class dan **test case pertama** sebagai contoh:

![ProductRepositoryTest.php](images/p5_product_test.png)

**Lengkapi 5 test case berikutnya:**

```php
public function test_dapat_mencari_produk_berdasarkan_id(): void
{
    // Arrange — simpan dulu satu produk
    $id = $this->repo->save([
        'name' => 'Mouse Logitech', 'price' => 250000,
        'stock' => 5, 'category' => 'Komputer',
    ]);

    // Act
    $product = $this->repo->findById($id);

    // Assert
    $this->assertNotNull($product);
    $this->assertEquals('Mouse Logitech', $product['name']);
    $this->assertEquals(250000, $product['price']);
}

public function test_findById_mengembalikan_null_jika_tidak_ada(): void
{
    $product = $this->repo->findById(99999); // id yang pasti tidak ada
    $this->assertNull($product);
}

public function test_dapat_memfilter_produk_berdasarkan_kategori(): void
{
    // Arrange — simpan 2 produk berbeda kategori
    $this->repo->save([
        'name' => 'Laptop Asus', 'price' => 8500000,
        'stock' => 3, 'category' => 'Elektronik',
    ]);
    $this->repo->save([
        'name' => 'Buku PHP', 'price' => 120000,
        'stock' => 10, 'category' => 'Buku',
    ]);

    // Act
    $hasil = $this->repo->findByCategory('Elektronik');

    // Assert
    $this->assertCount(1, $hasil);
    $this->assertEquals('Laptop Asus', $hasil[0]['name']);
}

public function test_dapat_mengupdate_stok_produk(): void
{
    $id = $this->repo->save([
        'name' => 'Keyboard', 'price' => 300000,
        'stock' => 5, 'category' => 'Komputer',
    ]);

    $berhasil = $this->repo->updateStock($id, 50);

    $this->assertTrue($berhasil);
    $product = $this->repo->findById($id);
    $this->assertEquals(50, $product['stock']);
}

public function test_dapat_menghapus_produk(): void
{
    $id = $this->repo->save([
        'name' => 'Monitor', 'price' => 1500000,
        'stock' => 2, 'category' => 'Elektronik',
    ]);

    $berhasil = $this->repo->delete($id);

    $this->assertTrue($berhasil);
    $this->assertNull($this->repo->findById($id));
}
```

---

**Tabel ringkasan 6 test case Soal 2:**

| No | Nama Test | Yang Diuji |
|----|-----------|------------|
| 1 | `test_dapat_menyimpan_produk_baru` | Method `save()` menyimpan data dan mengembalikan id valid (> 0) |
| 2 | `test_dapat_mencari_produk_berdasarkan_id` | `findById()` mengembalikan data produk yang benar |
| 3 | `test_findById_mengembalikan_null_jika_tidak_ada` | `findById()` dengan id tidak ada mengembalikan `null` |
| 4 | `test_dapat_memfilter_produk_berdasarkan_kategori` | `findByCategory()` hanya mengembalikan produk kategori tersebut |
| 5 | `test_dapat_mengupdate_stok_produk` | `updateStock()` mengubah nilai stok di database |
| 6 | `test_dapat_menghapus_produk` | `delete()` menghapus produk, `findById()` sesudahnya mengembalikan `null` |

---

### Soal 3 — Testing OrderRepository (30 poin)

Implementasikan `src/OrderRepository.php`, lalu buat `tests/OrderRepositoryTest.php` yang berisi minimal **4 test case** berikut.

Gambar di bawah menunjukkan test untuk **kasus error** (test ke-4) sebagai referensi:

![OrderRepositoryTest.php](images/p5_order_test.png)

**Lengkapi 3 test case lainnya:**

```php
<?php
namespace Tests;

use App\OrderRepository;
use App\ProductRepository;
use PDOException;

class OrderRepositoryTest extends BaseTestCase
{
    private OrderRepository $orderRepo;
    private int $productId; // produk dummy untuk relasi

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderRepo = new OrderRepository($this->db);

        // Buat produk dummy agar foreign key order valid
        $productRepo = new ProductRepository($this->db);
        $this->productId = $productRepo->save([
            'name'     => 'Produk Test',
            'price'    => 100000,
            'stock'    => 50,
            'category' => 'Test',
        ]);
    }

    public function test_dapat_membuat_pesanan_baru(): void
    {
        $id = $this->orderRepo->create([
            'product_id'  => $this->productId,
            'quantity'    => 2,
            'total_price' => 200000,
        ]);

        $this->assertGreaterThan(0, $id);
    }

    public function test_dapat_mencari_pesanan_berdasarkan_status(): void
    {
        // Arrange — buat 2 pesanan, satu pending satu completed
        $this->orderRepo->create([
            'product_id'  => $this->productId,
            'quantity'    => 1,
            'total_price' => 100000,
        ]);
        $idCompleted = $this->orderRepo->create([
            'product_id'  => $this->productId,
            'quantity'    => 3,
            'total_price' => 300000,
        ]);
        $this->orderRepo->updateStatus($idCompleted, 'completed');

        // Act
        $pendingOrders = $this->orderRepo->findByStatus('pending');

        // Assert — hanya 1 yang statusnya pending
        $this->assertCount(1, $pendingOrders);
        $this->assertEquals('pending', $pendingOrders[0]['status']);
    }

    public function test_dapat_mengupdate_status_pesanan(): void
    {
        $id = $this->orderRepo->create([
            'product_id'  => $this->productId,
            'quantity'    => 1,
            'total_price' => 100000,
        ]);

        $berhasil = $this->orderRepo->updateStatus($id, 'processing');

        $this->assertTrue($berhasil);
        $order = $this->orderRepo->findById($id);
        $this->assertEquals('processing', $order['status']);
    }

    // Test ke-4: gunakan contoh dari gambar p5_order_test.png di atas
}
```

---

**Tabel ringkasan 4 test case Soal 3:**

| No | Nama Test | Yang Diuji |
|----|-----------|------------|
| 1 | `test_dapat_membuat_pesanan_baru` | `create()` menyimpan pesanan dan mengembalikan id valid |
| 2 | `test_dapat_mencari_pesanan_berdasarkan_status` | `findByStatus('pending')` hanya mengembalikan pesanan pending |
| 3 | `test_dapat_mengupdate_status_pesanan` | `updateStatus()` mengubah status, diverifikasi dengan `findById()` |
| 4 | `test_pesanan_gagal_jika_product_id_tidak_ada` | `create()` dengan `product_id` yang tidak ada harus melempar exception |

---

### Soal 4 — Analisis dan Refleksi (10 poin)

Jawab pertanyaan berikut dalam file `REFLEKSI.md`:

1. Mengapa kita menggunakan **transaction rollback** di `tearDown()` bukan menghapus data dengan `DELETE` biasa?

2. Apa perbedaan antara test pada **Soal 3 No. 4** dengan test-test lainnya? Mengapa kita perlu menguji kasus error/gagal?

3. Jika ada **dua developer** menjalankan test secara bersamaan di database yang sama, apakah transaction rollback cukup untuk menjaga isolasi? Jelaskan!

---

## Cara Menjalankan Test

```bash
# Jalankan semua test
vendor/bin/phpunit

# Jalankan dengan output detail
vendor/bin/phpunit --testdox

# Jalankan satu file test saja
vendor/bin/phpunit tests/ProductRepositoryTest.php
```

### Contoh Output yang Diharapkan

![Expected Test Output](images/p5_test_output.png)

---

## Kriteria Penilaian

| Soal | Bobot | Kriteria |
|------|-------|----------|
| Soal 1 | 20 poin | Database terbuat, koneksi berhasil, BaseTestCase dengan rollback benar |
| Soal 2 | 40 poin | Semua 6 test case ada, pass, dan assertion bermakna |
| Soal 3 | 30 poin | Semua 4 test case ada, pass, termasuk test kasus error |
| Soal 4 | 10 poin | Jawaban refleksi tepat dan menunjukkan pemahaman konsep |

**Total: 100 poin**
