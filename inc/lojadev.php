<?php
/**
 * Radar Mage — sincronização com o Radar do fornecedor (radar.lojadev.com.br)
 *
 * Fontes lidas:
 *   dados.json   releases (notas de versão), issues (tarefas e Notas Técnicas), estatísticas do projeto
 *   agora.html   feed das últimas alterações, com hash e hora exata
 *   versoes.html alterações por área de cada versão e as contagens reais
 *
 * Regras:
 *   - registro criado pela sincronização tem origem = 'lojadev'
 *   - registro editado no painel ganha editado = 1 e a sincronização não o toca mais
 *   - registro manual (origem = 'manual') nunca é tocado
 */
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

const LOJADEV_TIMEOUT = 20;

function lojadev_url(string $arquivo): string {
    return rtrim(cfg('fonte_url', 'https://radar.lojadev.com.br'), '/') . '/' . $arquivo;
}

function lojadev_ativo(): bool { return cfg('sinc_ativo', '1') === '1'; }

function lojadev_relatorio(): ?array {
    $j = cfg('sinc_json'); if ($j === '') return null;
    $d = json_decode($j, true); return is_array($d) ? $d : null;
}

function lojadev_baixar(string $arquivo, ?string &$erro = null): ?string {
    return sefaz_baixar(lojadev_url($arquivo), $erro); // mesmo cURL, mesmo tratamento
}

/** Substituições de texto configuradas (uma por linha: "de=>para") */
function lojadev_substituir(string $texto): string {
    static $pares = null;
    if ($pares === null) {
        $pares = [];
        foreach (preg_split('/\r?\n/', cfg('sinc_substituicoes')) as $l) {
            if (strpos($l, '=>') === false) continue;
            [$de, $para] = array_map('trim', explode('=>', $l, 2));
            if ($de !== '') $pares[$de] = $para;
        }
    }
    return $pares ? strtr($texto, $pares) : $texto;
}

/** ISO 8601 (UTC ou com offset) -> 'Y-m-d H:i:s' no fuso local */
function lojadev_dt(?string $iso): ?string {
    if (!$iso) return null;
    try { return (new DateTime($iso))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s'); }
    catch (Throwable) { return null; }
}

/** "há 14 dias", "ontem", "há 2 meses", "há 13 horas" -> datetime aproximado a partir de uma referência */
function lojadev_relativo(string $txt, DateTime $ref): string {
    $d = clone $ref; $t = mb_strtolower(trim($txt));
    if ($t === 'ontem') $d->modify('-1 day');
    elseif (preg_match('/(\d+)\s*(min|hora|dia|m[êe]s|mes|ano)/u', $t, $m)) {
        $n = (int)$m[1];
        $u = match (true) { str_starts_with($m[2], 'min') => 'minutes', str_starts_with($m[2], 'hora') => 'hours', str_starts_with($m[2], 'dia') => 'days', str_starts_with($m[2], 'ano') => 'years', default => 'months' };
        $d->modify("-$n $u");
    }
    return $d->format('Y-m-d H:i:s');
}

/** Executa a sincronização completa. Retorna o relatório (também gravado em config). */
function lojadev_sincronizar(bool $forcar = false): array {
    $rel = ['em' => date('Y-m-d H:i:s'), 'ok' => false, 'erros' => [], 'contagem' => []];
    if (!lojadev_ativo() && !$forcar) { $rel['erros'][] = 'sincronização desligada nas configurações'; return $rel; }

    $lock = RADAR_ROOT . '/data/sinc.lock';
    if (file_exists($lock) && time() - filemtime($lock) < 300) { $rel['erros'][] = 'outra sincronização em andamento'; return $rel; }
    @touch($lock);

    try {
        $json = lojadev_baixar('dados.json', $e1);
        $dados = $json ? json_decode($json, true) : null;
        if (!is_array($dados) || empty($dados['releases'])) {
            $rel['erros'][] = 'dados.json: ' . ($e1 ?: 'resposta inválida');
        } else {
            $rel['contagem'] = array_merge($rel['contagem'], lojadev_importar_dados($dados));
            $rel['fonte_atualizada_em'] = lojadev_dt($dados['atualizado'] ?? null);
            $rel['versao_fonte'] = $dados['versao'] ?? '';
        }

        $agora = lojadev_baixar('agora.html', $e2);
        if ($agora) $rel['contagem'] = array_merge($rel['contagem'], lojadev_importar_feed($agora));
        else $rel['erros'][] = 'agora.html: ' . ($e2 ?: 'sem resposta');

        $versoes = lojadev_baixar('versoes.html', $e3);
        if ($versoes) $rel['contagem'] = array_merge($rel['contagem'], lojadev_importar_versoes_html($versoes, $rel['fonte_atualizada_em'] ?? null));
        else $rel['erros'][] = 'versoes.html: ' . ($e3 ?: 'sem resposta');

        $rel['ok'] = !empty($rel['contagem']);
    } catch (Throwable $ex) {
        $rel['erros'][] = 'falha interna: ' . $ex->getMessage();
    }

    @unlink($lock);
    cfg_set('sinc_json', json_encode($rel, JSON_UNESCAPED_UNICODE));
    return $rel;
}

/* ------------------------------------------------------------------ */
/* dados.json: releases, issues, estatísticas                          */
/* ------------------------------------------------------------------ */

function lojadev_importar_dados(array $d): array {
    $pdo = db(); $prod = produto();
    $c = ['versoes_novas' => 0, 'versoes_atualizadas' => 0, 'tarefas_novas' => 0, 'tarefas_atualizadas' => 0, 'nts_novas' => 0, 'nts_atualizadas' => 0];

    /* ---- releases -> versoes ---- */
    $releases = $d['releases'];
    usort($releases, fn($a, $b) => strcmp($a['data'], $b['data']));
    $porNumero = [];
    foreach ($releases as $r) {
        $numero = preg_replace('/^v/', '', (string)$r['versao']);
        if (!preg_match('/^\d+(\.\d+){0,3}$/', $numero)) continue;
        $p = lojadev_parse_corpo((string)$r['corpo']);
        $pub = lojadev_dt($r['data']);
        $st = $pdo->prepare("SELECT * FROM versoes WHERE produto=? AND numero=?"); $st->execute([$prod, $numero]); $v = $st->fetch();
        if (!$v) {
            $pdo->prepare("INSERT INTO versoes(produto, numero, codinome, resumo, notas, como_atualizar, publicada_em, criada_em, origem) VALUES (?,?,?,?,?,?,?,?,'lojadev')")
                ->execute([$prod, $numero, $p['codinome'], $p['resumo'], $p['notas'], $p['como_atualizar'], $pub, $pub]);
            $c['versoes_novas']++;
        } elseif ($v['origem'] === 'lojadev' && !$v['editado']) {
            $pdo->prepare("UPDATE versoes SET codinome=?, resumo=?, notas=?, como_atualizar=?, publicada_em=COALESCE(publicada_em, ?) WHERE id=?")
                ->execute([$p['codinome'], $p['resumo'], $p['notas'], $p['como_atualizar'], $pub, $v['id']]);
            $c['versoes_atualizadas']++;
        }
        $porNumero[$numero] = $pub;
    }
    // versão em preparo
    $atual = preg_replace('/^v/', '', (string)($d['versao'] ?? ''));
    if (preg_match('/^\d+(\.\d+){0,3}$/', $atual)) {
        $st = $pdo->prepare("SELECT id FROM versoes WHERE produto=? AND numero=?"); $st->execute([$prod, $atual]);
        if (!$st->fetchColumn()) {
            $pdo->prepare("INSERT INTO versoes(produto, numero, origem) VALUES (?,?,'lojadev')")->execute([$prod, $atual]);
            $c['versoes_novas']++;
        }
    }

    /* ---- issues -> tarefas (+ notas técnicas) ---- */
    // mapa data de publicação -> id, para "saiu na X"
    $pubs = $pdo->prepare("SELECT id, publicada_em FROM versoes WHERE produto=? AND publicada_em IS NOT NULL ORDER BY publicada_em");
    $pubs->execute([$prod]); $pubs = $pubs->fetchAll();
    $versaoDe = function (?string $fechada) use ($pubs): ?int {
        if (!$fechada) return null;
        foreach ($pubs as $p) if ($p['publicada_em'] >= $fechada) return (int)$p['id'];
        return null; // fechou depois da última publicada: sai na próxima
    };

    foreach ($d['issues'] ?? [] as $i) {
        $num = (int)$i['numero']; if (!$num) continue;
        $titulo = lojadev_substituir((string)$i['titulo']);
        $area = '';
        if (preg_match('/^\[([^\]]{2,30})\]\s*/u', $titulo, $m)) { $area = lojadev_area($m[1]); $titulo = trim(substr($titulo, strlen($m[0]))); }
        $labels = array_map('strval', $i['labels'] ?? []);
        $tipo = in_array('bug', $labels, true) ? 'bug' : (in_array('melhoria', $labels, true) ? 'melhoria' : (in_array('fiscal', $labels, true) ? 'fiscal' : 'geral'));
        $aberta = ($i['estado'] ?? '') === 'open';
        $status = $aberta ? 'aberta' : 'encerrada';
        $criada = lojadev_dt($i['criada'] ?? null) ?? date('Y-m-d H:i:s');
        $fechada = $aberta ? null : lojadev_dt($i['fechada'] ?? null);
        $resp = implode(', ', $i['responsaveis'] ?? []);
        $andamento = ($aberta && $resp !== '') ? 1 : 0;
        $versaoId = $aberta ? null : $versaoDe($fechada);
        $tituloCompleto = $titulo; $descricao = '';
        if (mb_strlen($titulo) > 90 && preg_match('/^(.{20,}?)\s+[—–]\s+(.{10,})$/u', $titulo, $m)) { $titulo = $m[1]; $descricao = $m[2]; }

        $st = $pdo->prepare("SELECT * FROM tarefas WHERE produto=? AND numero=?"); $st->execute([$prod, $num]); $t = $st->fetch();
        if (!$t) {
            $pdo->prepare("INSERT INTO tarefas(produto, numero, titulo, descricao, area, tipo, em_andamento, status, versao_id, criada_em, encerrada_em, origem, labels, responsaveis, comentarios) VALUES (?,?,?,?,?,?,?,?,?,?,?,'lojadev',?,?,?)")
                ->execute([$prod, $num, $titulo, $descricao, $area, $tipo, $andamento, $status, $versaoId, $criada, $fechada, implode(', ', $labels), $resp, (int)($i['comentarios'] ?? 0)]);
            $c['tarefas_novas']++;
            $tarefaId = (int)$pdo->lastInsertId();
        } else {
            $tarefaId = (int)$t['id'];
            if ($t['origem'] === 'lojadev' && !$t['editado']) {
                $pdo->prepare("UPDATE tarefas SET titulo=?, descricao=?, area=?, tipo=?, em_andamento=?, status=?, versao_id=COALESCE(?, versao_id), criada_em=?, encerrada_em=?, labels=?, responsaveis=?, comentarios=? WHERE id=?")
                    ->execute([$titulo, $descricao, $area, $tipo, $andamento, $status, $versaoId, $criada, $fechada, implode(', ', $labels), $resp, (int)($i['comentarios'] ?? 0), $tarefaId]);
                $c['tarefas_atualizadas']++;
            }
        }

        /* Nota Técnica: label fiscal pura e título de norma, ou com prazos */
        $prazos = is_array($i['prazos'] ?? null) ? $i['prazos'] : [];
        $ehNT = $prazos || ($labels === ['fiscal'] && preg_match('/^(NT|IT|Resolu[çc][ãa]o|Ato|Conv[êe]nio|Nota T[ée]cnica|Ajuste)\b/iu', $tituloCompleto));
        if ($ehNT) {
            $codigo = ''; $assunto = $tituloCompleto;
            if (preg_match('/^(.{3,45}?)\s+[-—–:]\s+(.+)$/u', $tituloCompleto, $m)) { $codigo = trim($m[1]); $assunto = trim($m[2]); }
            elseif (preg_match('/^((?:NT|IT)\s*\d{4}\.\d{3}(?:-RTC)?\s*v?\d+(?:\.\d+)*[a-z]?)\s*(.*)$/iu', $tituloCompleto, $m)) { $codigo = trim($m[1]); $assunto = trim($m[2]); }
            if ($codigo === '') $codigo = mb_strimwidth($tituloCompleto, 0, 40, '…');
            $ntStatus = $aberta ? 'aberta' : 'atendida';
            $st = $pdo->prepare("SELECT * FROM notas_tecnicas WHERE produto=? AND tarefa_id=?"); $st->execute([$prod, $tarefaId]); $nt = $st->fetch();
            if (!$nt) {
                $pdo->prepare("INSERT INTO notas_tecnicas(produto, codigo, assunto, homologacao, producao, tarefa_id, status, origem) VALUES (?,?,?,?,?,?,?,'lojadev')")
                    ->execute([$prod, $codigo, $assunto, $prazos['homologacao'] ?? null, $prazos['producao'] ?? null, $tarefaId, $ntStatus]);
                $c['nts_novas']++;
            } elseif ($nt['origem'] === 'lojadev' && !$nt['editado']) {
                $pdo->prepare("UPDATE notas_tecnicas SET codigo=?, assunto=?, homologacao=?, producao=?, status=? WHERE id=?")
                    ->execute([$codigo, $assunto, $prazos['homologacao'] ?? null, $prazos['producao'] ?? null, $ntStatus, $nt['id']]);
                $c['nts_atualizadas']++;
            }
        }
    }

    /* ---- estatísticas do projeto ---- */
    $stats = [
        'atualizado' => lojadev_dt($d['atualizado'] ?? null),
        'pulso' => $d['pulso'] ?? [], 'commits' => $d['commits'] ?? [], 'modulos' => $d['modulos'] ?? [],
        'linhas_entraram' => $d['linhas_entraram'] ?? [], 'linhas_sairam' => $d['linhas_sairam'] ?? [],
        'frentes' => $d['frentes'] ?? [], 'sistema' => $d['sistema'] ?? [],
    ];
    cfg_set('projeto_stats', json_encode($stats, JSON_UNESCAPED_UNICODE));
    return $c;
}

/** Nome de área a partir do prefixo [x] da issue */
function lojadev_area(string $x): string {
    $x = mb_strtolower(trim($x));
    return match ($x) {
        'fiscal' => 'Fiscal', 'pdv' => 'PDV', 'estoque' => 'Estoque', 'banco' => 'Banco', 'arquitetura' => 'Arquitetura',
        'desempenho' => 'Desempenho', 'tef' => 'TEF', 'geral' => 'Geral', 'nfs-e', 'nfse' => 'NFS-e', 'nf-e', 'nfe' => 'NF-e',
        default => mb_convert_case($x, MB_CASE_TITLE),
    };
}

/** Separa o corpo da release (markdown do GitHub) em codinome, resumo, notas e como atualizar */
function lojadev_parse_corpo(string $corpo): array {
    $corpo = lojadev_substituir(str_replace("\r", '', $corpo));
    $out = ['codinome' => '', 'resumo' => '', 'notas' => '', 'como_atualizar' => ''];
    $linhas = explode("\n", $corpo);
    // 1ª linha: "## 🧾 ERP 7.5.6 — Vender sem o servidor"
    if ($linhas && preg_match('/^#+\s*(.*)$/u', $linhas[0], $m)) {
        $t = $m[1];
        if (preg_match('/[—–-]\s*(.+)$/u', $t, $mm)) $out['codinome'] = trim($mm[1]);
        array_shift($linhas);
    }
    // "-# 29/10/2026 · 39 mudanças" (subtexto do GitHub)
    $linhas = array_values(array_filter($linhas, fn($l) => !preg_match('/^-#\s*\d{2}\/\d{2}\/\d{4}/', $l)));
    $linhas = array_map(fn($l) => preg_replace('/^-#\s*/', '', $l), $linhas);
    $texto = implode("\n", $linhas);
    if (preg_match('/\*\*Em uma linha:\*\*\s*(.+?)(?:\n\n|\n(?=[#⚠🆕🧾🔧⚡💬📥📢])|$)/su', $texto, $m)) {
        $out['resumo'] = trim(preg_replace('/\s+/', ' ', $m[1]));
        $texto = str_replace($m[0], "\n\n", $texto);
    }
    // títulos em negrito solto ("⚠️ **Leia antes de usar**") viram ###
    $texto = preg_replace('/^(?:\s*)([⚠🆕🧾🔧⚡💬📥📢🗑][\x{FE0F}]?)\s*\*\*(.+?)\*\*\s*$/mu', '### $1 $2', $texto);
    $texto = preg_replace('/^#{2}\s+/mu', '### ', $texto);   // nivela: tudo h3 dentro da versão
    $texto = preg_replace('/^#{4,}\s+/mu', '#### ', $texto);
    // separa "Como atualizar"
    if (preg_match('/^###\s*📥\s*Como atualizar\s*$/mu', $texto, $m, PREG_OFFSET_CAPTURE)) {
        $ini = $m[0][1];
        $resto = substr($texto, $ini + strlen($m[0][0]));
        if (preg_match('/^###\s/mu', $resto, $mm, PREG_OFFSET_CAPTURE)) {
            $out['como_atualizar'] = trim(substr($resto, 0, $mm[0][1]));
            $texto = substr($texto, 0, $ini) . substr($resto, $mm[0][1]);
        } else { $out['como_atualizar'] = trim($resto); $texto = substr($texto, 0, $ini); }
    }
    $out['notas'] = trim(preg_replace("/\n{3,}/", "\n\n", $texto));
    return $out;
}

/* ------------------------------------------------------------------ */
/* agora.html: feed com hash e hora                                    */
/* ------------------------------------------------------------------ */

function lojadev_importar_feed(string $html): array {
    $pdo = db(); $prod = produto(); $c = ['feed_novas' => 0, 'feed_atualizadas' => 0];
    if (!preg_match_all('#<div class="feed-item" data-em="([^"]+)">(.*?)<span class="feed-quando">#su', $html, $itens, PREG_SET_ORDER)) return $c;
    $proxima = versao_proxima();
    $sel = $pdo->prepare("SELECT id, origem, editado, ref, area, versao_id FROM alteracoes WHERE produto=? AND (ref=? OR titulo=?) ORDER BY ref=? DESC LIMIT 1");
    $ins = $pdo->prepare("INSERT INTO alteracoes(produto, versao_id, titulo, area, ref, interno, criada_em, origem) VALUES (?,?,?,?,?,0,?,'lojadev')");
    $upd = $pdo->prepare("UPDATE alteracoes SET ref=?, criada_em=? WHERE id=?");
    foreach ($itens as $it) {
        $em = lojadev_dt($it[1]); if (!$em) continue;
        $ref = preg_match('#mono-curto">([0-9a-f]{6,12})<#', $it[2], $m) ? $m[1] : '';
        $titulo = trim(html_entity_decode(strip_tags(preg_replace('#<span class="tags">.*$#su', '', $it[2])), ENT_QUOTES, 'UTF-8'));
        $titulo = lojadev_substituir(preg_replace('/\s+/', ' ', $titulo));
        if ($titulo === '') continue;
        $sel->execute([$prod, $ref ?: '-', $titulo, $ref ?: '-']); $a = $sel->fetch();
        if (!$a) {
            $versaoId = $proxima ? (int)$proxima['id'] : null;
            // alteração anterior à criação da versão em preparo pertence à publicada naquela data
            $v = $pdo->prepare("SELECT id FROM versoes WHERE produto=? AND publicada_em IS NOT NULL AND publicada_em >= ? ORDER BY publicada_em LIMIT 1");
            $v->execute([$prod, $em]); if ($x = $v->fetchColumn()) $versaoId = (int)$x;
            $ins->execute([$prod, $versaoId, $titulo, '', $ref, $em]); $c['feed_novas']++;
        } elseif ($a['origem'] === 'lojadev' && !$a['editado'] && ($a['ref'] === '' || $a['ref'] !== $ref)) {
            $upd->execute([$ref, $em, $a['id']]); $c['feed_atualizadas']++;
        }
    }
    return $c;
}

/* ------------------------------------------------------------------ */
/* versoes.html: alterações por área e contagens                       */
/* ------------------------------------------------------------------ */

function lojadev_importar_versoes_html(string $html, ?string $referencia): array {
    $pdo = db(); $prod = produto(); $c = ['areas_atribuidas' => 0, 'historico_novas' => 0];
    $ref = $referencia ? new DateTime($referencia) : new DateTime();
    // blocos de versão: <section class="cartao versao" id="v7-5-6" data-versao="7.5.6"> ... </section>
    $blocos = [];
    foreach (array_slice(explode('<section class="cartao versao"', $html), 1) as $peca) {
        $peca = explode('</section>', $peca, 2)[0];
        if (preg_match('/data-versao="([\d.]+)"/', $peca, $m) || preg_match('/id="v([\d-]+)"/', $peca, $m)) $blocos[] = [str_replace('-', '.', $m[1]), $peca];
    }
    // "Vindo na próxima" (cartao destaque) também traz áreas
    if (preg_match('#<section class="cartao destaque">(.*?)</section>#su', $html, $m)) {
        $p = versao_proxima();
        if ($p) $blocos[] = [$p['numero'], $m[1]];
    }
    $selT = $pdo->prepare("SELECT id, origem, editado, area, versao_id FROM alteracoes WHERE produto=? AND titulo=? LIMIT 1");
    $ins = $pdo->prepare("INSERT INTO alteracoes(produto, versao_id, titulo, area, ref, interno, criada_em, origem) VALUES (?,?,?,?,'',0,?,'lojadev')");
    $updArea = $pdo->prepare("UPDATE alteracoes SET area=CASE WHEN area='' THEN ? ELSE area END, versao_id=COALESCE(versao_id, ?) WHERE id=?");
    foreach ($blocos as [$numero, $corpo]) {
        $st = $pdo->prepare("SELECT id, origem, editado FROM versoes WHERE produto=? AND numero=?"); $st->execute([$prod, $numero]); $v = $st->fetch();
        if (!$v) continue;
        // contagens reais: "44 assuntos · 282 alterações · publicada em 16/10"
        if (preg_match('#<span class="cartao-meta">([^<]*)</span>#u', $corpo, $mm)) {
            $alt = preg_match('/(\d+)\s*altera/u', $mm[1], $x) ? (int)$x[1] : null;
            $ass = preg_match('/(\d+)\s*assunto/u', $mm[1], $x) ? (int)$x[1] : null;
            if ($v['origem'] === 'lojadev' && !$v['editado']) $pdo->prepare("UPDATE versoes SET alteracoes_total=?, assuntos_total=? WHERE id=?")->execute([$alt, $ass, $v['id']]);
        }
        foreach (array_slice(explode('<div class="area-bloco">', $corpo), 1) as $bloco) {
            if (!preg_match('#<span class="area-nome">([^<]*)</span>#u', $bloco, $an)) continue;
            $area = trim(html_entity_decode($an[1], ENT_QUOTES, 'UTF-8'));
            if ($area === 'Outros assuntos') $area = '';
            if (!preg_match_all('#<span class="area-txt">(.*?)</span><span class="area-quando">([^<]*)</span>#su', $bloco, $itens, PREG_SET_ORDER)) continue;
            foreach ($itens as $it) {
                $titulo = lojadev_substituir(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($it[1]), ENT_QUOTES, 'UTF-8'))));
                if ($titulo === '') continue;
                $selT->execute([$prod, $titulo]); $a = $selT->fetch();
                if ($a) {
                    if ($a['origem'] === 'lojadev' && !$a['editado'] && ($a['area'] === '' || !$a['versao_id'])) { $updArea->execute([$area, $v['id'], $a['id']]); $c['areas_atribuidas']++; }
                } else {
                    $ins->execute([$prod, $v['id'], $titulo, $area, lojadev_relativo($it[2], $ref)]); $c['historico_novas']++;
                }
            }
        }
    }
    return $c;
}
