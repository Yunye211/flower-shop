<?php
/**
 * api/update_cart.php —— 修改购物车商品数量
 * 入参：token, cart_id, num(>0)
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me    = require_role([ROLE_USER]);
$cartId = post_int('cart_id', 0);
$num   = post_int('num', 0);
if ($cartId <= 0 || $num <= 0) {
    fail('参数不合法：cart_id 与数量必须为正整数');
}

$pdo = db();

// 购物车项归属校验（只能改自己的）
$stmt = $pdo->prepare('SELECT id, product_id FROM cart WHERE id = ? AND user_id = ?');
$stmt->execute([$cartId, $me['id']]);
$item = $stmt->fetch();
if (!$item) {
    fail('购物车记录不存在');
}

// 数量上限校验：不超过库存
$stmt = $pdo->prepare('SELECT stock, status FROM product WHERE id = ?');
$stmt->execute([$item['product_id']]);
$prod = $stmt->fetch();
if (!$prod || (int)$prod['status'] !== 1) {
    fail('商品不存在或已下架');
}
if ((int)$num > (int)$prod['stock']) {
    fail('修改数量超过库存（当前库存 ' . $prod['stock'] . '）');
}

$pdo->prepare('UPDATE cart SET num = ? WHERE id = ?')->execute([$num, $cartId]);
ok([], '购物车已更新');
