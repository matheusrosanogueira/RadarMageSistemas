<?php
require_once __DIR__ . '/inc/layout.php';

$abertas = nts_abertas();
$atendidas = nts_atendidas();
$sefaz = sefaz_situacao();
$sr = sefaz_resumo($sefaz);
$mapUf = sefaz_ufs();

function celula_prazo(?array $p): string {
    if (!$p) return '<td class="prazo">sem data</td>';
    $d = $p['dias'];
    $cls = $d < 0 ? 'vencido' : ($d <= 15 ? 'urgente' : '');
    $txt = $d < 0 ? 'venceu há ' . abs($d) . ' ' . plural(abs($d), 'dia', 'dias') : ($d === 0 ? 'hoje' : "$d " . plural($d, 'dia', 'dias'));
    return "<td class=\"prazo $cls\">{$p['etapa']} · $txt</td>";
}

layout_topo('Fiscal', 'fiscal');
titulo_pagina('Fiscal', 'Sefaz e Notas Técnicas');
?>
<?php bloco_sefaz_estados($sefaz); ?>
<?php if (sefaz_ativo() && $sefaz && $sefaz['autorizadores']): ?>
<section class="bloco" id="autorizadores">
  <div class="bloco-cab"><h2>Serviço a serviço, por autorizador</h2></div>
  <p class="nota">Cada bolinha é um serviço do autorizador (autorização, retorno, inutilização, consulta de protocolo, status, consulta de cadastro, eventos). SVRS e SVAN atendem vários estados de uma vez; SVC-AN e SVC-RS são as contingências nacionais.</p>
  <div class="sefaz-grid">
    <?php foreach ($sefaz['autorizadores'] as $a): $cor = sefaz_cor_geral($a); ?>
      <div class="sefaz-item">
        <h4><i class="bola bola-<?= $cor ?>"></i><?= e($a['sigla']) ?></h4>
        <?php if (!empty($mapUf[$a['sigla']]) && count($mapUf[$a['sigla']]) > 1): ?><small><?= e(implode(' ', $mapUf[$a['sigla']])) ?></small><?php endif; ?>
        <div class="sefaz-servicos">
          <?php foreach ($a['servicos'] as $k => $v): if (in_array($v, ['verde', 'amarela', 'vermelha'], true)): ?><i class="bola bola-<?= $v ?>" title="<?= e($k) ?>"></i><?php endif; endforeach; ?>
        </div>
        <?php if ($a['tempo'] !== ''): ?><small>tempo médio <?= e($a['tempo']) ?></small><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="sefaz-meta">
    <span>lido <?= tempo_relativo($sefaz['em']) ?> (<?= data_hora_br($sefaz['em']) ?>)<?= ($sefaz['fonte'] ?? 'portal') === 'fornecedor' ? ' pela leitura do fornecedor' : ' no Portal da NF-e' ?></span>
    <?php if (!empty($sefaz['erro'])): ?><span>última tentativa falhou: <?= e($sefaz['erro']) ?></span><?php endif; ?>
    <a href="https://www.nfe.fazenda.gov.br/portal/disponibilidade.aspx" target="_blank" rel="noopener">ver no Portal da NF-e</a>
  </div>
</section>
<?php $quedas = sefaz_quedas(7); if ($quedas):
    // agrupa por autorizador + início + fim: uma queda do SVRS é uma linha, com os estados dela
    $grupos = [];
    foreach ($quedas as $q) { $k = $q['autorizador'] . '|' . $q['inicio'] . '|' . ($q['fim'] ?? ''); $grupos[$k] ??= $q + ['ufs' => []]; $grupos[$k]['ufs'][] = $q['uf']; if ($q['pior'] === 'vermelha') $grupos[$k]['pior'] = 'vermelha'; }
?>
<section class="bloco" id="quedas">
  <div class="bloco-cab"><h2>Quedas nos últimos 7 dias</h2><span class="contagem"><?= count($grupos) ?><?= sefaz_quedas_hoje() ? ' · ' . count(array_filter($grupos, fn($g) => substr($g['inicio'], 0, 10) === date('Y-m-d'))) . ' hoje' : '' ?></span></div>
  <div class="tabela-rolagem">
  <table class="tabela">
    <thead><tr><th>Autorizador</th><th>Estados</th><th>Começou</th><th>Durou</th><th>Pior momento</th></tr></thead>
    <tbody>
      <?php foreach ($grupos as $q): ?>
        <tr>
          <td><b><?= e($q['autorizador'] ?: $q['uf']) ?></b></td>
          <td><?= e(implode(' ', $q['ufs'])) ?></td>
          <td class="nowrap"><?= data_hora_br($q['inicio']) ?></td>
          <td class="nowrap"><?= $q['fim'] ? duracao($q['inicio'], $q['fim']) : '<span class="prazo urgente">ainda fora · ' . duracao($q['inicio'], null) . '</span>' ?></td>
          <td class="nowrap"><i class="bola bola-<?= e($q['pior']) ?>"></i> <?= $q['pior'] === 'vermelha' ? 'fora do ar' : 'instável' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php endif; endif; ?>

<section class="bloco">
  <div class="bloco-cab"><h2>Notas Técnicas em aberto</h2><span class="contagem"><?= count($abertas) ?></span></div>
  <p class="nota"><b>Homologação</b> é quando a Sefaz libera o ambiente de teste; <b>produção</b> é quando a regra passa a valer — depois dela, nota fora da regra é rejeitada no seu cliente. A coluna da direita mostra qual das duas cobra primeiro. Nem toda NT exige mudança aqui dentro.</p>

  <?php if (!$abertas): ?><p class="vazio">Nenhuma Nota Técnica em aberto.</p><?php else: ?>
  <div class="tabela-rolagem">
  <table class="tabela">
    <thead><tr><th>Nota</th><th>Assunto</th><th>Homologação</th><th>Produção</th><th>Falta</th></tr></thead>
    <tbody>
      <?php foreach ($abertas as $nt): ?>
        <tr>
          <td class="nowrap"><?php if ($nt['tarefa_numero']): ?><span class="num">#<?= (int)$nt['tarefa_numero'] ?></span> <?php endif; ?><b><?= e($nt['codigo']) ?></b></td>
          <td><?= e($nt['assunto']) ?><?php if ($nt['observacao']): ?><br><small><?= e($nt['observacao']) ?></small><?php endif; ?></td>
          <td class="nowrap"><?= data_br($nt['homologacao']) ?></td>
          <td class="nowrap"><?= data_br($nt['producao']) ?></td>
          <?= celula_prazo($nt['_prazo']) ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>

<?php if ($atendidas): ?>
<section class="bloco">
  <div class="bloco-cab"><h2>Já atendidas</h2><span class="contagem"><?= count($atendidas) ?></span></div>
  <div class="tabela-rolagem">
  <table class="tabela">
    <thead><tr><th>Nota</th><th>Assunto</th><th>Homologação</th><th>Produção</th><th>Situação</th></tr></thead>
    <tbody>
      <?php foreach ($atendidas as $nt): ?>
        <tr>
          <td class="nowrap"><?php if ($nt['tarefa_numero']): ?><span class="num">#<?= (int)$nt['tarefa_numero'] ?></span> <?php endif; ?><b><?= e($nt['codigo']) ?></b></td>
          <td><?= e($nt['assunto']) ?></td>
          <td class="nowrap"><?= data_br($nt['homologacao']) ?></td>
          <td class="nowrap"><?= data_br($nt['producao']) ?></td>
          <td class="prazo ok"><?= $nt['status'] === 'nao_aplica' ? 'não exige mudança' : 'atendida' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php endif; ?>
<?php layout_rodape();
