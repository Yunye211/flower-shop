<?php
/**
 * api/delete_product.php —— 商家删除自有商品（软删除：置为下架，保留订单快照）
 * 入参：token, product_id
 * v2 优化：原版为物理删除，会导致历史订单快照失真；改为 status=0 软删除。
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me  = require_role([ROLE_MERCHANT]);
$pid = post_int('product_id', 0);
if ($pid <= 0) {
    fail('参数 product_id 缺失');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, merchant_id FROM product WHERE id = ?');
$stmt->execute([$pid]);
$prod = $stmt->fetch();
if (!$prod) {
    fail('商品不存在');
}
if ((int)$prod['merchant_id'] !== $me['id']) {
    fail('无权限操作该商品', 403);
}

$pdo->prepare('UPDATE product SET status = 0 WHERE id = ?')->execute([$pid]);
audit_log($pdo, ROLE_MERCHANT, $me['id'], '商品删除(下架)', "product_id={$pid}");
ok([], '删除成功（商品已下架）');
