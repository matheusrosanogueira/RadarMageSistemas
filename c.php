<?php
/**
 * Entrada do cliente pela chave: c.php?k=CHAVE  (opcional: &v=7.5.6 para o sistema informar a versão instalada)
 * Grava os cookies da versão e do cliente e leva à Visão geral.
 */
require_once __DIR__ . '/inc/functions.php';
$c = cliente_por_chave($_GET['k'] ?? '');
if (!$c) { http_response_code(404); header('Content-Type: text/html; charset=utf-8'); echo '<!doctype html><meta charset="utf-8"><title>Chave não encontrada</title><body style="font-family:system-ui;background:#0A0718;color:#ECE9F8;padding:40px;max-width:560px;margin:auto"><h1 style="font-size:22px">Chave não encontrada</h1><p style="color:#A9A3C6">Confira o link que a ' . e(cfg('empresa')) . ' enviou. <a style="color:#7C5CFF" href="index.php">Abrir o Radar sem identificação</a>.</p></body>'; exit; }
$v = trim($_GET['v'] ?? '');
$pdo = db();
if (preg_match('/^\d+(\.\d+){0,3}$/', $v)) { $pdo->prepare("UPDATE clientes SET versao = ?, versao_informada_em = datetime('now','localtime'), visto_em = datetime('now','localtime') WHERE id = ?")->execute([$v, $c['id']]); $c['versao'] = $v; }
else $pdo->prepare("UPDATE clientes SET visto_em = datetime('now','localtime') WHERE id = ?")->execute([$c['id']]);
$op = ['path' => '/', 'expires' => time() + 86400 * 365, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])];
setcookie('radar_cliente', $c['chave'], $op);
if ($c['versao'] !== '') setcookie('radar_versao', $c['versao'], $op);
if (cliente_modulos($c)) setcookie('radar_filtro', '1', $op);
header('Location: ' . (($_GET['ir'] ?? '') === 'desde' ? 'desde.php' : 'index.php'));
