<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sefaz.php';
require_once __DIR__ . '/lojadev.php';
require_once __DIR__ . '/clientes.php';
require_once __DIR__ . '/avisos.php';

/* ---------- escape ---------- */
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* ---------- datas ---------- */
function agora(): DateTime { return new DateTime('now'); }

function dt(?string $s): ?DateTime {
    if (!$s) return null;
    try { return new DateTime($s); } catch (Throwable) { return null; }
}

function data_br(?string $s, bool $ano = false): string {
    $d = dt($s); if (!$d) return '—';
    return $d->format($ano ? 'd/m/Y' : 'd/m');
}

function data_hora_br(?string $s, bool $completa = false): string {
    $d = dt($s); return $d ? $d->format($completa ? 'd/m/Y H:i:s' : 'd/m, H:i') : '—';
}

/** "há 6 horas", "ontem", "há 17 dias", "há 1 mês" */
function tempo_relativo(?string $s): string {
    $d = dt($s); if (!$d) return '';
    $seg = agora()->getTimestamp() - $d->getTimestamp();
    if ($seg < 60) return 'agora';
    $min = intdiv($seg, 60);
    if ($min < 60) return "há $min min";
    $h = intdiv($min, 60);
    if ($h < 24) return "há $h hora" . ($h > 1 ? 's' : '');
    $dias = intdiv($h, 24);
    if ($dias === 1) return 'ontem';
    if ($dias < 30) return "há $dias dias";
    $m = intdiv($dias, 30);
    if ($m < 12) return "há $m " . ($m > 1 ? 'meses' : 'mês');
    $a = intdiv($dias, 365);
    return "há $a ano" . ($a > 1 ? 's' : '');
}

/** dias entre hoje e uma data (positivo = futuro) */
function dias_ate(?string $s): ?int {
    $d = dt($s); if (!$d) return null;
    $hoje = new DateTime('today');
    $d->setTime(0, 0);
    return (int)$hoje->diff($d)->format('%r%a');
}

function rotulo_dia(string $s): string {
    $d = dt($s); if (!$d) return '';
    $hoje = new DateTime('today'); $ontem = (clone $hoje)->modify('-1 day');
    if ($d >= $hoje) return 'hoje';
    if ($d >= $ontem) return 'ontem';
    $meses = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
    $txt = $d->format('j') . ' de ' . $meses[(int)$d->format('n') - 1];
    if ($d->format('Y') !== $hoje->format('Y')) $txt .= ' de ' . $d->format('Y');
    return $txt;
}

/* ---------- versões ---------- */
function versoes_todas(bool $recarregar = false): array {
    static $c = null;
    if ($c !== null && !$recarregar) return $c;
    $rows = db()->prepare("SELECT * FROM versoes WHERE produto = ? ORDER BY publicada_em IS NULL DESC, criada_em DESC");
    $rows->execute([produto()]);
    $c = $rows->fetchAll();
    usort($c, fn($a, $b) => version_compare($b['numero'], $a['numero']));
    return $c;
}

function versao_proxima(): ?array {
    foreach (versoes_todas() as $v) if (!$v['publicada_em']) return $v;
    return null;
}

function versao_ultima_publicada(): ?array {
    foreach (versoes_todas() as $v) if ($v['publicada_em']) return $v;
    return null;
}

function versao_atual(): ?array { return versao_proxima() ?? versao_ultima_publicada(); }

function versao_por_id(?int $id): ?array {
    if (!$id) return null;
    foreach (versoes_todas() as $v) if ((int)$v['id'] === $id) return $v;
    return null;
}

/** Versão informada pelo visitante (cookie gravado pelo app.js) */
function versao_visitante(): string {
    $v = trim($_COOKIE['radar_versao'] ?? '');
    return preg_match('/^\d+(\.\d+){0,3}$/', $v) ? $v : '';
}

/** true quando a versão ainda não chegou na instalação do visitante */
function falta_para_visitante(array $versao): bool {
    $minha = versao_visitante();
    if ($minha === '') return false;
    return version_compare($versao['numero'], $minha, '>');
}

/* ---------- alterações ---------- */
function alteracoes_da_versao(?int $versao_id, bool $incluir_internas = false): array {
    $sql = "SELECT a.*, v.numero AS versao_numero FROM alteracoes a LEFT JOIN versoes v ON v.id = a.versao_id WHERE a.produto = ? AND "
         . ($versao_id ? "a.versao_id = ?" : "a.versao_id IS NULL")
         . ($incluir_internas ? "" : " AND a.interno = 0")
         . " ORDER BY a.criada_em DESC, a.id DESC";
    $st = db()->prepare($sql);
    $st->execute($versao_id ? [produto(), $versao_id] : [produto()]);
    return $st->fetchAll();
}

/** Alterações "vindo na próxima": as da versão não publicada + as sem versão */
function alteracoes_proxima(bool $incluir_internas = false): array {
    $p = versao_proxima();
    $lista = alteracoes_da_versao(null, $incluir_internas);
    if ($p) $lista = array_merge(alteracoes_da_versao((int)$p['id'], $incluir_internas), $lista);
    usort($lista, fn($a, $b) => strcmp($b['criada_em'], $a['criada_em']));
    return $lista;
}

function alteracoes_internas_proxima(): int {
    $p = versao_proxima();
    $sql = "SELECT COUNT(*) FROM alteracoes WHERE produto = ? AND interno = 1 AND (versao_id IS NULL" . ($p ? " OR versao_id = " . (int)$p['id'] : "") . ")";
    $st = db()->prepare($sql); $st->execute([produto()]);
    return (int)$st->fetchColumn();
}

function alteracoes_recentes(int $limite = 8): array {
    $st = db()->prepare("SELECT a.*, v.numero AS versao_numero FROM alteracoes a LEFT JOIN versoes v ON v.id = a.versao_id WHERE a.produto = ? AND a.interno = 0 ORDER BY a.criada_em DESC, a.id DESC LIMIT ?");
    $st->execute([produto(), $limite]);
    return $st->fetchAll();
}

function agrupar_por_area(array $alteracoes): array {
    $g = [];
    foreach ($alteracoes as $a) {
        $area = trim((string)$a['area']) ?: 'Outros assuntos';
        $g[$area][] = $a;
    }
    // ordena por quantidade, "Outros assuntos" por último
    uksort($g, function ($x, $y) use ($g) {
        if ($x === 'Outros assuntos') return 1;
        if ($y === 'Outros assuntos') return -1;
        return count($g[$y]) <=> count($g[$x]) ?: strcmp($x, $y);
    });
    return $g;
}

/** commits por semana nas últimas N semanas (índice 0 = semana mais antiga) */
function pulso_semanal(int $semanas = 8): array {
    $inicio = (new DateTime('monday this week'))->modify('-' . ($semanas - 1) . ' weeks')->setTime(0, 0);
    $st = db()->prepare("SELECT criada_em FROM alteracoes WHERE produto = ? AND criada_em >= ?");
    $st->execute([produto(), $inicio->format('Y-m-d H:i:s')]);
    $barras = array_fill(0, $semanas, 0);
    foreach ($st as $r) {
        $d = dt($r['criada_em']);
        $i = (int)floor(($d->getTimestamp() - $inicio->getTimestamp()) / (7 * 86400));
        if ($i >= 0 && $i < $semanas) $barras[$i]++;
    }
    return $barras;
}

/** Estatísticas do projeto vindas da sincronização (ou null) */
function projeto_stats(): ?array {
    $j = cfg('projeto_stats'); if ($j === '') return null;
    $d = json_decode($j, true); return is_array($d) && !empty($d['pulso']) ? $d : null;
}

/** Alterações por dia nos últimos N dias (do próprio banco) — [['Y-m-d', n], ...] */
function pulso_diario(int $dias = 56): array {
    $ini = (new DateTime('today'))->modify('-' . ($dias - 1) . ' days');
    $st = db()->prepare("SELECT substr(criada_em,1,10) d, COUNT(*) n FROM alteracoes WHERE produto=? AND criada_em >= ? GROUP BY d");
    $st->execute([produto(), $ini->format('Y-m-d')]);
    $m = []; foreach ($st as $r) $m[$r['d']] = (int)$r['n'];
    $out = []; $d = clone $ini;
    for ($i = 0; $i < $dias; $i++) { $k = $d->format('Y-m-d'); $out[] = [$k, $m[$k] ?? 0]; $d->modify('+1 day'); }
    return $out;
}

function areas_conhecidas(): array {
    $st = db()->prepare("SELECT area, COUNT(*) n FROM alteracoes WHERE produto = ? AND area <> '' GROUP BY area ORDER BY n DESC");
    $st->execute([produto()]);
    return array_column($st->fetchAll(), 'area');
}

/* ---------- tarefas ---------- */
function tarefas_abertas(): array {
    $st = db()->prepare("SELECT * FROM tarefas WHERE produto = ? AND status = 'aberta' AND id NOT IN (SELECT tarefa_id FROM notas_tecnicas WHERE tarefa_id IS NOT NULL AND status = 'aberta') ORDER BY em_andamento DESC, criada_em DESC");
    $st->execute([produto()]);
    return $st->fetchAll();
}

function tarefas_encerradas(int $limite = 60): array {
    $st = db()->prepare("SELECT t.*, v.numero AS versao_numero FROM tarefas t LEFT JOIN versoes v ON v.id = t.versao_id WHERE t.produto = ? AND t.status = 'encerrada' ORDER BY t.encerrada_em DESC LIMIT ?");
    $st->execute([produto(), $limite]);
    return $st->fetchAll();
}

function tarefas_da_versao(int $versao_id): array {
    $st = db()->prepare("SELECT * FROM tarefas WHERE produto = ? AND versao_id = ? ORDER BY numero");
    $st->execute([produto(), $versao_id]);
    return $st->fetchAll();
}

/** [quantidade encerradas em 30 dias, mediana em dias] */
function entregues_30_dias(): array {
    $st = db()->prepare("SELECT criada_em, encerrada_em FROM tarefas WHERE produto = ? AND status = 'encerrada' AND encerrada_em >= datetime('now','localtime','-30 days')");
    $st->execute([produto()]);
    $duracoes = [];
    foreach ($st as $r) {
        $a = dt($r['criada_em']); $b = dt($r['encerrada_em']);
        if ($a && $b) $duracoes[] = max(0, ($b->getTimestamp() - $a->getTimestamp()) / 86400);
    }
    return [count($duracoes), mediana($duracoes)];
}

function mediana_encerradas(): array {
    $st = db()->prepare("SELECT criada_em, encerrada_em FROM tarefas WHERE produto = ? AND status = 'encerrada'");
    $st->execute([produto()]);
    $d = [];
    foreach ($st as $r) { $a = dt($r['criada_em']); $b = dt($r['encerrada_em']); if ($a && $b) $d[] = max(0, ($b->getTimestamp() - $a->getTimestamp()) / 86400); }
    return [count($d), mediana($d)];
}

function mediana(array $v): ?float {
    if (!$v) return null;
    sort($v); $n = count($v);
    return $n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
}

function fmt_dias(?float $d): string {
    if ($d === null) return '—';
    return number_format($d, 1, ',', '.') . ' dia' . ($d >= 2 ? 's' : '');
}

/* ---------- notas técnicas ---------- */
function nts_abertas(): array {
    $st = db()->prepare("SELECT n.*, t.numero AS tarefa_numero FROM notas_tecnicas n LEFT JOIN tarefas t ON t.id = n.tarefa_id WHERE n.produto = ? AND n.status = 'aberta'");
    $st->execute([produto()]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) $r['_prazo'] = nt_prazo($r);
    unset($r);
    usort($rows, function ($a, $b) {
        $x = $a['_prazo']['dias'] ?? PHP_INT_MAX; $y = $b['_prazo']['dias'] ?? PHP_INT_MAX;
        return $x <=> $y;
    });
    return $rows;
}

function nts_atendidas(): array {
    $st = db()->prepare("SELECT n.*, t.numero AS tarefa_numero FROM notas_tecnicas n LEFT JOIN tarefas t ON t.id = n.tarefa_id WHERE n.produto = ? AND n.status <> 'aberta' ORDER BY n.producao DESC");
    $st->execute([produto()]);
    return $st->fetchAll();
}

/** Qual prazo cobra primeiro: ['etapa'=>'homologação|produção', 'dias'=>N] ou null */
function nt_prazo(array $nt): ?array {
    $h = dias_ate($nt['homologacao']); $p = dias_ate($nt['producao']);
    $cands = [];
    if ($h !== null && $h >= 0) $cands[] = ['etapa' => 'homologação', 'dias' => $h];
    if ($p !== null && $p >= 0) $cands[] = ['etapa' => 'produção', 'dias' => $p];
    if (!$cands) {
        if ($p !== null) return ['etapa' => 'produção', 'dias' => $p]; // já venceu
        if ($h !== null) return ['etapa' => 'homologação', 'dias' => $h];
        return null;
    }
    usort($cands, fn($a, $b) => $a['dias'] <=> $b['dias']);
    return $cands[0];
}

/* ---------- markdown leve ---------- */
function md(string $texto): string {
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    $linhas = explode("\n", $texto);
    $html = ''; $lista = false; $par = [];

    $flush = function () use (&$par, &$html) {
        if ($par) { $html .= '<p>' . md_inline(implode(' ', $par)) . '</p>'; $par = []; }
    };
    $fechaLista = function () use (&$lista, &$html) { if ($lista) { $html .= '</ul>'; $lista = false; } };

    foreach ($linhas as $l) {
        $t = rtrim($l);
        if ($t === '') { $flush(); $fechaLista(); continue; }
        if (preg_match('/^(#{2,4})\s+(.*)$/', $t, $m)) {
            $flush(); $fechaLista();
            $n = strlen($m[1]);
            $conteudo = $m[2];
            $classe = '';
            if (mb_strpos($conteudo, '⚠') !== false) $classe = ' class="md-aviso"';
            elseif (preg_match('/^(🆕|🧾|🔧|⚡|💬|📥|📢|🗑)/u', $conteudo)) $classe = ' class="md-secao"';
            $html .= "<h$n$classe>" . md_inline($conteudo) . "</h$n>";
            continue;
        }
        if (preg_match('/^[-*]\s+(.*)$/', $t, $m)) {
            $flush();
            if (!$lista) { $html .= '<ul>'; $lista = true; }
            $html .= '<li>' . md_inline($m[1]) . '</li>';
            continue;
        }
        if ($lista && preg_match('/^\s{2,}(.*)$/', $l, $m)) { // continuação de item
            $html = preg_replace('/<\/li>$/', ' ' . md_inline($m[1]) . '</li>', $html);
            continue;
        }
        $fechaLista();
        $par[] = $t;
    }
    $flush(); $fechaLista();
    return $html;
}

function md_inline(string $s): string {
    $s = e($s);
    $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);
    $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $s);
    $s = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $s);
    return $s;
}

/* ---------- homologação Mage ---------- */
function homologacoes(): array {
    return ['' => 'sem avaliação', 'teste' => 'em teste na Mage', 'liberada' => 'liberada pela Mage', 'ressalva' => 'liberada com ressalva', 'aguarde' => 'aguarde: não atualize ainda'];
}
function homologacao_classe(string $h): string {
    return match ($h) { 'liberada' => 'ok', 'ressalva' => 'inst', 'aguarde' => 'fora', 'teste' => 'teste', default => '' };
}
function selo_homologacao(array $v): string {
    $h = (string)($v['homologacao'] ?? ''); if ($h === '') return '';
    return '<span class="selo mono selo-h-' . homologacao_classe($h) . '" title="' . ($v['homologada_em'] ? 'desde ' . data_br($v['homologada_em'], true) : '') . '">' . e(homologacoes()[$h] ?? $h) . '</span>';
}

/** Versões publicadas mais novas que $minha, da mais antiga para a mais nova */
function versoes_desde(string $minha): array {
    $out = [];
    foreach (versoes_todas() as $v) if ($v['publicada_em'] && version_compare($v['numero'], $minha, '>')) $out[] = $v;
    usort($out, fn($a, $b) => version_compare($a['numero'], $b['numero']));
    return $out;
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

function versao_por_numero(string $numero): ?array {
    $st = db()->prepare("SELECT * FROM versoes WHERE produto = ? AND numero = ?"); $st->execute([produto(), $numero]);
    return $st->fetch() ?: null;
}

function tarefa_por_numero(int $numero): ?array {
    $st = db()->prepare("SELECT t.*, v.numero AS versao_numero FROM tarefas t LEFT JOIN versoes v ON v.id = t.versao_id WHERE t.produto = ? AND t.numero = ?");
    $st->execute([produto(), $numero]);
    return $st->fetch() ?: null;
}

function url_base(): string {
    $u = rtrim(cfg('radar_url'), '/');
    if ($u !== '') return $u;
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    // caminho do Radar dentro do site = pasta do projeto menos a raiz do documento (funciona também de dentro de /admin)
    $raiz = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ''), '/');
    $proj = str_replace('\\', '/', realpath(RADAR_ROOT) ?: RADAR_ROOT);
    $caminho = ($raiz !== '' && str_starts_with($proj, $raiz)) ? substr($proj, strlen($raiz)) : preg_replace('#/admin$#', '', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\'));
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim($caminho, '/');
}

/* ---------- diário da Sefaz ---------- */
/** Resumo do mês (Y-m): por autorizador, quedas, tempo fora, pior hora; e total */
function sefaz_diario(string $mes): array {
    $ini = $mes . '-01 00:00:00'; $fim = date('Y-m-d H:i:s', strtotime($ini . ' +1 month'));
    $st = db()->prepare("SELECT * FROM sefaz_quedas WHERE inicio < ? AND (fim IS NULL OR fim >= ?) ORDER BY inicio");
    $st->execute([$fim, $ini]);
    $por = []; $horas = array_fill(0, 24, 0); $vistas = [];
    foreach ($st as $q) {
        $k = $q['autorizador'] . '|' . $q['inicio']; // uma queda de autorizador conta uma vez
        if (isset($vistas[$k])) { $por[$q['autorizador']]['ufs'][$q['uf']] = true; continue; }
        $vistas[$k] = true;
        $a = $q['autorizador'] ?: $q['uf'];
        $por[$a] ??= ['quedas' => 0, 'vermelhas' => 0, 'segundos' => 0, 'ufs' => [], 'maior' => 0];
        $i = max(strtotime($q['inicio']), strtotime($ini)); $f = min($q['fim'] ? strtotime($q['fim']) : time(), strtotime($fim));
        $dur = max(0, $f - $i);
        $por[$a]['quedas']++; if ($q['pior'] === 'vermelha') $por[$a]['vermelhas']++;
        $por[$a]['segundos'] += $dur; $por[$a]['maior'] = max($por[$a]['maior'], $dur); $por[$a]['ufs'][$q['uf']] = true;
        for ($t = $i; $t < $f; $t += 3600) $horas[(int)date('G', $t)]++;
    }
    uasort($por, fn($x, $y) => $y['segundos'] <=> $x['segundos']);
    return ['por' => $por, 'horas' => $horas, 'total_quedas' => array_sum(array_column($por, 'quedas')), 'total_segundos' => array_sum(array_column($por, 'segundos'))];
}

function duracao_seg(int $s): string {
    if ($s < 60) return $s ? 'menos de 1 min' : '0 min';
    $m = intdiv($s, 60); if ($m < 60) return "$m min";
    $h = intdiv($m, 60); $m %= 60; if ($h < 24) return $h . 'h' . ($m ? sprintf('%02d', $m) : '');
    $d = intdiv($h, 24); return "$d " . ($d > 1 ? 'dias' : 'dia') . ($h % 24 ? ' e ' . ($h % 24) . 'h' : '');
}

/* ---------- etiquetas ---------- */
function tipo_classe(string $tipo): string {
    return match ($tipo) { 'bug' => 'tag-bug', 'fiscal' => 'tag-fiscal', 'melhoria' => 'tag-melhoria', default => 'tag-geral' };
}

function plural(int $n, string $sing, string $plu): string { return $n === 1 ? $sing : $plu; }
