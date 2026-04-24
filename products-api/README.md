# products-api

REST API sederhana untuk data produk. Digunakan sebagai **target pengujian** pada:

- **Praktek 4** — API Testing dengan Postman
- **Praktek 6** — Performance Testing dengan JMeter

---

## Cara Setup (XAMPP / Laragon)

1. **Copy folder ini ke `htdocs`:**
   ```
   C:\xampp\htdocs\products-api\
   ```
   (Atau pakai `laragon/www/products-api/` kalau pakai Laragon.)

2. **Import database:**
   - Buka phpMyAdmin → `http://localhost/phpmyadmin`
   - Klik tab **Import** → pilih file `setup.sql` di folder ini → Go.
   - Atau via CLI:
     ```bash
     mysql -u root < setup.sql
     ```

3. **Start Apache + MySQL** dari XAMPP Control Panel.

4. **Tes di browser:**
   ```
   http://localhost/products-api/index.php?endpoint=products
   ```
   Harusnya muncul JSON berisi 5 produk seed.

---

## Endpoint

Response envelope: `{ "success": bool, "data": mixed|null, "message": string }`

| Method | URL | Keterangan | Status sukses |
|---|---|---|---|
| GET | `?endpoint=products` | List semua produk | 200 |
| GET | `?endpoint=products&id=1` | Detail satu produk | 200 |
| POST | `?endpoint=products` | Buat produk (body JSON) | 201 |
| PUT | `?endpoint=products&id=1` | Update produk (body JSON) | 200 |
| DELETE | `?endpoint=products&id=1` | Hapus produk | 200 |

### Body JSON untuk POST / PUT

```json
{
  "name": "Produk Baru",
  "price": 50000,
  "stock": 10,
  "category": "Elektronik"
}
```

### Contoh response

```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Laptop Asus", "price": 8500000, "stock": 10, "category": "Elektronik" }
  ],
  "message": "OK"
}
```

---

## Override konfigurasi DB

Set environment variable sebelum menjalankan Apache kalau konfigurasi MySQL berbeda dari default XAMPP:

| Variable | Default |
|---|---|
| `DB_HOST` | `localhost` |
| `DB_NAME` | `tokokita_testing` |
| `DB_USER` | `root` |
| `DB_PASS` | *(kosong)* |
| `DB_PORT` | `3306` |
