<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

function layout_topo(string $titulo, string $pagina, string $subtitulo = ''): void {
    $atual = versao_atual();
    $abertas = count(tarefas_abertas());
    $nts = count(nts_abertas());
    $minha = versao_visitante();
    $site = cfg('site_nome');
    $nav = [
        'index'     => ['Visão geral', 'index.php', null],
        'agora'     => ['Agora', 'agora.php', null],
        'versoes'   => ['Versões', 'versoes.php', null],
        'planejado' => ['Planejado', 'planejado.php', $abertas],
        'fiscal'    => ['Fiscal', 'fiscal.php', $nts],
        'projeto'   => ['O projeto', 'projeto.php', null],
    ];
    $sefazSit = sefaz_ativo() ? sefaz_situacao() : null;
    $sefazFrase = $sefazSit ? sefaz_frase($sefazSit) : '';
    $sefazClasse = $sefazFrase ? (str_contains($sefazFrase, 'fora') ? 'fora' : (str_contains($sefazFrase, 'inst') ? 'inst' : 'ok')) : '';
    $atualizadoEm = atualizado_em();
    $recente = db()->prepare("SELECT COUNT(*) FROM alteracoes WHERE produto = ? AND interno = 0 AND criada_em >= datetime('now','localtime','-24 hours')");
    $recente->execute([produto()]); $recente = (int)$recente->fetchColumn();
    $atras = $minha ? versoes_atras($minha) : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($titulo) ?> · <?= e($site) ?></title>
<meta name="description" content="<?= e(cfg('descricao')) ?>">
<meta name="theme-color" content="#0A0718">
<meta property="og:title" content="<?= e($titulo) ?> · <?= e($site) ?>">
<meta property="og:description" content="<?= e(cfg('descricao')) ?>">
<meta property="og:site_name" content="<?= e($site) ?>">
<meta property="og:locale" content="pt_BR">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=2">
<script>try{var t=localStorage.getItem('radar_tema');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
</head>
<body data-pagina="<?= e($pagina) ?>">
<a class="pular" href="#conteudo">Ir para o conteúdo</a>

<aside class="lateral" id="lateral">
  <a class="marca" href="index.php" aria-label="<?= e($site) ?>">
    <img src="assets/logo.svg" alt="" width="44" height="44">
    <span><b>Radar</b><small><?= e(cfg('empresa')) ?></small></span>
  </a>
  <form class="busca" action="busca.php" role="search">
    <input type="search" name="q" placeholder="Buscar no projeto…" aria-label="Buscar no projeto" value="<?= e($_GET['q'] ?? '') ?>">
  </form>
  <nav class="nav" aria-label="Seções">
    <?php foreach ($nav as $chave => [$rotulo, $href, $badge]): ?>
      <a href="<?= $href ?>" <?= $pagina === $chave ? 'aria-current="page"' : '' ?>>
        <?= $rotulo ?>
        <?php if ($badge): ?><b><?= $badge ?></b><?php elseif ($chave === 'agora' && $recente): ?><i class="ponto-vivo" title="<?= $recente ?> alterações nas últimas 24 horas"></i><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <a class="produto" href="<?= e(cfg('site_url')) ?>" target="_blank" rel="noopener"><i></i><?= e(cfg('sistema_nome')) ?></a>

  <div class="instalacao">
    <p class="rotulo">A sua instalação</p>
    <form id="form-versao" autocomplete="off">
      <label class="visually-hidden" for="minha-versao">Sua versão do <?= e(cfg('sistema_nome')) ?></label>
      <input id="minha-versao" name="v" inputmode="decimal" pattern="[0-9]+(\.[0-9]+)*" placeholder="<?= $atual ? e($atual['numero']) : '1.0.0' ?>" value="<?= e($minha) ?>">
      <button type="submit" aria-label="Marcar minha versão">→</button>
    </form>
    <?php if ($minha): ?>
      <p class="instalacao-aviso">
        <?php if ($atras === null): ?>Versão <?= e($minha) ?> não reconhecida.
        <?php elseif ($atras === 0): ?>Você está na versão mais recente.
        <?php else: ?>Você está <?= $atras ?> <?= plural($atras, 'versão', 'versões') ?> atrás. <a href="versoes.php">Veja em Versões o que ainda não chegou.</a><?php endif; ?>
        <button type="button" class="limpar" id="limpar-versao">esquecer</button>
      </p>
    <?php else: ?>
      <p class="instalacao-aviso">Diga sua versão e o Radar marca o que ainda não chegou até você.</p>
    <?php endif; ?>
  </div>
  <button type="button" class="tema" id="tema" aria-pressed="false">Tema claro</button>
</aside>

<div class="cortina" id="cortina"></div>

<div class="pagina">
  <header class="topbar">
    <button class="menu-btn" aria-expanded="false" aria-controls="lateral" aria-label="Abrir menu"><span></span><span></span><span></span></button>
    <a class="marca-mini" href="index.php"><img src="assets/logo.svg" alt="" width="26" height="26"><b>Radar Mage</b></a>
    <div class="topbar-estado">
      <?php if ($atual): ?><span><b><?= e($atual['numero']) ?></b> é a versão atual</span><?php endif; ?>
      <?php if ($sefazFrase): ?><a class="sefaz-frase <?= $sefazClasse ?>" href="fiscal.php#sefaz"><i></i><?= e($sefazFrase) ?></a><?php endif; ?>
    </div>
    <?php if ($atualizadoEm): ?><span class="topbar-quando mono">atualizado <?= data_hora_br($atualizadoEm) ?></span><?php endif; ?>
  </header>

  <main id="conteudo" class="conteudo">
<?php
    if ($subtitulo !== '') titulo_pagina($titulo, $subtitulo);
}

function layout_rodape(): void {
?>
  </main>
  <footer class="rodape">
    <div><?= e(cfg('site_nome')) ?> · <?= e(cfg('empresa')) ?><?php if (cfg('site_url')): ?> · <a href="<?= e(cfg('site_url')) ?>"><?= e(preg_replace('#^https?://#', '', cfg('site_url'))) ?></a><?php endif; ?></div>
    <div><a href="admin/">Área administrativa</a></div>
  </footer>
</div>
<script src="assets/app.js?v=2"></script>
</body>
</html>
<?php
}

/** Compatibilidade: as páginas chamam titulo_pagina() logo após layout_topo() */
function titulo_pagina(string $t, string $sub = ''): void {
    echo '<div class="cabecalho"><h1>' . e($t) . '</h1>';
    if ($sub) echo '<p>' . e($sub) . '</p>';
    echo '</div>';
}

/** Selo "novo para você" para versão que ainda não chegou na instalação do visitante */
function selo_falta(array $versao): string {
    return falta_para_visitante($versao) ? '<span class="selo selo-falta mono" title="Ainda não chegou na sua instalação">novo para você</span>' : '';
}

/** Quantas versões publicadas são mais novas que a informada; null se a versão não existe */
function versoes_atras(string $minha): ?int {
    $existe = false; $n = 0;
    foreach (versoes_todas() as $v) {
        if ($v['numero'] === $minha) $existe = true;
        if ($v['publicada_em'] && version_compare($v['numero'], $minha, '>')) $n++;
    }
    return $existe ? $n : null;
}

/** Momento da última atualização dos dados: sincronização ou última alteração lançada */
function atualizado_em(): ?string {
    $rel = lojadev_relatorio();
    $sinc = $rel['em'] ?? null;
    $st = db()->prepare("SELECT MAX(criada_em) FROM alteracoes WHERE produto = ?"); $st->execute([produto()]);
    $alt = $st->fetchColumn() ?: null;
    return max((string)$sinc, (string)$alt) ?: null;
}

/** Bloco "Sefaz por estado ao vivo": grade das 27 UFs, legenda e a tabela serviço a serviço */
function bloco_sefaz_estados(?array $sit, bool $aberto = false): void {
    if (!sefaz_ativo()) return;
    $estados = sefaz_estados($sit);
    $r = sefaz_resumo($sit);
    $lido = $sit['em'] ?? null;
    $fonte = ($sit['fonte'] ?? 'portal') === 'fornecedor' ? ' · pela leitura do fornecedor' : '';
    $falhas = (int)($sit['falhas_seguidas'] ?? 0);
    $mapUf = sefaz_ufs();
    $servicos = array_values(array_filter(sefaz_servicos(), fn($s) => $s !== 'Tempo Médio'));
    ?>
    <section class="bloco sefaz-bloco" id="sefaz">
      <div class="bloco-cab">
        <h2>Sefaz por estado <span class="ao-vivo mono">ao vivo</span></h2>
        <span class="contagem mono"><?php if ($lido): ?>Sefaz verificou às <?= data_hora_br($lido, true) ?><?= $fonte ?><?php else: ?>sem leitura<?php endif; ?></span>
      </div>
      <?php if (!$estados): ?>
        <div class="aviso">Ainda não foi possível ler a situação da Sefaz<?= !empty($sit['erro']) ? ': ' . e($sit['erro']) : '' ?>. A próxima visita tenta de novo.</div>
      <?php else: ?>
        <?php if ($falhas > 0): ?><div class="aviso">A leitura está parada há <?= $falhas ?> <?= plural($falhas, 'tentativa', 'tentativas') ?>: o portal e o fornecedor não respondem. Abaixo está a última leitura boa, de <?= data_hora_br($lido) ?>.</div><?php endif; ?>
        <p class="sefaz-titulo <?= $r['fora'] ? 'fora' : ($r['instaveis'] ? 'inst' : 'ok') ?>">
          <?php if ($r['fora']): ?><?= count($r['fora']) ?> <?= plural(count($r['fora']), 'estado fora do ar', 'estados fora do ar') ?><?= $r['instaveis'] ? ', ' . count($r['instaveis']) . ' ' . plural(count($r['instaveis']), 'instável', 'instáveis') : '' ?>
          <?php elseif ($r['instaveis']): ?><?= count($r['instaveis']) ?> <?= plural(count($r['instaveis']), 'estado instável', 'estados instáveis') ?>, <?= $r['no_ar'] ?> no ar
          <?php else: ?>Os <?= $r['no_ar'] ?> estados no ar<?php endif; ?>
        </p>
        <div class="uf-grade">
          <?php foreach ($estados as $uf => $x): ?>
            <span class="uf mono uf-<?= $x['situacao'] ?>" title="<?= e($uf . ' · ' . $x['autorizador'] . ' · ' . ['no_ar' => 'no ar', 'instavel' => 'instável', 'fora' => 'fora do ar'][$x['situacao']] . ($x['desde'] ? ' desde ' . data_hora_br($x['desde']) : '')) ?>"><?= $uf ?></span>
          <?php endforeach; ?>
        </div>
        <div class="sefaz-legenda"><span><i class="anel anel-ok"></i>No ar</span><span><i class="anel anel-inst"></i>Instável — some ou piora em 10 min</span><span><i class="anel anel-fora"></i>Fora — falhas seguidas</span></div>
        <?php if (!empty($sit['autorizadores'])): ?>
        <details class="expandir" <?= $aberto ? 'open' : '' ?>>
          <summary class="mono">Ver serviço a serviço, por autorizador</summary>
          <div class="tabela-rolagem">
          <table class="tabela servicos">
            <thead><tr><th>Autorizador</th><?php foreach ($servicos as $s): ?><th><?= e($s) ?></th><?php endforeach; ?><th>Tempo médio</th></tr></thead>
            <tbody>
              <?php foreach ($sit['autorizadores'] as $a): ?>
                <tr>
                  <td class="mono"><b><?= e($a['sigla']) ?></b><?php if (!empty($mapUf[$a['sigla']]) && count($mapUf[$a['sigla']]) > 1): ?><small><?= e(implode(' ', $mapUf[$a['sigla']])) ?></small><?php endif; ?></td>
                  <?php foreach ($servicos as $s): $v = $a['servicos'][$s] ?? 'nd'; ?>
                    <td><?php if (in_array($v, ['verde', 'amarela', 'vermelha'], true)): ?><i class="bola bola-<?= $v ?>" title="<?= e($s) ?>"></i><?php elseif (str_starts_with($v, 'texto:')): ?><small><?= e(substr($v, 6)) ?></small><?php else: ?><span class="nd">–</span><?php endif; ?></td>
                  <?php endforeach; ?>
                  <td class="mono"><small><?= $a['tempo'] !== '' ? e($a['tempo']) : '–' ?></small></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>
          <p class="sefaz-meta mono">
            <?= ($sit['fonte'] ?? 'portal') === 'fornecedor' ? 'leitura feita pelo Radar do fornecedor' : 'leitura feita no Portal da NF-e' ?>
            <?php if (!empty($sit['erro'])): ?> · última tentativa falhou: <?= e($sit['erro']) ?><?php endif; ?>
            · <a href="https://www.nfe.fazenda.gov.br/portal/disponibilidade.aspx" target="_blank" rel="noopener">abrir o portal</a>
          </p>
        </details>
        <?php endif; ?>
      <?php endif; ?>
    </section>
    <?php
}
