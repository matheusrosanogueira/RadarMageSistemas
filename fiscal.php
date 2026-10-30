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
<?php if (sefaz_ativo()): ?>
<section class="bloco" id="sefaz">
  <div class="bloco-cab"><h2>Sefaz agora</h2><span class="contagem"><?= $sr['ok'] ? $sr['no_ar'] . ' de ' . $sr['total'] . ' estados no ar' : 'sem leitura' ?></span></div>
  <p class="nota">Leitura da página de disponibilidade do Portal da NF-e, autorizador por autorizador. <b>Verde</b> responde normalmente, <b>amarelo</b> está lento ou instável, <b>vermelho</b> está fora — nesse caso a NFC-e sai em contingência e a NF-e espera. SVRS e SVAN atendem vários estados de uma vez.</p>
  <?php if (!$sefaz || !$sefaz['autorizadores']): ?>
    <div class="sefaz-erro">Ainda não foi possível ler o portal<?= !empty($sefaz['erro']) ? ': ' . e($sefaz['erro']) : '' ?>. A próxima visita tenta de novo.</div>
  <?php else: ?>
    <div class="sefaz-legenda"><span><i class="bola bola-verde"></i>no ar</span><span><i class="bola bola-amarela"></i>instável</span><span><i class="bola bola-vermelha"></i>fora</span></div>
    <div class="sefaz-grid">
      <?php foreach ($sefaz['autorizadores'] as $a): $cor = sefaz_cor_geral($a); ?>
        <div class="sefaz-item">
          <h4><i class="bola bola-<?= $cor ?>"></i><?= e($a['sigla']) ?></h4>
          <?php if (!empty($mapUf[$a['sigla']]) && count($mapUf[$a['sigla']]) > 1): ?><small><?= e(implode(' ', $mapUf[$a['sigla']])) ?></small><?php endif; ?>
          <div class="sefaz-servicos" title="<?= e(implode(' · ', array_map(fn($k, $v) => "$k: " . (str_starts_with($v, 'texto:') ? substr($v, 6) : $v), array_keys($a['servicos']), $a['servicos']))) ?>">
            <?php foreach ($a['servicos'] as $k => $v): if (in_array($v, ['verde', 'amarela', 'vermelha'], true)): ?><i class="bola bola-<?= $v ?>" title="<?= e($k) ?>"></i><?php endif; endforeach; ?>
          </div>
          <?php if ($a['tempo'] !== ''): ?><small>tempo médio <?= e($a['tempo']) ?></small><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="sefaz-meta">
      <span>lido <?= tempo_relativo($sefaz['em']) ?> (<?= data_hora_br($sefaz['em']) ?>)</span>
      <?php if (!empty($sefaz['erro'])): ?><span>última tentativa falhou: <?= e($sefaz['erro']) ?></span><?php endif; ?>
      <a href="https://www.nfe.fazenda.gov.br/portal/disponibilidade.aspx" target="_blank" rel="noopener">ver no Portal da NF-e</a>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>
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
