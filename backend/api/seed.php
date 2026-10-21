<?php
/**
 * api/seed.php —— 初始化演示数据（部署后执行一次）
 * 创建默认账号 + 示例商品 + 示例钱包余额，密码全部运行时哈希，绝不写死明文。
 * 用法：php api/seed.php   （在 backend 目录下执行）
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$pdo = db();

echo "开始初始化演示数据...\n";

// ---------- 管理员 ----------
$stmt = $pdo->prepare('SELECT count(*) FROM admin_user WHERE username = ?');
$stmt->execute(['admin']);
if ((int)$stmt->fetchColumn() === 0) {
    $pdo->prepare('INSERT INTO admin_user(username, password_hash, name) VALUES (?, ?, ?)')
        ->execute(['admin', password_hash('admin123', PASSWORD_BCRYPT), '系统管理员']);
    echo "已创建管理员 admin / admin123\n";
} else {
    echo "管理员 admin 已存在，跳过\n";
}

// ---------- 用户 ----------
$user = [
    ['user01', 'user123', '13800000001'],
    ['user02', 'user123', '13800000002'],
];
$stmt = $pdo->prepare('SELECT count(*) FROM app_user WHERE username = ?');
$ins  = $pdo->prepare('INSERT INTO app_user(username, password_hash, phone) VALUES (?, ?, ?)');
foreach ($user as $u) {
    $stmt->execute([$u[0]]);
    if ((int)$stmt->fetchColumn() === 0) {
        $ins->execute([$u[0], password_hash($u[1], PASSWORD_BCRYPT), $u[2]]);
        echo "已创建用户 {$u[0]} / {$u[1]}\n";
    } else {
        echo "用户 {$u[0]} 已存在，跳过\n";
    }
}
// 给 user01 二级密码与初始余额
$stmt = $pdo->prepare('UPDATE app_user SET second_password_hash = ? WHERE username = ?');
$stmt->execute([password_hash('pwd12345', PASSWORD_BCRYPT), 'user01']);
$stmt = $pdo->prepare('SELECT id FROM app_user WHERE username = ?');
$stmt->execute(['user01']);
$uid = (int)$stmt->fetchColumn();
$pdo->prepare(
    'INSERT INTO wallet(user_id, balance) VALUES (?, 100.00)
     ON CONFLICT (user_id) DO UPDATE SET balance = EXCLUDED.balance'
)->execute([$uid]);
echo "user01 二级密码 pwd12345，初始余额 100 元\n";

// ---------- 商家 ----------
$merchants = [
    ['shop01', 'shop123', '爱心鲜花店'],
    ['shop02', 'shop123', '香语花坊'],
];
$stmt = $pdo->prepare('SELECT count(*) FROM merchant WHERE username = ?');
$insM = $pdo->prepare('INSERT INTO merchant(username, password_hash, shop_name, phone) VALUES (?, ?, ?, ?)');
foreach ($merchants as $m) {
    $stmt->execute([$m[0]]);
    if ((int)$stmt->fetchColumn() === 0) {
        $insM->execute([$m[0], password_hash($m[1], PASSWORD_BCRYPT), $m[2], '1390000000' . rand(10, 99)]);
        echo "已创建商家 {$m[0]} / {$m[1]}（{$m[2]}）\n";
    } else {
        echo "商家 {$m[0]} 已存在，跳过\n";
    }
}

// ---------- 外卖员 ----------
$couriers = [
    ['courier01', 'courier123', '小王'],
    ['courier02', 'courier123', '小李'],
];
$stmt = $pdo->prepare('SELECT count(*) FROM delivery_person WHERE username = ?');
$insC = $pdo->prepare('INSERT INTO delivery_person(username, password_hash, name, phone) VALUES (?, ?, ?, ?)');
foreach ($couriers as $c) {
    $stmt->execute([$c[0]]);
    if ((int)$stmt->fetchColumn() === 0) {
        $insC->execute([$c[0], password_hash($c[1], PASSWORD_BCRYPT), $c[2], '1370000000' . rand(10, 99)]);
        echo "已创建外卖员 {$c[0]} / {$c[1]}（{$c[2]}）\n";
    } else {
        echo "外卖员 {$c[0]} 已存在，跳过\n";
    }
}

// ---------- 示例商品（由 shop01 提供） ----------
$stmt = $pdo->prepare('SELECT id FROM merchant WHERE username = ?');
$stmt->execute(['shop01']);
$shopId = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT count(*) FROM product WHERE merchant_id = ?');
$stmt->execute([$shopId]);
if ((int)$stmt->fetchColumn() === 0 && $shopId > 0) {
    $products = [
        ['红玫瑰花束', 99.90, 50,  '11 朵红玫瑰，节日表白首选'],
        ['向日葵花束', 69.00, 80,  '阳光向日葵，温暖每一天'],
        ['满天星干花', 39.90, 100, '文艺干花，可长期保存'],
        ['康乃馨花篮', 129.00, 30, '母亲节慰问花篮'],
        ['百合花束', 89.00, 40,   '白百合，纯洁高雅'],
        ['开业花篮', 199.00, 20,  '企业开业庆典花篮'],
        ['香槟玫瑰', 79.90, 60,   '香槟色玫瑰，低调浪漫'],
        ['绿植盆栽', 45.00, 90,   '办公室绿萝盆栽'],
    ];
    $insP = $pdo->prepare(
        'INSERT INTO product(name, price, img, description, stock, merchant_id, status)
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    );
    foreach ($products as $idx => $p) {
        $insP->execute([
            $p[0], $p[1],
            '/img/' . ($idx + 1) . '.svg',
            $p[3], $p[2], $shopId,
        ]);
    }
    echo '已创建 8 个示例商品（爱心鲜花店）' . "\n";
} else {
    echo "示例商品已存在或商家不存在，跳过\n";
}

echo "\n演示账号：user01/user123、shop01/shop123、courier01/courier123、admin/admin123\n";
echo "初始化完成。\n";
