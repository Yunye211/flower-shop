<?php
/**
 * api/wallet_info.php —— 用户钱包信息（余额 + 最近流水）
 * 入参：token
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me  = require_role([ROLE_USER]);
$pdo = db();

$stmt = $pdo->prepare('SELECT balance, updated_at FROM wallet WHERE user_id = ?');
$stmt->execute([$me['id']]);
$wallet = $stmt->fetch();
$balance = $wallet ? (float)$wallet['balance'] : 0.0;

$stmt = $pdo->prepare(
    'SELECT type, amount, balance_after, order_id, remark, created_at
       FROM wallet_transaction
      WHERE user_id = ?
      ORDER BY id DESC LIMIT 20'
);
$stmt->execute([$me['id']]);
$txns = $stmt->fetchAll();

$hasSecondPwd = false;
$stmt = $pdo->prepare('SELECT second_password_hash FROM app_user WHERE id = ?');
$stmt->execute([$me['id']]);
$u = $stmt->fetch();
if ($u && $u['second_password_hash'] !== null && $u['second_password_hash'] !== '') {
    $hasSecondPwd = true;
}

ok(['balance' => $balance, 'has_second_password' => $hasSecondPwd, 'transactions' => $txns]);
