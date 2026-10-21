<?php
/**
 * api/get_cart.php —— 查询用户购物车（JOIN 实时价格与库存）
 * 入参：token
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me = require_role([ROLE_USER]);

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT c.id, c.product_id, c.num,
            p.name, p.price, p.img, p.stock, p.status AS product_status
       FROM cart c
       JOIN product p ON p.id = c.product_id
      WHERE c.user_id = ?
      ORDER BY c.id DESC'
);
$stmt->execute([$me['id']]);
$list = $stmt->fetchAll();

// 计算合计（前端也可自行计算，这里统一返回）
$total = calc_total($list);
ok(['list' => $list, 'total' => $total]);
