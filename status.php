<?php
/* Estado do próprio Radar: sincronização, Sefaz, cron. ?formato=json para monitoramento */
require_once __DIR__ . '/inc/layout.php';
$rel = lojadev_relatorio(); $sef = sefaz_cache();
$cronUltimo = cfg('cron_ultimo');
$minutos = fn(?string $t) => $t ? (int)round((time() - strtotime($t)) / 60) : null;
$itens = [
    ['Cron', $cronUltimo, $minutos($cronUltimo), 20, $cronUltimo ? 'rodou ' . tempo_relativo($cronUltimo) : 'nunca rodou — agende em cPanel > Cron Jobs'],
    ['Sefaz', $sef['em'] ?? null, $minutos($sef['em'] ?? null), 30, $sef ? (($sef['fonte'] ?? 'portal') === 'portal' ? 'lida no Portal da NF-e' : 'lida pelo fornecedor') . (!empty($sef['erro']) ? ' · última tentativa falhou: ' . $sef['erro'] : '') : 'sem leitura'],
    ['Fornecedor', $rel['em'] ?? null, $minutos($rel['em'] ?? null), max(60, (int)cfg('sinc_intervalo', '30') * 2), $rel ? ($rel['ok'] ? 'sincronizado' . (!empty($rel['versao_fonte']) ? ', fonte na ' . $rel['versao_fonte'] : '') : 'falhou: ' . implode('; ', $rel['erros'] ?? [])) : 'nunca sincronizou'],
];
$geral = 'ok';
foreach ($itens as &$i) { $i[] = $i[2] === null ? 'fora' : ($i[2] > $i[3] ? 'inst' : 'ok'); if ($i[5] !== 'ok') $geral = $i[5] === 'fora' ? 'fora' : ($geral === 'ok' ? 'inst' : $geral); }
unset($i);
if (($_GET['formato'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => $geral, 'agora' => date('c'), 'itens' => array_map(fn($i) => ['nome' => $i[0], 'em' => $i[1], 'minutos' => $i[2], 'limite' => $i[3], 'detalhe' => $i[4], 'situacao' => $i[5]], $itens), 'versao' => versao_atual()['numero'] ?? null], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
layout_topo('Status do Radar', 'projeto', 'O Radar está se mantendo sozinho?');
?>
<section class="bloco">
  <p class="sefaz-titulo <?= $geral ?>"><?= ['ok' => 'Tudo em dia', 'inst' => 'Algo atrasado', 'fora' => 'Algo parado'][$geral] ?></p>
  <ul class="lista">
    <?php foreach ($itens as [$nome, $em, $min, $lim, $det, $sit]): ?>
      <li><span><i class="anel anel-<?= $sit ?>"></i> <b><?= e($nome) ?></b> — <?= e($det) ?></span><time><?= $em ? data_hora_br($em) : '—' ?></time></li>
    <?php endforeach; ?>
  </ul>
  <p class="nota">Para monitorar de fora: <code><?= e(url_base()) ?>/status.php?formato=json</code> responde <code>"status": "ok"</code>, <code>"inst"</code> ou <code>"fora"</code>.</p>
</section>
<?php layout_rodape();
