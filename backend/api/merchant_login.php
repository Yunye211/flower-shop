<?php
/**
 * api/merchant_login.php —— 商家登录
 * 入参：username, password
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$username = post_str('username');
$password = post_str('password');
if ($username === '' || $password === '') {
    fail('用户名和密码不能为空');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, password_hash, shop_name, status FROM merchant WHERE username = ?');
$stmt->execute([$username]);
$m = $stmt->fetch();

if (!$m || !password_verify($password, $m['password_hash'])) {
    fail('账号或密码错误');
}
if ((int)$m['status'] !== 1) {
    fail('账号已被禁用，请联系管理员');
}

$token = issue_token($pdo, (int)$m['id'], ROLE_MERCHANT);
audit_log($pdo, ROLE_MERCHANT, (int)$m['id'], '商家登录', "username={$username}");

ok([
    'token' => $token,
    'user'  => ['id' => (int)$m['id'], 'username' => $username, 'shop_name' => $m['shop_name'], 'role' => ROLE_MERCHANT],
], '登录成功');
