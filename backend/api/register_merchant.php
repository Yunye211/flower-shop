<?php
/**
 * api/register_merchant.php —— 商家注册
 * 入参：username, password, shop_name, phone(可选)
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$username = post_str('username');
$password = post_str('password');
$shopName = post_str('shop_name');
$phone    = post_str('phone');

if (!valid_username($username)) {
    fail('用户名需为 3~20 位字母、数字或下划线');
}
if (!valid_password($password)) {
    fail('密码至少 8 位，且需同时包含字母和数字');
}
if ($shopName === '' || mb_strlen($shopName) > 30) {
    fail('店铺名称不能为空且不超过 30 字');
}
if (!valid_phone($phone)) {
    fail('手机号格式不正确');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM merchant WHERE username = ?');
$stmt->execute([$username]);
if ($stmt->fetch()) {
    fail('用户名已存在');
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$pdo->prepare(
    'INSERT INTO merchant(username, password_hash, shop_name, phone) VALUES (?, ?, ?, ?)'
)->execute([$username, $hash, $shopName, $phone ?: null]);

audit_log($pdo, ROLE_MERCHANT, (int)$pdo->lastInsertId(), '商家注册', "username={$username}");
ok([], '商家注册成功，请登录');
