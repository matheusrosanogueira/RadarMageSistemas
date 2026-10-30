<?php
/* Feed RSS (padrão) ou JSON (?formato=json) das versões publicadas e das últimas alterações */
require_once __DIR__ . '/inc/functions.php';
$base = url_base(); $site = cfg('site_nome');
$versoes = array_values(array_filter(versoes_todas(), fn($v) => $v['publicada_em']));
$alts = alteracoes_recentes(50);
if (($_GET['formato'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'radar' => $site, 'gerado_em' => date('c'), 'versao_atual' => versao_atual()['numero'] ?? null,
        'versoes' => array_map(fn($v) => ['numero' => $v['numero'], 'codinome' => $v['codinome'], 'resumo' => $v['resumo'], 'publicada_em' => $v['publicada_em'], 'homologacao' => $v['homologacao'] ?? '', 'nota_mage' => $v['nota_mage'] ?? '', 'url' => "$base/v.php?n=" . rawurlencode($v['numero'])], $versoes),
        'alteracoes' => array_map(fn($a) => ['titulo' => $a['titulo'], 'area' => $a['area'], 'ref' => $a['ref'], 'versao' => $a['versao_numero'], 'em' => $a['criada_em']], $alts),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
header('Content-Type: application/rss+xml; charset=utf-8');
$x = fn($s) => htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$rfc = fn($d) => date(DATE_RSS, strtotime($d));
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title><?= $x($site) ?> — versões e alterações do <?= $x(cfg('sistema_nome')) ?></title>
  <link><?= $x($base) ?>/</link>
  <atom:link href="<?= $x($base) ?>/feed.php" rel="self" type="application/rss+xml"/>
  <description><?= $x(cfg('descricao')) ?></description>
  <language>pt-BR</language>
  <lastBuildDate><?= $rfc(date('Y-m-d H:i:s')) ?></lastBuildDate>
<?php foreach (array_slice($versoes, 0, 20) as $v): ?>
  <item>
    <title>Versão <?= $x($v['numero']) ?><?= $v['codinome'] ? ' — ' . $x($v['codinome']) : '' ?></title>
    <link><?= $x($base) ?>/v.php?n=<?= $x(rawurlencode($v['numero'])) ?></link>
    <guid isPermaLink="true"><?= $x($base) ?>/v.php?n=<?= $x(rawurlencode($v['numero'])) ?></guid>
    <pubDate><?= $rfc($v['publicada_em']) ?></pubDate>
    <category>versão</category>
    <description><?= $x(($v['nota_mage'] ? cfg('empresa') . ' diz: ' . $v['nota_mage'] . "\n\n" : '') . ($v['resumo'] ?: '')) ?></description>
  </item>
<?php endforeach; foreach ($alts as $a): ?>
  <item>
    <title><?= $x($a['titulo']) ?></title>
    <link><?= $x($base) ?>/agora.php</link>
    <guid isPermaLink="false">alt-<?= $x($a['ref'] ?: $a['id']) ?></guid>
    <pubDate><?= $rfc($a['criada_em']) ?></pubDate>
    <category><?= $x($a['area'] ?: 'alteração') ?></category>
    <description><?= $x(($a['area'] ? $a['area'] . ' · ' : '') . ($a['versao_numero'] ? 'vai na ' . $a['versao_numero'] : 'próximo ciclo')) ?></description>
  </item>
<?php endforeach; ?>
</channel>
</rss>
