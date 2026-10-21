<?php
/**
 * api/admin_audit.php —— 管理员查看操作审计日志
 * 入参：token, page(默认1), size(默认20), keyword(可选)
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me   = require_role([ROLE_ADMIN]);
$page = max(1, post_int('page', 1));
$size = min(100, max(1, post_int('size', 20)));
$kw   = post_str('keyword');

$pdo = db();
$where = ' WHERE 1=1 ';
$params = [];
if ($kw !== '') {
    $where .= ' AND (action ILIKE ? OR detail ILIKE ?)';
    $params[] = '%' . $kw . '%';
    $params[] = '%' . $kw . '%';
}

$stmt = $pdo->prepare('SELECT count(*) AS cnt FROM operation_log' . $where);
$stmt->execute($params);
$total = (int)$stmt->fetch()['cnt'];

$offset = ($page - 1) * $size;
$stmt = $pdo->prepare(
    'SELECT id, user_type, user_id, action, detail, ip, created_at
       FROM operation_log' . $where . ' ORDER BY id DESC LIMIT ? OFFSET ?'
);
$stmt->execute(array_merge($params, [$size, $offset]));
$list = $stmt->fetchAll();

ok(['list' => $list, 'total' => $total, 'page' => $page, 'total_page' => (int)ceil($total / $size)]);
