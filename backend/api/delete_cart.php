<?php
/**
 * api/delete_cart.php —— 删除购物车单项
 * 入参：token, cart_id
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me    = require_role([ROLE_USER]);
$cartId = post_int('cart_id', 0);
if ($cartId <= 0) {
    fail('参数 cart_id 缺失');
}

$pdo = db();
// 归属校验后删除
$stmt = $pdo->prepare('DELETE FROM cart WHERE id = ? AND user_id = ?');
$stmt->execute([$cartId, $me['id']]);
if ($stmt->rowCount() === 0) {
    fail('购物车记录不存在');
}
ok([], '删除成功');
