<?php
/**
 * api/register.php —— 普通用户注册
 * v2 优化：密码哈希存储、用户名/密码/手机号校验、预处理语句防注入。
 * 入参：username, password, phone(可选)
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$username = post_str('username');
$password = post_str('password');
$phone    = post_str('phone');

if (!valid_username($username)) {
    fail('用户名需为 3~20 位字母、数字或下划线');
}
if (!valid_password($password)) {
    fail('密码至少 8 位，且需同时包含字母和数字');
}
if (!valid_phone($phone)) {
    fail('手机号格式不正确');
}

$pdo = db();

// 用户名唯一性校验
$stmt = $pdo->prepare('SELECT id FROM app_user WHERE username = ?');
$stmt->execute([$username]);
if ($stmt->fetch()) {
    fail('用户名已存在');
}

// 密码哈希存储（绝不存明文）
$hash = password_hash($password, PASSWORD_BCRYPT);
$pdo->prepare(
    'INSERT INTO app_user(username, password_hash, phone) VALUES (?, ?, ?)'
)->execute([$username, $hash, $phone ?: null]);

// 触发器自动创建钱包
audit_log($pdo, ROLE_USER, (int)$pdo->lastInsertId(), '用户注册', "username={$username}");
ok([], '注册成功，请登录');
