<?php
/**
 * api/courier_take_order.php —— 外卖员接单（已发货 → 配送中）
 * 入参：token, order_id
 * 并发安全：条件更新（status='已发货' AND courier_id IS NULL），防止多人同时接同一单。
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me      = require_role([ROLE_COURIER]);
$orderId = post_int('order_id', 0);
if ($orderId <= 0) {
    fail('参数 order_id 缺失');
}

$pdo = db();
$stmt = $pdo->prepare(
    "UPDATE orders SET courier_id = ?, status = '配送中'
      WHERE id = ? AND status = '已发货' AND courier_id IS NULL"
);
$stmt->execute([$me['id'], $orderId]);
if ($stmt->rowCount() === 0) {
    fail('接单失败：该订单已被其他外卖员接走或状态已变化');
}
audit_log($pdo, ROLE_COURIER, $me['id'], '外卖员接单', "order_id={$orderId}");
ok([], '接单成功，请尽快配送');
