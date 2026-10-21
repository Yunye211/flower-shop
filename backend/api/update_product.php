<?php
/**
 * api/update_product.php —— 商家修改自有商品 / 上下架切换
 * 入参：token, product_id, name(可选), price(可选), img(可选), description(可选),
 *       stock(可选), status(可选 0下架/1上架)
 * 仅允许操作 merchant_id 匹配的自有商品（行级数据隔离）。
 * 安全要点：仅处理实际提交的字段（array_key_exists），未提交字段不写入，避免误清零。
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me  = require_role([ROLE_MERCHANT]);
$pid = post_int('product_id', 0);
if ($pid <= 0) {
    fail('参数 product_id 缺失');
}

$pdo = db();

// 归属校验：只能修改自有商品
$stmt = $pdo->prepare('SELECT id, merchant_id FROM product WHERE id = ?');
$stmt->execute([$pid]);
$prod = $stmt->fetch();
if (!$prod) {
    fail('商品不存在');
}
if ((int)$prod['merchant_id'] !== $me['id']) {
    fail('无权限操作该商品', 403);
}

// 仅收集实际提交的字段
$sets   = [];
$params = [];
$submitted = [
    'name'        => array_key_exists('name', $_POST),
    'price'       => array_key_exists('price', $_POST),
    'img'         => array_key_exists('img', $_POST),
    'description' => array_key_exists('description', $_POST),
    'stock'       => array_key_exists('stock', $_POST),
    'status'      => array_key_exists('status', $_POST),
];

if ($submitted['name']) {
    $v = post_str('name');
    if ($v === '' || mb_strlen($v) > 100) {
        fail('商品名称不合法');
    }
    $sets[] = 'name = ?';
    $params[] = $v;
}
if ($submitted['price']) {
    $v = post_str('price');
    if (!valid_amount($v)) {
        fail('商品价格不合法（需为非负数字，最多两位小数）');
    }
    $sets[] = 'price = ?';
    $params[] = $v;
}
if ($submitted['img']) {
    $sets[] = 'img = ?';
    $params[] = post_str('img');
}
if ($submitted['description']) {
    $sets[] = 'description = ?';
    $params[] = post_str('description');
}
if ($submitted['stock']) {
    $v = post_int('stock');
    if ($v < 0) {
        fail('库存不能为负数');
    }
    $sets[] = 'stock = ?';
    $params[] = $v;
}
if ($submitted['status']) {
    $v = post_int('status');
    if ($v !== 0 && $v !== 1) {
        fail('状态参数不合法');
    }
    $sets[] = 'status = ?';
    $params[] = $v;
}

if (!$sets) {
    fail('没有需要修改的字段');
}

$params[] = $pid;
$pdo->prepare('UPDATE product SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
audit_log($pdo, ROLE_MERCHANT, $me['id'], '商品修改', "product_id={$pid}");
ok([], '商品更新成功');
