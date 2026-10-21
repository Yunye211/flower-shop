<?php
/**
 * api/cancel_order.php —— 用户取消订单（仅限未发货且未支付的订单；已支付则需退款）
 * 入参：token, order_id, reason(可选)
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me     = require_role([ROLE_USER]);
$orderId = post_int('order_id', 0);
$reason = post_str('reason');
if ($orderId <= 0) {
    fail('参数 order_id 缺失');
}

$pdo = db();

// 归属校验：只能取消自己的订单
$stmt = $pdo->prepare('SELECT id, status, pay_status, total_price, user_id FROM orders WHERE id = ?');
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order || (int)$order['user_id'] !== $me['id']) {
    fail('订单不存在', 403);
}
if ($order['status'] !== '待发货') {
    fail('订单已发货或已取消，无法取消');
}

$pdo->beginTransaction();
try {
    // 若已钱包支付，则退款回钱包
    if ($order['pay_status'] === '已支付') {
        $stmt = $pdo->prepare('SELECT balance FROM wallet WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$me['id']]);
        $wallet = $stmt->fetch();
        $newBalance = round((float)($wallet['balance'] ?? 0) + (float)$order['total_price'], 2);
        $pdo->prepare('UPDATE wallet SET balance = ?, updated_at = now() WHERE user_id = ?')
            ->execute([$newBalance, $me['id']]);
        $pdo->prepare(
            'INSERT INTO wallet_transaction(user_id, type, amount, balance_after, order_id, remark)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$me['id'], '退款', $order['total_price'], $newBalance, $orderId, '订单取消退款 ' . $reason]);
    }

    // 回补库存
    $items = $pdo->prepare('SELECT product_id, num FROM order_item WHERE order_id = ?');
    $items->execute([$orderId]);
    $restore = $pdo->prepare('UPDATE product SET stock = stock + ? WHERE id = ?');
    foreach ($items->fetchAll() as $it) {
        if ($it['product_id'] !== null) {
            $restore->execute([$it['num'], $it['product_id']]);
        }
    }

    $pdo->prepare(
        "UPDATE orders SET status = '已取消', cancel_reason = ? WHERE id = ? AND status = '待发货'"
    )->execute([$reason ?: '用户取消', $orderId]);

    $pdo->commit();
    audit_log($pdo, ROLE_USER, $me['id'], '取消订单', "order_id={$orderId}, reason={$reason}");
    ok([], '订单已取消，库存与金额已返还');
} catch (Throwable $e) {
    $pdo->rollBack();
    fail('取消失败：' . $e->getMessage());
}
