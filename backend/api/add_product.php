<?php
/**
 * api/add_product.php —— 商家上架商品（仅自有店铺）
 * 入参：token, name, price, img(可选), description(可选), stock(可选默认0)
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me = require_role([ROLE_MERCHANT]);

$name  = post_str('name');
$price = post_str('price');
$img   = post_str('img');
$desc  = post_str('description');
$stock = post_int('stock', 0);

if ($name === '' || mb_strlen($name) > 100) {
    fail('商品名称不能为空且不超过 100 字');
}
if (!valid_amount($price)) {
    fail('商品价格不合法（需为非负数字，最多两位小数）');
}
if ($stock < 0) {
    fail('库存不能为负数');
}

$pdo = db();
$pdo->prepare(
    'INSERT INTO product(name, price, img, description, stock, merchant_id, status)
     VALUES (?, ?, ?, ?, ?, ?, 1)'
)->execute([$name, $price, $img ?: null, $desc ?: null, $stock, $me['id']]);

$pid = (int)$pdo->lastInsertId();
audit_log($pdo, ROLE_MERCHANT, $me['id'], '商品上架', "product_id={$pid}, name={$name}");
ok(['product_id' => $pid], '商品上架成功');
