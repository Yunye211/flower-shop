<?php
/**
 * api/courier_update_status.php —— 外卖员更新配送状态（配送中 → 已送达）
 * 入参：token, order_id
 * 只能更新本人接单的订单。
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
    "UPDATE orders SET status = '已送达', delivered_at = now()
      WHERE id = ? AND courier_id = ? AND status = '配送中'"
);
$stmt->execute([$orderId, $me['id']]);
if ($stmt->rowCount() === 0) {
    fail('更新失败：订单不存在、非本人配送单或状态不可达');
}
audit_log($pdo, ROLE_COURIER, $me['id'], '配送完成', "order_id={$orderId}");
ok([], '已确认送达');
