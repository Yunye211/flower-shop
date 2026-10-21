<?php
/**
 * api/register_courier.php —— 外卖员注册
 * 入参：username, password, name, phone(可选)
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$username = post_str('username');
$password = post_str('password');
$name     = post_str('name');
$phone    = post_str('phone');

if (!valid_username($username)) {
    fail('用户名需为 3~20 位字母、数字或下划线');
}
if (!valid_password($password)) {
    fail('密码至少 8 位，且需同时包含字母和数字');
}
if ($name === '' || mb_strlen($name) > 30) {
    fail('姓名不能为空且不超过 30 字');
}
if (!valid_phone($phone)) {
    fail('手机号格式不正确');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM delivery_person WHERE username = ?');
$stmt->execute([$username]);
if ($stmt->fetch()) {
    fail('用户名已存在');
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$pdo->prepare(
    'INSERT INTO delivery_person(username, password_hash, name, phone) VALUES (?, ?, ?, ?)'
)->execute([$username, $hash, $name, $phone ?: null]);

audit_log($pdo, ROLE_COURIER, (int)$pdo->lastInsertId(), '外卖员注册', "username={$username}");
ok([], '外卖员注册成功，请登录');
