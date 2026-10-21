<?php
/**
 * api/get_courier_orders.php —— 外卖员订单列表
 * 入参：token, scope(可选 'pool'=可接单列表 / 'mine'=我的配送单, 默认 mine)
 *       page(默认1), size(默认10)
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me    = require_role([ROLE_COURIER]);
$scope = post_str('scope', 'mine');
$page  = max(1, post_int('page', 1));
$size  = min(50, max(1, post_int('size', 10)));

$pdo = db();
if ($scope === 'pool') {
    // 可接单：商家已发货且尚未被接单
    $where  = " WHERE o.status = '已发货' AND o.courier_id IS NULL";
    $params = [];
} else {
    $where  = ' WHERE o.courier_id = ? AND o.status IN (\'配送中\', \'已送达\')';
    $params = [$me['id']];
}

$stmt = $pdo->prepare('SELECT count(*) AS cnt FROM orders o' . $where);
$stmt->execute($params);
$total = (int)$stmt->fetch()['cnt'];

$offset = ($page - 1) * $size;
$sql = 'SELECT o.id, o.order_no, o.rece_name, o.rece_phone, o.rece_addr, o.goods_text,
               o.total_price, o.status, o.pay_status, o.created_at, o.shipped_at
          FROM orders o' . $where . ' ORDER BY o.id ASC LIMIT ? OFFSET ?';
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$size, $offset]));
$list = $stmt->fetchAll();

ok(['list' => $list, 'total' => $total, 'page' => $page, 'total_page' => (int)ceil($total / $size)]);
