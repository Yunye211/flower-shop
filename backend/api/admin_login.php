<?php
/**
 * api/admin_login.php —— 管理员登录（默认账号由 seed.php 初始化）
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
$stmt = $pdo->prepare('SELECT id, password_hash, name FROM admin_user WHERE username = ?');
$stmt->execute([$username]);
$a = $stmt->fetch();

if (!$a || !password_verify($password, $a['password_hash'])) {
    audit_log($pdo, ROLE_ADMIN, null, '管理员登录失败', "username={$username}");
    fail('账号或密码错误');
}

$token = issue_token($pdo, (int)$a['id'], ROLE_ADMIN);
audit_log($pdo, ROLE_ADMIN, (int)$a['id'], '管理员登录', "username={$username}");

ok([
    'token' => $token,
    'user'  => ['id' => (int)$a['id'], 'username' => $username, 'name' => $a['name'], 'role' => ROLE_ADMIN],
], '登录成功');
