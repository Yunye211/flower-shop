<?php
/**
 * api/admin_users.php —— 管理员查看平台用户列表（用户/商家/外卖员）
 * 入参：token, role(user|merchant|courier), page(默认1), size(默认20), keyword(可选)
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me   = require_role([ROLE_ADMIN]);
$role = post_str('role', 'user');
$page = max(1, post_int('page', 1));
$size = min(100, max(1, post_int('size', 20)));
$kw   = post_str('keyword');

$tableMap = ['user' => 'app_user', 'merchant' => 'merchant', 'courier' => 'delivery_person'];
if (!isset($tableMap[$role])) {
    fail('角色参数不合法');
}
$table = $tableMap[$role];

$pdo = db();
$where = ' WHERE 1=1 ';
$params = [];
if ($kw !== '') {
    $where .= ' AND username ILIKE ?';
    $params[] = '%' . $kw . '%';
}

$stmt = $pdo->prepare("SELECT count(*) AS cnt FROM $table" . $where);
$stmt->execute($params);
$total = (int)$stmt->fetch()['cnt'];

$offset = ($page - 1) * $size;
$stmt = $pdo->prepare(
    "SELECT id, username, phone, status, created_at FROM $table" . $where . ' ORDER BY id DESC LIMIT ? OFFSET ?'
);
$stmt->execute(array_merge($params, [$size, $offset]));
$list = $stmt->fetchAll();

ok(['list' => $list, 'total' => $total, 'page' => $page, 'total_page' => (int)ceil($total / $size)]);
