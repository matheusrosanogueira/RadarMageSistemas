<?php
require_once __DIR__ . '/inc/functions.php';
$op = ['path' => '/', 'expires' => time() + 86400 * 365, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])];
setcookie('radar_filtro', ($_GET['ligar'] ?? '') === '1' ? '1' : '0', $op);
$volta = (string)($_GET['volta'] ?? 'index.php');
if (!preg_match('#^/?[a-z0-9_./?=&%-]*$#i', $volta) || str_contains($volta, '//')) $volta = 'index.php';
header('Location: ' . $volta);
