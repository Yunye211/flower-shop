<?php
/**
 * api/set_second_password.php —— 设置/修改二级密码
 * 入参：token, second_password, old_second_password(修改时必填)
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me      = require_role([ROLE_USER]);
$newPwd  = post_str('second_password');
$oldPwd  = post_str('old_second_password');

if (!valid_password($newPwd)) {
    fail('二级密码需至少 8 位且包含字母和数字');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT second_password_hash FROM app_user WHERE id = ?');
$stmt->execute([$me['id']]);
$u = $stmt->fetch();

if ($u && $u['second_password_hash'] !== null && $u['second_password_hash'] !== '') {
    // 已设置：必须验证旧二级密码
    if (!password_verify($oldPwd, $u['second_password_hash'])) {
        fail('原二级密码错误');
    }
}

$hash = password_hash($newPwd, PASSWORD_BCRYPT);
$pdo->prepare('UPDATE app_user SET second_password_hash = ? WHERE id = ?')
    ->execute([$hash, $me['id']]);

// 设置成功后清零失败锁定记录
$pdo->prepare('DELETE FROM second_pwd_fail WHERE user_id = ?')->execute([$me['id']]);

audit_log($pdo, ROLE_USER, $me['id'], '设置二级密码');
ok([], '二级密码设置成功');
