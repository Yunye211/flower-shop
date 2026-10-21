<?php
/**
 * api/ship_order.php —— 商家发货（待发货 → 已发货）
 * 入参：token, order_id
 * 归属校验：订单必须包含本商家商品。
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me    = require_role([ROLE_MERCHANT]);
$orderId = post_int('order_id', 0);
if ($orderId <= 0) {
    fail('参数 order_id 缺失');
}

$pdo = db();

// 归属校验：订单内须有本商家商品
$stmt = $pdo->prepare(
    'SELECT o.id, o.status
       FROM orders o
      WHERE o.id = ?
        AND EXISTS (
            SELECT 1 FROM order_item oi
            JOIN product p ON p.id = oi.product_id
            WHERE oi.order_id = o.id AND p.merchant_id = ?
        )'
);
$stmt->execute([$orderId, $me['id']]);
$order = $stmt->fetch();
if (!$order) {
    fail('订单不存在或与您的店铺无关', 403);
}
if ($order['status'] !== '待发货') {
    fail('当前订单状态（' . $order['status'] . '）不可发货');
}

$stmt = $pdo->prepare(
    "UPDATE orders SET status = '已发货', shipped_at = now() WHERE id = ? AND status = '待发货'"
);
$stmt->execute([$orderId]);
if ($stmt->rowCount() === 0) {
    fail('发货失败：订单状态已变化，请刷新后重试');
}
audit_log($pdo, ROLE_MERCHANT, $me['id'], '商家发货', "order_id={$orderId}");
ok([], '发货成功，等待外卖员接单');
