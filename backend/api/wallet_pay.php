<?php
/**
 * api/wallet_pay.php —— 钱包支付未支付订单（二级密码校验）
 * 入参：token, order_id, second_password
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me       = require_role([ROLE_USER]);
$orderId  = post_int('order_id', 0);
$secondPwd = post_str('second_password');
if ($orderId <= 0) {
    fail('参数 order_id 缺失');
}
if ($secondPwd === '') {
    fail('请先设置并输入二级密码');
}

$pdo = db();
$pdo->beginTransaction();
try {
    // 归属与状态校验
    $stmt = $pdo->prepare(
        'SELECT id, total_price, status, pay_status, user_id FROM orders WHERE id = ? FOR UPDATE'
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order || (int)$order['user_id'] !== $me['id']) {
        throw new RuntimeException('订单不存在', 403);
    }
    if ($order['status'] === '已取消') {
        throw new RuntimeException('订单已取消，无法支付');
    }
    if ($order['pay_status'] === '已支付') {
        throw new RuntimeException('订单已支付，请勿重复支付');
    }

    // 二级密码校验（含防暴力破解）
    $check = check_second_password($pdo, $me['id'], $secondPwd);
    if (!$check['ok']) {
        throw new RuntimeException($check['msg']);
    }

    // 余额扣款
    $total = (float)$order['total_price'];
    $stmt = $pdo->prepare('SELECT balance FROM wallet WHERE user_id = ? FOR UPDATE');
    $stmt->execute([$me['id']]);
    $wallet = $stmt->fetch();
    if (!$wallet || (float)$wallet['balance'] < $total) {
        throw new RuntimeException('钱包余额不足，请先充值');
    }
    $newBalance = round((float)$wallet['balance'] - $total, 2);
    $pdo->prepare('UPDATE wallet SET balance = ?, updated_at = now() WHERE user_id = ?')
        ->execute([$newBalance, $me['id']]);
    $pdo->prepare(
        'INSERT INTO wallet_transaction(user_id, type, amount, balance_after, order_id, remark)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$me['id'], '支付', -$total, $newBalance, $orderId, '订单支付']);

    $pdo->prepare(
        "UPDATE orders SET pay_status = '已支付', paid_at = now() WHERE id = ?"
    )->execute([$orderId]);

    $pdo->commit();
    audit_log($pdo, ROLE_USER, $me['id'], '钱包支付', "order_id={$orderId}, amount={$total}");
    ok(['balance' => $newBalance], '支付成功');
} catch (Throwable $e) {
    $pdo->rollBack();
    fail($e->getMessage(), $e->getCode() === 403 ? 403 : 200);
}
