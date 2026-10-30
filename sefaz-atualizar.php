<?php
/**
 * Endpoint para o cron do CPanel renovar a leitura da Sefaz.
 * Agende a cada 10 minutos:
 *   curl -s "https://radar.magesistemas.com.br/sefaz-atualizar.php?token=SEU_TOKEN" > /dev/null
 * O token está em Admin > Configurações > Sefaz.
 */
require_once __DIR__ . '/inc/functions.php';
header('Content-Type: text/plain; charset=utf-8');
$token = $_GET['token'] ?? '';
if ($token === '' || !hash_equals(sefaz_token(), $token)) { http_response_code(403); exit("token inválido\n"); }
if (!sefaz_ativo()) exit("consulta desligada nas configurações\n");
$s = sefaz_situacao(true);
$r = sefaz_resumo($s);
echo $s && $s['autorizadores'] ? "ok: {$r['no_ar']}/{$r['total']} estados no ar, " . count($s['autorizadores']) . " autorizadores lidos em {$s['em']}\n" : "falhou: " . ($s['erro'] ?? 'sem detalhe') . "\n";
