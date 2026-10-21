<?php
/**
 * api/submit_order.php —— 用户提交订单（核心业务）
 * v2 优化：
 *  1. 事务内 SELECT ... FOR UPDATE 锁定商品行，防止并发超卖；
 *  2. 生成 order_item 明细快照（替代原版仅 goods_text 字符串）；
 *  3. 可选钱包支付：校验二级密码、余额充足、扣款并写流水；
 *  4. 提交成功后清空购物车。
 * 入参：token, rece_name, rece_phone, rece_addr, pay_type(可选 'wallet' 使用钱包支付),
 *       second_password(钱包支付时必填)
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/functions.php';
require __DIR__ . '/../lib/auth.php';

$me    = require_role([ROLE_USER]);
$name  = post_str('rece_name');
$phone = post_str('rece_phone');
$addr  = post_str('rece_addr');
$payType = post_str('pay_type');                 // '' 或 'wallet'
$secondPwd = post_str('second_password');

if ($name === '' || $phone === '' || $addr === '') {
    fail('收货人姓名、电话、地址不能为空');
}
if (mb_strlen($name) > 50 || mb_strlen($addr) > 200) {
    fail('收货信息长度超出限制');
}
if (!valid_phone($phone)) {
    fail('收货手机号格式不正确');
}

$pdo = db();

try {
    $pdo->beginTransaction();

    // 1. 读取购物车（FOR UPDATE 锁定商品，防超卖）
    $stmt = $pdo->prepare(
        'SELECT c.product_id, c.num, p.name, p.price, p.stock, p.status
           FROM cart c
           JOIN product p ON p.id = c.product_id
          WHERE c.user_id = ?
            FOR UPDATE OF p'
    );
    $stmt->execute([$me['id']]);
    $items = $stmt->fetchAll();
    if (!$items) {
        throw new RuntimeException('购物车为空，无法下单');
    }

    // 2. 校验在售与库存
    foreach ($items as $it) {
        if ((int)$it['status'] !== 1) {
            throw new RuntimeException("商品「{$it['name']}」已下架，请先在购物车移除");
        }
        if ((int)$it['num'] > (int)$it['stock']) {
            throw new RuntimeException("商品「{$it['name']}」库存不足（剩余 {$it['stock']}）");
        }
    }

    // 3. 计算总价（服务端计算，禁止信任前端金额）
    $total     = calc_total($items);
    $goodsText = build_goods_text($items);
    $orderNo   = generate_order_no();

    // 4. 钱包支付校验（在事务内完成，保证一致）
    $paidAt = null;
    if ($payType === 'wallet') {
        if ($secondPwd === '') {
            throw new RuntimeException('钱包支付需要输入二级密码');
        }
        $check = check_second_password($pdo, $me['id'], $secondPwd);
        if (!$check['ok']) {
            throw new RuntimeException($check['msg']);
        }
        // 余额校验并扣款（行锁）
        $stmt = $pdo->prepare('SELECT balance FROM wallet WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$me['id']]);
        $wallet = $stmt->fetch();
        if (!$wallet || (float)$wallet['balance'] < $total) {
            throw new RuntimeException('钱包余额不足，请先充值或改用其他支付方式');
        }
        $newBalance = round((float)$wallet['balance'] - $total, 2);
        $pdo->prepare('UPDATE wallet SET balance = ?, updated_at = now() WHERE user_id = ?')
            ->execute([$newBalance, $me['id']]);
    }

    // 5. 写订单主表
    $pdo->prepare(
        'INSERT INTO orders(order_no, user_id, rece_name, rece_phone, rece_addr,
                            goods_text, total_price, status, pay_status, paid_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $orderNo, $me['id'], $name, $phone, $addr,
        $goodsText, $total, '待发货',
        $payType === 'wallet' ? '已支付' : '未支付',
        $paidAt ?? ($payType === 'wallet' ? date('Y-m-d H:i:s') : null),
    ]);
    $orderId = (int)$pdo->lastInsertId();

    // 6. 写订单明细快照 + 扣库存
    $insItem = $pdo->prepare(
        'INSERT INTO order_item(order_id, product_id, name, price, num, subtotal) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $decStock = $pdo->prepare('UPDATE product SET stock = stock - ? WHERE id = ?');
    foreach ($items as $it) {
        $sub = round((float)$it['price'] * (int)$it['num'], 2);
        $insItem->execute([$orderId, $it['product_id'], $it['name'], $it['price'], $it['num'], $sub]);
        $decStock->execute([$it['num'], $it['product_id']]);
    }

    // 7. 钱包流水（若支付）
    if ($payType === 'wallet') {
        $pdo->prepare(
            'INSERT INTO wallet_transaction(user_id, type, amount, balance_after, order_id, remark)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$me['id'], '支付', -$total, $newBalance, $orderId, '订单支付 ' . $orderNo]);
    }

    // 8. 清空购物车
    $pdo->prepare('DELETE FROM cart WHERE user_id = ?')->execute([$me['id']]);

    $pdo->commit();
    audit_log($pdo, ROLE_USER, $me['id'], '提交订单', "order_no={$orderNo}, total={$total}, pay={$payType}");

    ok([
        'order_id'   => $orderId,
        'order_no'   => $orderNo,
        'total'      => $total,
        'pay_status' => $payType === 'wallet' ? '已支付' : '未支付',
    ], '下单成功');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail($e->getMessage());
}
