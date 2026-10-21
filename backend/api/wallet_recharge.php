<?php
/**
 * api/wallet_recharge.php —— 钱包充值（课程演示：模拟支付通道，直接到账）
 * 入参：token, amount
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me     = require_role([ROLE_USER]);
$amount = post_str('amount');
if (!valid_amount($amount) || (float)$amount <= 0) {
    fail('充值金额不合法（需为大于 0 的数字，最多两位小数）');
}
$amount = round((float)$amount, 2);

$pdo = db();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT balance FROM wallet WHERE user_id = ? FOR UPDATE');
    $stmt->execute([$me['id']]);
    $wallet = $stmt->fetch();
    if (!$wallet) {
        // 兜底创建钱包（正常情况下注册触发器已创建）
        $pdo->prepare('INSERT INTO wallet(user_id, balance) VALUES (?, 0) ON CONFLICT DO NOTHING')
            ->execute([$me['id']]);
    }
    $newBalance = round(($wallet ? (float)$wallet['balance'] : 0.0) + $amount, 2);
    $pdo->prepare(
        'INSERT INTO wallet(user_id, balance, updated_at) VALUES (?, ?, now())
         ON CONFLICT (user_id) DO UPDATE SET balance = ?, updated_at = now()'
    )->execute([$me['id'], $newBalance, $newBalance]);

    $pdo->prepare(
        'INSERT INTO wallet_transaction(user_id, type, amount, balance_after, remark)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$me['id'], '充值', $amount, $newBalance, '模拟充值到账']);

    $pdo->commit();
    audit_log($pdo, ROLE_USER, $me['id'], '钱包充值', "amount={$amount}");
    ok(['balance' => $newBalance], '充值成功');
} catch (Throwable $e) {
    $pdo->rollBack();
    fail('充值失败：' . $e->getMessage());
}
