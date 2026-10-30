<?php
/** Renderiza o cartão completo de uma versão (usado em versoes.php, v.php e desde.php) */
declare(strict_types=1);

function rotulo_areas(int $n): string {
    $m = min(50, $n);
    return $m === 1 ? 'Ver a alteração, por área' : "Ver as $m alterações, agrupadas por área";
}

function bloco_areas(array $alteracoes, string $rotulo, int $totalReal = 0, bool $aberto = false): void {
    if (!$alteracoes) return;
    $grupos = agrupar_por_area($alteracoes);
    $limite = 50; $mostradas = 0;
    ?>
    <details class="areas" <?= $aberto ? 'open' : '' ?>>
      <summary><?= e($rotulo) ?></summary>
      <?php foreach ($grupos as $nome => $lista): if ($mostradas >= $limite) break; ?>
        <h4><?= e($nome) ?><b><?= count($lista) ?></b></h4>
        <ul class="lista compacta">
          <?php foreach ($lista as $a): if ($mostradas++ >= $limite) break; ?>
            <li><span><?= e($a['titulo']) ?></span><time><?= tempo_relativo($a['criada_em']) ?></time></li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
      <?php $totalCiclo = max(count($alteracoes), $totalReal); if ($totalCiclo > min($limite, count($alteracoes))): ?>
        <p class="nota">E mais <?= $totalCiclo - min($limite, count($alteracoes)) ?> alterações neste ciclo, não listadas aqui.</p>
      <?php endif; ?>
    </details>
    <?php
}

/** Cartão de uma versão publicada */
function cartao_versao(array $v, bool $notasAbertas = false, bool $comLink = true): void {
    $alts = alteracoes_da_versao((int)$v['id']);
    $issues = tarefas_da_versao((int)$v['id']);
    $falta = falta_para_visitante($v);
    $nAss = max(count($issues), (int)($v['assuntos_total'] ?? 0));
    $tot = max((int)($v['alteracoes_total'] ?? 0), count($alts));
    ?>
    <article class="versao <?= $falta ? 'versao-falta' : '' ?>" id="v<?= $v['id'] ?>">
      <header class="versao-cab">
        <h2><span class="versao-numero"><?php if ($comLink): ?><a href="v.php?n=<?= e($v['numero']) ?>"><?= e($v['numero']) ?></a><?php else: ?><?= e($v['numero']) ?><?php endif; ?><?php if ($v['codinome']): ?><em><?= e($v['codinome']) ?></em><?php endif; ?></span></h2>
        <p class="versao-meta">
          <?php if ($nAss): ?><?= $nAss ?> <?= plural($nAss, 'assunto', 'assuntos') ?> · <?php endif; ?>
          <?= $tot ?> <?= plural($tot, 'alteração', 'alterações') ?> · publicada em <?= data_br($v['publicada_em']) ?>
          <?= selo_homologacao($v) ?><?= selo_falta($v) ?>
          <?php if ($comLink): ?><a class="compartilhar" href="v.php?n=<?= e($v['numero']) ?>" title="Link desta versão">link</a><?php endif; ?>
        </p>
      </header>

      <?php if (!empty($v['nota_mage'])): ?>
        <div class="nota-mage"><b><?= e(cfg('empresa')) ?> diz:</b> <?= md_inline($v['nota_mage']) ?></div>
      <?php endif; ?>

      <?php if ($v['resumo']): ?><p class="uma-linha"><b>Em uma linha:</b> <?= md_inline($v['resumo']) ?></p><?php endif; ?>

      <?php if ($v['notas']): ?>
        <details class="notas-detalhe" <?= $notasAbertas ? 'open' : '' ?>>
          <summary>Ver o que mudou na <?= e($v['numero']) ?></summary>
          <div class="notas"><?= md($v['notas']) ?></div>
          <?php if ($v['como_atualizar']): ?>
            <h3 class="md-secao">📥 Como atualizar</h3>
            <div class="notas"><?= md($v['como_atualizar']) ?></div>
          <?php endif; ?>
        </details>
      <?php elseif ($v['como_atualizar']): ?>
        <details class="notas-detalhe"><summary>Como atualizar para a <?= e($v['numero']) ?></summary><div class="notas"><?= md($v['como_atualizar']) ?></div></details>
      <?php endif; ?>

      <?php if ($issues): ?>
        <details class="issues">
          <summary>Ver <?= count($issues) === 1 ? 'a issue que fechou' : 'as ' . count($issues) . ' issues que fecharam' ?> nesta versão</summary>
          <ul class="lista tarefas">
            <?php foreach ($issues as $t): ?>
              <li>
                <span class="num"><a href="t.php?n=<?= (int)$t['numero'] ?>">#<?= (int)$t['numero'] ?></a></span>
                <span class="tit"><?= e($t['titulo']) ?></span>
                <span class="tags"><?php if ($t['area']): ?><i class="tag"><?= e($t['area']) ?></i><?php endif; ?><?php if (mb_strtolower($t['area'] ?? '') !== $t['tipo']): ?><i class="tag <?= tipo_classe($t['tipo']) ?>"><?= e($t['tipo']) ?></i><?php endif; ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </details>
      <?php endif; ?>

      <?php bloco_areas($alts, rotulo_areas(count($alts)), (int)($v['alteracoes_total'] ?? 0)); ?>
    </article>
    <?php
}
