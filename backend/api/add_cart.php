<?php
/**
 * api/add_cart.php —— 加入购物车（同一商品已存在则数量+1，否则新增）
 * 入参：token, product_id, num(可选，默认1)
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me    = require_role([ROLE_USER]);
$pid   = post_int('product_id', 0);
$num   = max(1, post_int('num', 1));
if ($pid <= 0) {
    fail('参数 product_id 缺失');
}

$pdo = db();

// 商品必须存在且在售
$stmt = $pdo->prepare('SELECT id, status, stock FROM product WHERE id = ?');
$stmt->execute([$pid]);
$prod = $stmt->fetch();
if (!$prod || (int)$prod['status'] !== 1) {
    fail('商品不存在或已下架');
}
if ($prod['stock'] <= 0) {
    fail('商品库存不足');
}

// 存在性检查：存在则数量+num，否则新增（UNIQUE 约束兜底防重复行）
$stmt = $pdo->prepare('SELECT id, num FROM cart WHERE user_id = ? AND product_id = ?');
$stmt->execute([$me['id'], $pid]);
$cart = $stmt->fetch();
if ($cart) {
    $newNum = (int)$cart['num'] + $num;
    $pdo->prepare('UPDATE cart SET num = ? WHERE id = ?')->execute([$newNum, $cart['id']]);
} else {
    $pdo->prepare('INSERT INTO cart(user_id, product_id, num) VALUES (?, ?, ?)')
        ->execute([$me['id'], $pid, $num]);
}
ok([], '加入购物车成功');
