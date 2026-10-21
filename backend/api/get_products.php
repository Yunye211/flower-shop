<?php
/**
 * api/get_products.php —— 分页查询在售商品（用户端首页）
 * 入参：page(默认1), size(默认8), keyword(可选，按名称模糊搜索)
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';

$page = max(1, post_int('page', 1));
$size = min(50, max(1, post_int('size', 8)));
$kw   = post_str('keyword');

$pdo = db();
$where = ' WHERE p.status = 1';
$params = [];
if ($kw !== '') {
    $where .= ' AND p.name ILIKE ?';
    $params[] = '%' . $kw . '%';
}

// 总数
$stmt = $pdo->prepare('SELECT count(*) AS cnt FROM product p' . $where);
$stmt->execute($params);
$total = (int)$stmt->fetch()['cnt'];

// 分页列表（带商家名）
$offset = ($page - 1) * $size;
$sql = 'SELECT p.id, p.name, p.price, p.img, p.description, p.stock, p.merchant_id,
               m.shop_name AS merchant_name
          FROM product p
          JOIN merchant m ON m.id = p.merchant_id'
      . $where . ' ORDER BY p.id DESC LIMIT ? OFFSET ?';
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$size, $offset]));
$list = $stmt->fetchAll();

ok([
    'list'       => $list,
    'total'      => $total,
    'page'       => $page,
    'size'       => $size,
    'total_page' => (int)ceil($total / $size),
]);
