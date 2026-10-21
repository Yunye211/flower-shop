<?php
/**
 * api/admin_stats.php —— 管理员平台统计
 * 入参：token
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me = require_role([ROLE_ADMIN]);
$pdo = db();

$stats = [];
foreach ([
    'user_count'   => 'SELECT count(*) FROM app_user',
    'merchant_count' => 'SELECT count(*) FROM merchant',
    'courier_count'  => 'SELECT count(*) FROM delivery_person',
    'product_count'  => 'SELECT count(*) FROM product',
    'order_count'    => 'SELECT count(*) FROM orders',
    'paid_order_count' => "SELECT count(*) FROM orders WHERE pay_status = '已支付'",
    'delivered_count' => "SELECT count(*) FROM orders WHERE status = '已送达'",
] as $key => $sql) {
    $stats[$key] = (int)$pdo->query($sql)->fetchColumn();
}

// 今日订单与营收
$stats['today_orders'] = (int)$pdo->query(
    "SELECT count(*) FROM orders WHERE created_at::date = CURRENT_DATE"
)->fetchColumn();
$stats['total_revenue'] = (float)$pdo->query(
    "SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE pay_status = '已支付' AND status <> '已取消'"
)->fetchColumn();

// 各状态订单数
$statusRows = $pdo->query('SELECT status, count(*) AS cnt FROM orders GROUP BY status')->fetchAll();
$stats['status_map'] = array_column($statusRows, 'cnt', 'status');

ok(['stats' => $stats]);
