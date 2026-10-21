<?php
/**
 * lib/functions.php —— 公共纯函数与通用工具
 * 纯函数集中在此，便于单元测试（tests/test_functions.php 直接调用）。
 */

declare(strict_types=1);

// 兼容性兜底：若运行环境未启用 mbstring，退化为按字节计数
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s): int
    {
        return strlen($s);
    }
}

// ---------------- 输入校验 ----------------

/**
 * 校验用户名：3~20 位字母/数字/下划线
 */
function valid_username(string $username): bool
{
    return (bool)preg_match('/^[A-Za-z0-9_]{3,20}$/', $username);
}

/**
 * 校验密码强度：至少 8 位，且包含字母和数字
 */
function valid_password(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/[0-9]/', $password);
}

/**
 * 校验手机号：11 位数字（中国大陆），允许为空
 */
function valid_phone(?string $phone): bool
{
    if ($phone === null || $phone === '') {
        return true;
    }
    return (bool)preg_match('/^1[3-9]\d{9}$/', $phone);
}

/**
 * 校验金额：非负、最多两位小数、数值上限
 */
function valid_amount($amount): bool
{
    if (!is_numeric($amount) || $amount < 0) {
        return false;
    }
    $cents = round(((float)$amount) * 100);
    if ($cents > 9999999999) {          // 上限 99999999.99
        return false;
    }
    return abs((float)$amount * 100 - $cents) < 1e-6;
}

// ---------------- 业务纯函数 ----------------

/**
 * 生成业务订单号：时间戳 + 随机数，唯一且可读
 * 格式：YYYYMMDDHHMMSS + 6 位随机
 */
function generate_order_no(): string
{
    return date('YmdHis') . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * 计算购物车/订单明细总价
 * @param array $items 每条含 price 与 num
 */
function calc_total(array $items): float
{
    $total = 0.0;
    foreach ($items as $it) {
        $total += (float)$it['price'] * (int)$it['num'];
    }
    return round($total, 2);
}

/**
 * 拼接商品快照文本，如："红玫瑰花束×2，向日葵花束×1"
 */
function build_goods_text(array $items): string
{
    $parts = [];
    foreach ($items as $it) {
        $parts[] = $it['name'] . '×' . $it['num'];
    }
    return implode('，', $parts);
}

/**
 * 二级密码校验（带防暴力破解：连续错误 5 次锁定 15 分钟）
 * 返回：['ok'=>bool, 'msg'=>string]
 *
 * @param PDO    $pdo
 * @param int    $userId
 * @param string $input  用户输入的二级密码
 */
function check_second_password(PDO $pdo, int $userId, string $input): array
{
    // 读取锁定状态与哈希
    $stmt = $pdo->prepare(
        'SELECT u.second_password_hash AS pwd_hash,
                COALESCE(f.fail_count, 0) AS fail_count,
                f.lock_until
           FROM app_user u
           LEFT JOIN second_pwd_fail f ON f.user_id = u.id
          WHERE u.id = ?'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || $row['pwd_hash'] === null || $row['pwd_hash'] === '') {
        return ['ok' => false, 'msg' => '尚未设置二级密码，请先设置'];
    }
    if ($row['lock_until'] !== null && strtotime($row['lock_until']) > time()) {
        $mins = ceil((strtotime($row['lock_until']) - time()) / 60);
        return ['ok' => false, 'msg' => "二级密码错误次数过多，已锁定，请 {$mins} 分钟后再试"];
    }
    if (password_verify($input, $row['pwd_hash'])) {
        // 校验成功，清零失败记录
        $pdo->prepare('DELETE FROM second_pwd_fail WHERE user_id = ?')->execute([$userId]);
        return ['ok' => true, 'msg' => '校验通过'];
    }
    // 校验失败：累计次数
    $newCount = $row['fail_count'] + 1;
    if ($newCount >= 5) {
        $lockUntil = date('Y-m-d H:i:s', time() + 15 * 60);
        $pdo->prepare(
            'INSERT INTO second_pwd_fail(user_id, fail_count, lock_until) VALUES (?, ?, ?)
             ON CONFLICT (user_id) DO UPDATE SET fail_count = ?, lock_until = ?'
        )->execute([$userId, $newCount, $lockUntil, $newCount, $lockUntil]);
        return ['ok' => false, 'msg' => '二级密码连续错误 5 次，账号已锁定 15 分钟'];
    }
    $pdo->prepare(
        'INSERT INTO second_pwd_fail(user_id, fail_count) VALUES (?, ?)
         ON CONFLICT (user_id) DO UPDATE SET fail_count = ?'
    )->execute([$userId, $newCount, $newCount]);
    return ['ok' => false, 'msg' => '二级密码错误，剩余尝试次数：' . (5 - $newCount)];
}

// ---------------- 审计日志 ----------------

/**
 * 记录操作审计日志（关键操作：登录、支付、发货、接单、管理操作）
 */
function audit_log(PDO $pdo, ?string $userType, ?int $userId, string $action, ?string $detail = null): void
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $pdo->prepare(
            'INSERT INTO operation_log(user_type, user_id, action, detail, ip) VALUES (?, ?, ?, ?, ?)'
        )->execute([$userType, $userId, $action, $detail, $ip]);
    } catch (Throwable $e) {
        // 审计失败不应阻断主流程，静默记录
        error_log('audit_log failed: ' . $e->getMessage());
    }
}

// ---------------- 通用 POST 参数读取 ----------------

/**
 * 读取 POST 字符串参数并去除首尾空白
 */
function post_str(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

/**
 * 读取 POST 整数参数（非整数返回默认值，防注入）
 */
function post_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? null;
    if ($v === null) {
        return $default;
    }
    return filter_var($v, FILTER_VALIDATE_INT) === false ? $default : (int)$v;
}
