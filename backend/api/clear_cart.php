<?php
/**
 * api/clear_cart.php —— 清空购物车
 * 入参：token
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me = require_role([ROLE_USER]);
$pdo = db();
$pdo->prepare('DELETE FROM cart WHERE user_id = ?')->execute([$me['id']]);
ok([], '购物车已清空');
