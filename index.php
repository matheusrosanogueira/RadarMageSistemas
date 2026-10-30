<?php
require_once __DIR__ . '/inc/layout.php';

$atual   = versao_atual();
$proxima = versao_proxima();
$ultimaPub = versao_ultima_publicada();
$prontas = filtrar_alteracoes(alteracoes_proxima());
$abertas = tarefas_abertas();
$emAndamento = count(array_filter($abertas, fn($t) => $t['em_andamento']));
$nts = nts_abertas();
[$entregues, $medianaDias] = entregues_30_dias();
$recentes = array_slice(filtrar_alteracoes(alteracoes_recentes(filtro_areas() ? 40 : 8)), 0, 8);
$areas = agrupar_por_area($prontas);
$sistema = cfg('sistema_nome');
$sefaz = sefaz_situacao();
$sr = sefaz_resumo($sefaz);

layout_topo('Visão geral', 'index');
titulo_pagina('Visão geral', 'O estado agora');
?>


<div class="cards">
  <a class="card" href="versoes.php">
    <b><?= $atual ? e($atual['numero']) : '—' ?></b>
    <span>Versão <?= $proxima ? 'em preparo' : 'atual' ?></span>
    <small><?= $proxima ? 'Ainda não publicada.' : ($atual ? 'Publicada em ' . data_br($atual['publicada_em']) . '.' : 'Nada cadastrado.') ?></small>
  </a>
  <a class="card" href="versoes.php">
    <b><?= count($prontas) ?></b>
    <span>Alterações prontas</span>
    <small>Já feitas<?= $proxima ? ', saem na ' . e($proxima['numero']) : ', saem na próxima' ?>.</small>
  </a>
  <a class="card" href="planejado.php">
    <b><?= count($abertas) ?></b>
    <span>Tarefas abertas</span>
    <small><?= $emAndamento ?> já com alguém trabalhando.</small>
  </a>
  <a class="card" href="fiscal.php">
    <b><?= count($nts) ?></b>
    <span>Notas Técnicas</span>
    <small>Prazos da Sefaz a atender.</small>
  </a>
  <a class="card <?= $sr['fora'] ? 'card-alerta' : '' ?>" href="fiscal.php#sefaz">
    <b><?= $sr['ok'] ? $sr['no_ar'] . '<i>/' . $sr['total'] . '</i>' : '—' ?></b>
    <span>Estados no ar</span>
    <small><?php if (!$sr['ok']): ?>Sefaz, sem leitura.<?php elseif ($sr['fora']): ?><?= count($sr['fora']) > 3 ? count($sr['fora']) . ' estados fora do ar.' : 'Fora: ' . e(implode(', ', $sr['fora'])) . '.' ?><?php elseif ($sr['instaveis']): ?><?= count($sr['instaveis']) > 3 ? count($sr['instaveis']) . ' estados instáveis.' : 'Instável: ' . e(implode(', ', $sr['instaveis'])) . '.' ?><?php else: ?>Sefaz, agora.<?php endif; ?></small>
  </a>
  <a class="card" href="planejado.php">
    <b><?= $entregues ?></b>
    <span>Entregues em 30 dias</span>
    <small>Mediana de <?= fmt_dias($medianaDias) ?>.</small>
  </a>
</div>

<?php bloco_sefaz_estados($sefaz); ?>

<div class="duas-colunas">
  <section class="bloco">
    <div class="bloco-cab">
      <h2>As últimas alterações</h2>
      <a href="agora.php">ver todas</a>
    </div>
    <?php if (!$recentes): ?>
      <p class="vazio">Nenhuma alteração registrada. As alterações entram pelo painel administrativo.</p>
    <?php else: ?>
      <ul class="lista">
        <?php foreach ($recentes as $a): ?>
          <li><span><?= e($a['titulo']) ?></span><time datetime="<?= e($a['criada_em']) ?>"><?= tempo_relativo($a['criada_em']) ?></time></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <div>
    <section class="bloco">
      <div class="bloco-cab">
        <h2>Vindo na <?= $proxima ? e($proxima['numero']) : 'próxima' ?></h2>
        <a href="versoes.php">detalhe</a>
      </div>
      <?php if (!$prontas): ?>
        <p class="vazio">Nenhuma alteração pronta para a próxima versão.</p>
      <?php else: ?>
        <p class="resumo-num"><b><?= count($prontas) ?></b> <?= plural(count($prontas), 'alteração pronta', 'alterações prontas') ?>, em <?= count($areas) ?> <?= plural(count($areas), 'área', 'áreas') ?></p>
        <div class="areas-chips">
          <?php $i = 0; foreach ($areas as $nome => $lista): if ($i++ >= 8) break; ?>
            <span><?= e($nome) ?><b><?= count($lista) ?></b></span>
          <?php endforeach; ?>
          <?php if (count($areas) > 8): ?><span class="mais">+<?= count($areas) - 8 ?> áreas</span><?php endif; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="bloco">
      <div class="bloco-cab">
        <h2>Prazos que estão correndo</h2>
        <a href="fiscal.php">detalhe</a>
      </div>
      <?php if (!$nts): ?>
        <p class="vazio">Nenhuma Nota Técnica em aberto.</p>
      <?php else: ?>
        <ul class="prazos">
          <?php foreach (array_slice($nts, 0, 4) as $nt): $p = $nt['_prazo']; ?>
            <li>
              <span><?= e($nt['codigo']) ?></span>
              <?php if ($p): ?>
                <em class="<?= $p['dias'] <= 15 ? 'urgente' : '' ?>"><?= $p['etapa'] ?> · <?= $p['dias'] < 0 ? 'venceu há ' . abs($p['dias']) . ' dias' : $p['dias'] . ' dias' ?></em>
              <?php else: ?><em>sem data</em><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php layout_rodape();
