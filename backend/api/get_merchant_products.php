<?php
/**
 * api/get_merchant_products.php —— 商家自有商品列表（含下架商品）
 * 入参：token, page(默认1), size(默认20)
 * 行级数据隔离：仅返回 merchant_id 匹配数据。
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me   = require_role([ROLE_MERCHANT]);
$page = max(1, post_int('page', 1));
$size = min(100, max(1, post_int('size', 20)));

$pdo = db();
$stmt = $pdo->prepare('SELECT count(*) AS cnt FROM product WHERE merchant_id = ?');
$stmt->execute([$me['id']]);
$total = (int)$stmt->fetch()['cnt'];

$offset = ($page - 1) * $size;
$stmt = $pdo->prepare(
    'SELECT id, name, price, img, description, stock, status, created_at
       FROM product
      WHERE merchant_id = ?
      ORDER BY id DESC
      LIMIT ? OFFSET ?'
);
$stmt->execute([$me['id'], $size, $offset]);
$list = $stmt->fetchAll();

ok([
    'list'       => $list,
    'total'      => $total,
    'page'       => $page,
    'total_page' => (int)ceil($total / $size),
]);
