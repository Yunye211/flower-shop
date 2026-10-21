<?php
/**
 * api/login.php —— 普通用户登录
 * v2 优化：密码校验（password_verify）+ token 签发 + 审计日志。
 * 入参：username, password
 * 返回：token（前端存 localStorage，请求时随 Authorization 头携带）
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
$stmt = $pdo->prepare('SELECT id, password_hash, status FROM app_user WHERE username = ?');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    audit_log($pdo, ROLE_USER, null, '登录失败', "username={$username}");
    fail('账号或密码错误');
}
if ((int)$user['status'] !== 1) {
    fail('账号已被禁用，请联系管理员');
}

$token = issue_token($pdo, (int)$user['id'], ROLE_USER);
audit_log($pdo, ROLE_USER, (int)$user['id'], '用户登录', "username={$username}");

ok([
    'token' => $token,
    'user'  => [
        'id'       => (int)$user['id'],
        'username' => $username,
        'role'     => ROLE_USER,
    ],
], '登录成功');
