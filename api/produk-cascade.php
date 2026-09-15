<?php

// api/produk-cascade.php
// JSON endpoint for getting merek by produk, or ukuran by merek.
header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if ($type === 'merek' && $id > 0) {
    $stmt = db()->prepare('SELECT id, merek FROM merek WHERE id_produk = ? AND deleted_at IS NULL ORDER BY merek ASC');
    $stmt->execute([$id]);
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($type === 'ukuran' && $id > 0) {
    $stmt = db()->prepare('SELECT id, ukuran, stok, harga_modal, harga_jual FROM ukuran WHERE id_merek = ? AND deleted_at IS NULL ORDER BY ukuran ASC');
    $stmt->execute([$id]);
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['data' => [], 'error' => 'Invalid request']);
exit;