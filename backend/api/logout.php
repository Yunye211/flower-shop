<?php
/**
 * api/logout.php —— 注销登录（删除当前 token）
 * 入参：token（或 Authorization 头）
 */
require __DIR__ . '/../config.php';
require __DIR__ . '/../lib/auth.php';

revoke_token();
ok([], '已退出登录');
