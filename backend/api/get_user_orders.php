<?php
/**
 * api/get_user_orders.php —— 用户查询本人订单
 * 入参：token, page(默认1), size(默认10)
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me   = require_role([ROLE_USER]);
$page = max(1, post_int('page', 1));
$size = min(50, max(1, post_int('size', 10)));

$pdo = db();
$stmt = $pdo->prepare('SELECT count(*) AS cnt FROM orders WHERE user_id = ?');
$stmt->execute([$me['id']]);
$total = (int)$stmt->fetch()['cnt'];

$offset = ($page - 1) * $size;
$stmt = $pdo->prepare(
    'SELECT id, order_no, rece_name, rece_phone, rece_addr, goods_text,
            total_price, status, pay_status, created_at,
            (SELECT name FROM delivery_person d WHERE d.id = o.courier_id) AS courier_name
       FROM orders o
      WHERE user_id = ?
      ORDER BY id DESC
      LIMIT ? OFFSET ?'
);
$stmt->execute([$me['id'], $size, $offset]);
$list = $stmt->fetchAll();

ok(['list' => $list, 'total' => $total, 'page' => $page, 'total_page' => (int)ceil($total / $size)]);
