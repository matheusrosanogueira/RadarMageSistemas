<?php
require_once __DIR__ . '/inc/layout.php';

$abertas = tarefas_abertas();
$encerradas = tarefas_encerradas(80);
[$totalEnc, $mediana] = mediana_encerradas();

layout_topo('Planejado', 'planejado');
titulo_pagina('Planejado', 'As tarefas grandes');
?>
<section class="bloco">
  <div class="bloco-cab"><h2>O que está aberto</h2><span class="contagem"><?= count($abertas) ?> <?= plural(count($abertas), 'tarefa', 'tarefas') ?></span></div>
  <p class="nota">Cada <b>tarefa</b> é um assunto registrado no projeto, com um número. Fica aberta desde que entra até o trabalho terminar — é assim que se sabe o que ainda está por vir. O ponto marca as que já têm alguém trabalhando.</p>

  <?php if (!$abertas): ?><p class="vazio">Nenhuma tarefa aberta.</p><?php endif; ?>
  <ul class="lista tarefas">
    <?php foreach ($abertas as $t): ?>
      <li class="<?= $t['em_andamento'] ? 'andamento' : '' ?>">
        <span class="num"><a href="t.php?n=<?= (int)$t['numero'] ?>">#<?= (int)$t['numero'] ?></a></span>
        <span class="tit"><?= e($t['titulo']) ?><?php if ($t['descricao']): ?> <small>— <?= e($t['descricao']) ?></small><?php endif; ?></span>
        <span class="tags"><?php if ($t['area']): ?><i class="tag"><?= e($t['area']) ?></i><?php endif; ?><?php if (mb_strtolower($t['area'] ?? '') !== $t['tipo']): ?><i class="tag <?= tipo_classe($t['tipo']) ?>"><?= e($t['tipo']) ?></i><?php endif; ?></span>
        <time><?= tempo_relativo($t['criada_em']) ?></time>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="bloco">
  <div class="bloco-cab"><h2>Encerradas</h2><span class="contagem"><?= $totalEnc ?><?php if ($mediana !== null): ?>, mediana de <?= fmt_dias($mediana) ?><?php endif; ?></span></div>
  <?php if (!$encerradas): ?><p class="vazio">Nenhuma tarefa encerrada ainda.</p><?php endif; ?>
  <ul class="lista tarefas">
    <?php foreach ($encerradas as $t): ?>
      <li>
        <span class="num"><a href="t.php?n=<?= (int)$t['numero'] ?>">#<?= (int)$t['numero'] ?></a></span>
        <span class="tit"><?= e($t['titulo']) ?><?php if ($t['descricao']): ?> <small>— <?= e($t['descricao']) ?></small><?php endif; ?></span>
        <span class="tags"><?php if ($t['area']): ?><i class="tag"><?= e($t['area']) ?></i><?php endif; ?><?php if (mb_strtolower($t['area'] ?? '') !== $t['tipo']): ?><i class="tag <?= tipo_classe($t['tipo']) ?>"><?= e($t['tipo']) ?></i><?php endif; ?></span>
        <time><?= $t['versao_numero'] ? 'saiu na ' . e($t['versao_numero']) : 'encerrada ' . tempo_relativo($t['encerrada_em']) ?></time>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php layout_rodape();
