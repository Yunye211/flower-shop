<?php
/**
 * lib/auth.php —— token 认证与角色权限校验
 * v2 优化：以随机 token 替代原版 localStorage 明文 uid 方案，服务端校验身份与角色。
 */

declare(strict_types=1);

// ---------------- 角色常量 ----------------
const ROLE_USER     = 'user';       // 普通用户
const ROLE_MERCHANT = 'merchant';   // 商家
const ROLE_COURIER  = 'courier';    // 外卖员
const ROLE_ADMIN    = 'admin';      // 管理员

// 各角色对应的数据表名（用户信息表）
const ROLE_TABLES = [
    ROLE_USER     => 'app_user',
    ROLE_MERCHANT => 'merchant',
    ROLE_COURIER  => 'delivery_person',
    ROLE_ADMIN    => 'admin_user',
];

// 各角色展示名字段（前端展示用）
const ROLE_NAME_FIELDS = [
    ROLE_USER     => 'username',
    ROLE_MERCHANT => 'shop_name',
    ROLE_COURIER  => 'name',
    ROLE_ADMIN    => 'name',
];

/**
 * 生成随机 token（64 位十六进制）
 */
function new_token(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * 登录成功后签发 token（写入 auth_token 表）
 * @param PDO    $pdo
 * @param int    $userId
 * @param string $role  角色常量
 * @return string
 */
function issue_token(PDO $pdo, int $userId, string $role): string
{
    $token = new_token();
    $pdo->prepare('INSERT INTO auth_token(token, user_type, user_id) VALUES (?, ?, ?)')
        ->execute([$token, $role, $userId]);
    return $token;
}

/**
 * 从请求中提取 token：优先 Authorization: Bearer xxx，其次 POST token
 */
function get_request_token(): ?string
{
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+([A-Fa-f0-9]{64})$/', $auth, $m)) {
        return strtolower($m[1]);
    }
    $t = $_POST['token'] ?? ($_GET['token'] ?? '');
    if (is_string($t) && preg_match('/^[A-Fa-f0-9]{64}$/', $t)) {
        return strtolower($t);
    }
    return null;
}

/**
 * 解析当前登录用户
 * @return array|null ['id'=>int, 'type'=>string, 'username'=>string] 未登录返回 null
 */
function current_user(): ?array
{
    $token = get_request_token();
    if ($token === null) {
        return null;
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT user_type, user_id FROM auth_token WHERE token = ?');
    $stmt->execute([$token]);
    $tk = $stmt->fetch();
    if (!$tk) {
        return null;
    }
    $role = $tk['user_type'];
    $table = ROLE_TABLES[$role] ?? null;
    if ($table === null) {
        return null;
    }
    $nameField = ROLE_NAME_FIELDS[$role] ?? 'username';
    $stmt = $pdo->prepare("SELECT id, $nameField AS display_name, username FROM $table WHERE id = ?");
    $stmt->execute([(int)$tk['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }
    return [
        'id'       => (int)$user['id'],
        'type'     => $role,
        'username' => (string)($user['display_name'] ?: $user['username']),
    ];
}

/**
 * 要求登录并校验角色；不满足则输出失败并终止。
 * @param array $roles 允许的角色列表
 * @return array 当前用户信息
 */
function require_role(array $roles): array
{
    $user = current_user();
    if ($user === null) {
        fail('未登录或登录已过期，请重新登录', 401);
    }
    if (!in_array($user['type'], $roles, true)) {
        fail('无权限执行该操作', 403);
    }
    return $user;
}

/**
 * 登出：删除当前 token
 */
function revoke_token(): void
{
    $token = get_request_token();
    if ($token !== null) {
        db()->prepare('DELETE FROM auth_token WHERE token = ?')->execute([$token]);
    }
}
