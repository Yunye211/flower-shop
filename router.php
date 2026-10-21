<?php
/**
 * Render 单容器路由：/api/xxx.php -> backend/api/xxx.php，其余交给 PHP 内置服务器
 */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

if (preg_match('#^/api/([A-Za-z0-9_\-]+\.php)$#', $uri, $m)) {
    $file = __DIR__ . '/backend/api/' . $m[1];
    if (is_file($file)) {
        require $file;
        return true;
    }
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 0, 'msg' => '接口不存在：' . $m[1]], JSON_UNESCAPED_UNICODE);
    return true;
}

return false;