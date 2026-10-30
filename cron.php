<?php
/**
 * Ponto único para o agendador do CPanel. A cada 10 minutos:
 *   curl -s "https://radar.magesistemas.com.br/cron.php?token=SEU_TOKEN" > /dev/null
 *
 * Faz, nesta ordem:
 *   1. lê a situação da Sefaz (portal; se falhar, o Radar do fornecedor)
 *   2. sincroniza com o Radar do fornecedor, se passou o intervalo configurado (ou com ?sinc=1)
 * O token está em Admin > Configurações.
 */
require_once __DIR__ . '/inc/functions.php';
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(120);
cfg_set('cron_ultimo', date('Y-m-d H:i:s'));

$token = $_GET['token'] ?? '';
if ($token === '' || !hash_equals(sefaz_token(), $token)) { http_response_code(403); exit("token inválido\n"); }

// 0. Backup semanal do banco (mantém os últimos 8)
$dirB = RADAR_ROOT . '/data/backups';
if (!is_dir($dirB)) @mkdir($dirB, 0755, true);
$ultimo = glob("$dirB/radar-*.db"); rsort($ultimo);
if (!$ultimo || filemtime($ultimo[0]) < time() - 7 * 86400) {
    db()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    $dest = "$dirB/radar-" . date('Y-m-d') . '.db';
    if (@copy(RADAR_DB, $dest)) { echo "backup: $dest\n"; foreach (array_slice($ultimo, 7) as $velho) @unlink($velho); }
}

// 1. Sefaz
if (sefaz_ativo()) {
    $s = sefaz_situacao(true); $r = sefaz_resumo($s);
    echo $s && $s['autorizadores']
        ? "sefaz ok ({$s['fonte']}): {$r['no_ar']}/{$r['total']} no ar" . ($r['instaveis'] ? ', instável: ' . implode(' ', $r['instaveis']) : '') . ($r['fora'] ? ', fora: ' . implode(' ', $r['fora']) : '') . "\n"
        : "sefaz falhou: " . ($s['erro'] ?? 'sem detalhe') . "\n";
} else echo "sefaz desligada\n";

// 2. Fornecedor
if (lojadev_ativo()) {
    $ultimo = lojadev_relatorio();
    $idade = $ultimo && !empty($ultimo['em']) ? (time() - strtotime($ultimo['em'])) / 60 : PHP_INT_MAX;
    $intervalo = max(5, (int)cfg('sinc_intervalo', '30'));
    if (isset($_GET['sinc']) || $idade >= $intervalo) {
        $rel = lojadev_sincronizar(true);
        echo $rel['ok'] ? "sincronização ok: " . json_encode($rel['contagem'], JSON_UNESCAPED_UNICODE) . "\n" : "sincronização falhou: " . implode('; ', $rel['erros']) . "\n";
        if ($rel['ok'] && $rel['erros']) echo "avisos: " . implode('; ', $rel['erros']) . "\n";
    } else echo "sincronização: próxima em " . (int)($intervalo - $idade) . " min\n";
} else echo "sincronização desligada\n";

// 3. Avisos aos clientes
if (avisos_ativos()) {
    $g = avisos_gerar_versoes() + avisos_gerar_sefaz();
    [$ok, $erro] = avisos_enviar(30);
    echo "avisos: $g enfileirados, $ok enviados" . ($erro ? ", $erro com erro" : '') . "\n";
} else echo "avisos desligados\n";
