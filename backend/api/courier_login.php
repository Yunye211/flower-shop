<?php
/**
 * api/courier_login.php —— 外卖员登录
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
$stmt = $pdo->prepare('SELECT id, password_hash, name, status FROM delivery_person WHERE username = ?');
$stmt->execute([$username]);
$c = $stmt->fetch();

if (!$c || !password_verify($password, $c['password_hash'])) {
    fail('账号或密码错误');
}
if ((int)$c['status'] !== 1) {
    fail('账号已被禁用，请联系管理员');
}

$token = issue_token($pdo, (int)$c['id'], ROLE_COURIER);
audit_log($pdo, ROLE_COURIER, (int)$c['id'], '外卖员登录', "username={$username}");

ok([
    'token' => $token,
    'user'  => ['id' => (int)$c['id'], 'username' => $username, 'name' => $c['name'], 'role' => ROLE_COURIER],
], '登录成功');
