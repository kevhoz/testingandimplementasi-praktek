<?php

declare(strict_types=1);

/**
 * products-api/index.php
 *
 * REST API untuk data produk. Dipakai oleh:
 *  - Praktek 6 (Performance Testing dengan JMeter)
 *  - Praktek 4 (API Testing dengan Postman)
 *
 * Routing via query string: ?endpoint=products (opsional: &id=X)
 *
 * Endpoint:
 *   GET    ?endpoint=products        → list semua produk
 *   GET    ?endpoint=products&id=X   → detail produk
 *   POST   ?endpoint=products        → buat produk (body JSON)
 *   PUT    ?endpoint=products&id=X   → update produk (body JSON)
 *   DELETE ?endpoint=products&id=X   → hapus produk
 *
 * Response envelope:
 *   { "success": bool, "data": mixed|null, "message": string }
 */

// ── Headers ───────────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Konfigurasi database (default XAMPP) ──────────────────────────────────
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME') ?: 'tokokita_testing';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_PORT = getenv('DB_PORT') ?: '3306';

// ── Helper ────────────────────────────────────────────────────────────────
function respond(bool $success, mixed $data = null, string $message = 'OK', int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        ['success' => $success, 'data' => $data, 'message' => $message],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

function validatePositiveInt(mixed $value): ?int
{
    if ($value === null) {
        return null;
    }
    $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $int !== false ? (int) $int : null;
}

function castProduct(array $row): array
{
    return [
        'id'       => (int) $row['id'],
        'name'     => $row['name'],
        'price'    => (float) $row['price'],
        'stock'    => (int) $row['stock'],
        'category' => $row['category'],
    ];
}

// ── Koneksi PDO ───────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    respond(false, null, 'Service unavailable', 503);
}

// ── Routing ───────────────────────────────────────────────────────────────
$method   = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

if ($endpoint !== 'products') {
    respond(false, null, 'Unknown endpoint. Gunakan ?endpoint=products', 404);
}

$idProvided = isset($_GET['id']) && $_GET['id'] !== '';
$id         = validatePositiveInt($_GET['id'] ?? null);

if ($idProvided && $id === null) {
    respond(false, null, 'Parameter id tidak valid', 400);
}

try {
    switch ($method) {

        // ─── GET ───────────────────────────────────────────────────────
        case 'GET':
            if ($id !== null) {
                $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $row = $stmt->fetch();
                if ($row === false) {
                    respond(false, null, 'Product not found', 404);
                }
                respond(true, castProduct($row));
            }
            $rows = $pdo->query('SELECT * FROM products ORDER BY id ASC')->fetchAll();
            respond(true, array_map('castProduct', $rows));

        // ─── POST ──────────────────────────────────────────────────────
        case 'POST':
            $data = json_decode(file_get_contents('php://input') ?: '', true);
            if (!is_array($data)) {
                respond(false, null, 'Body JSON tidak valid', 400);
            }

            $name     = isset($data['name']) && is_string($data['name'])  ? trim($data['name']) : '';
            $price    = filter_var($data['price']  ?? null, FILTER_VALIDATE_FLOAT);
            $stock    = filter_var($data['stock']  ?? null, FILTER_VALIDATE_INT);
            $category = isset($data['category']) && is_string($data['category']) ? trim($data['category']) : null;

            $errors = [];
            if ($name === '' || mb_strlen($name) > 100) {
                $errors[] = 'name wajib diisi (maks 100 karakter)';
            }
            if ($price === false || $price < 0) {
                $errors[] = 'price harus angka >= 0';
            }
            if ($stock === false || $stock < 0) {
                $errors[] = 'stock harus integer >= 0';
            }
            if ($category !== null && mb_strlen($category) > 50) {
                $errors[] = 'category maks 50 karakter';
            }
            if (!empty($errors)) {
                respond(false, $errors, 'Validation failed', 422);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO products (name, price, stock, category)
                 VALUES (:name, :price, :stock, :category)'
            );
            $stmt->execute([
                ':name'     => $name,
                ':price'    => $price,
                ':stock'    => $stock,
                ':category' => $category,
            ]);
            $newId = (int) $pdo->lastInsertId();

            $fetch = $pdo->prepare('SELECT * FROM products WHERE id = :id');
            $fetch->execute([':id' => $newId]);
            respond(true, castProduct($fetch->fetch()), 'Product created', 201);

        // ─── PUT ───────────────────────────────────────────────────────
        case 'PUT':
            if ($id === null) {
                respond(false, null, 'Parameter id wajib', 400);
            }
            $data = json_decode(file_get_contents('php://input') ?: '', true);
            if (!is_array($data)) {
                respond(false, null, 'Body JSON tidak valid', 400);
            }

            $check = $pdo->prepare('SELECT id FROM products WHERE id = :id');
            $check->execute([':id' => $id]);
            if ($check->fetch() === false) {
                respond(false, null, 'Product not found', 404);
            }

            $name     = isset($data['name']) && is_string($data['name'])  ? trim($data['name']) : '';
            $price    = filter_var($data['price']  ?? null, FILTER_VALIDATE_FLOAT);
            $stock    = filter_var($data['stock']  ?? null, FILTER_VALIDATE_INT);
            $category = isset($data['category']) && is_string($data['category']) ? trim($data['category']) : null;

            $errors = [];
            if ($name === '' || mb_strlen($name) > 100) {
                $errors[] = 'name wajib diisi (maks 100 karakter)';
            }
            if ($price === false || $price < 0) {
                $errors[] = 'price harus angka >= 0';
            }
            if ($stock === false || $stock < 0) {
                $errors[] = 'stock harus integer >= 0';
            }
            if (!empty($errors)) {
                respond(false, $errors, 'Validation failed', 422);
            }

            $stmt = $pdo->prepare(
                'UPDATE products
                 SET name = :name, price = :price, stock = :stock, category = :category
                 WHERE id = :id'
            );
            $stmt->execute([
                ':name'     => $name,
                ':price'    => $price,
                ':stock'    => $stock,
                ':category' => $category,
                ':id'       => $id,
            ]);

            $fetch = $pdo->prepare('SELECT * FROM products WHERE id = :id');
            $fetch->execute([':id' => $id]);
            respond(true, castProduct($fetch->fetch()), 'Product updated');

        // ─── DELETE ────────────────────────────────────────────────────
        case 'DELETE':
            if ($id === null) {
                respond(false, null, 'Parameter id wajib', 400);
            }
            $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
            $stmt->execute([':id' => $id]);
            if ($stmt->rowCount() === 0) {
                respond(false, null, 'Product not found', 404);
            }
            respond(true, ['deleted_id' => $id], 'Product deleted');

        default:
            respond(false, null, 'Method not allowed', 405);
    }
} catch (PDOException $e) {
    error_log('DB error: ' . $e->getMessage());
    respond(false, null, 'Internal server error', 500);
}
