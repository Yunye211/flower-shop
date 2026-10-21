<?php
/**
 * api/admin_orders.php —— 管理员查看全部订单
 * 入参：token, page(默认1), size(默认10), status(可选), keyword(可选，订单号模糊)
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me   = require_role([ROLE_ADMIN]);
$page = max(1, post_int('page', 1));
$size = min(100, max(1, post_int('size', 10)));
$status = post_str('status');
$kw   = post_str('keyword');

$pdo = db();
$where = ' WHERE 1=1 ';
$params = [];
if ($status !== '') {
    $where .= ' AND o.status = ?';
    $params[] = $status;
}
if ($kw !== '') {
    $where .= ' AND o.order_no ILIKE ?';
    $params[] = '%' . $kw . '%';
}

$stmt = $pdo->prepare('SELECT count(*) AS cnt FROM orders o' . $where);
$stmt->execute($params);
$total = (int)$stmt->fetch()['cnt'];

$offset = ($page - 1) * $size;
$sql = 'SELECT o.id, o.order_no, o.rece_name, o.rece_phone, o.rece_addr, o.goods_text,
               o.total_price, o.status, o.pay_status, o.created_at,
               (SELECT username FROM app_user u WHERE u.id = o.user_id) AS buyer,
               (SELECT name FROM delivery_person d WHERE d.id = o.courier_id) AS courier_name
          FROM orders o' . $where . ' ORDER BY o.id DESC LIMIT ? OFFSET ?';
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$size, $offset]));
$list = $stmt->fetchAll();

ok(['list' => $list, 'total' => $total, 'page' => $page, 'total_page' => (int)ceil($total / $size)]);
