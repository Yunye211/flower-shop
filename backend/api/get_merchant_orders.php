<?php
/**
 * api/get_merchant_orders.php —— 商家查询自有商品关联订单
 * v2 优化：通过 order_item.product_id → product.merchant_id 精确关联，
 *          替代原版 goods_text LIKE 模糊匹配（低效且不准确）。
 * 入参：token, page(默认1), size(默认10), status(可选过滤)
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me     = require_role([ROLE_MERCHANT]);
$page   = max(1, post_int('page', 1));
$size   = min(50, max(1, post_int('size', 10)));
$status = post_str('status');

$pdo = db();
$where = ' WHERE EXISTS (
              SELECT 1 FROM order_item oi
              JOIN product p ON p.id = oi.product_id
              WHERE oi.order_id = o.id AND p.merchant_id = ?
          )';
$params = [$me['id']];
if ($status !== '') {
    $where .= ' AND o.status = ?';
    $params[] = $status;
}

$stmt = $pdo->prepare('SELECT count(*) AS cnt FROM orders o' . $where);
$stmt->execute($params);
$total = (int)$stmt->fetch()['cnt'];

$offset = ($page - 1) * $size;
$sql = 'SELECT o.id, o.order_no, o.rece_name, o.rece_phone, o.rece_addr, o.goods_text,
               o.total_price, o.status, o.pay_status, o.created_at,
               (SELECT username FROM app_user u WHERE u.id = o.user_id) AS buyer
          FROM orders o' . $where . ' ORDER BY o.id DESC LIMIT ? OFFSET ?';
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$size, $offset]));
$list = $stmt->fetchAll();

ok(['list' => $list, 'total' => $total, 'page' => $page, 'total_page' => (int)ceil($total / $size)]);
