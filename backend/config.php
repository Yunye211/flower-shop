<?php
/**
 * config.php —— 全局配置：数据库连接（PDO + PostgreSQL / Supabase）、JSON 输出、CORS
 * v2：支持云端 Supabase（PostgreSQL），全站 PDO 预处理，统一 JSON 响应。
 * 部署时通过环境变量注入数据库凭据（见 README），默认指向本地 postgres 便于 Docker 联调。
 */

declare(strict_types=1);

// ---------------- 数据库配置（环境变量优先） ----------------
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_PORT = getenv('DB_PORT') ?: '5432';
$DB_NAME = getenv('DB_NAME') ?: 'flower_shop';
$DB_USER = getenv('DB_USER') ?: 'postgres';
$DB_PASS = getenv('DB_PASS') ?: 'postgres';
$DB_SSL  = getenv('DB_SSL')  ?: 'require';

/**
 * 获取 PDO 单例连接（PostgreSQL）
 * @return PDO
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        if (($GLOBALS['DB_SSL'] ?? 'require') === 'require') {
            putenv('PGSSLMODE=require');
        } else {
            putenv('PGSSLMODE=disable');
        }
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;client_encoding=UTF8;connect_timeout=8',
            $GLOBALS['DB_HOST'],
            $GLOBALS['DB_PORT'],
            $GLOBALS['DB_NAME']
        );
        try {
            $pdo = new PDO($dsn, $GLOBALS['DB_USER'], $GLOBALS['DB_PASS'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => true,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['code' => 0, 'msg' => '数据库连接失败，请检查服务状态'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    return $pdo;
}

// ---------------- 统一 JSON 输出 ----------------
function json_out(array $data, int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ok(array $extra = [], string $msg = 'success'): void
{
    json_out(['code' => 1, 'msg' => $msg] + $extra);
}

function fail(string $msg, int $httpCode = 200): void
{
    json_out(['code' => 0, 'msg' => $msg], $httpCode);
}

// ---------------- CORS ----------------
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}