<?php
/**
 * API do Radar Mage — para o Mage Lite ou o seu processo de build alimentarem o Radar.
 * Autenticação: cabeçalho  Authorization: Bearer TOKEN   (ou ?token=)  — token em Admin > Configurações > API.
 * Respostas em JSON.
 *
 *   GET  api.php?acao=versao                       versão atual, próxima e homologação
 *   GET  api.php?acao=versoes                      lista de versões publicadas
 *   GET  api.php?acao=sefaz                        situação por estado (pública, sem token)
 *   POST api.php?acao=alteracao   titulo, [area], [ref], [versao], [interno]      registra uma alteração
 *   POST api.php?acao=alteracoes  linhas (uma por linha, "Área: título")           registra várias
 *   POST api.php?acao=versao      numero, [codinome], [resumo], [notas], [como_atualizar], [publicar=1], [proxima]
 *   POST api.php?acao=homologar   numero, status (teste|liberada|ressalva|aguarde|''), [nota_mage]
 *   POST api.php?acao=cliente     chave, [versao], [uf]                             o sistema informa a versão instalada
 */
require_once __DIR__ . '/inc/functions.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

function responder(array $d, int $code = 200): never { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); exit; }
function api_token(): string { $t = cfg('api_token'); if ($t === '') { $t = bin2hex(random_bytes(16)); cfg_set('api_token', $t); } return $t; }

$acao = $_GET['acao'] ?? $_POST['acao'] ?? '';
$corpo = [];
if (($_SERVER['CONTENT_TYPE'] ?? '') && str_contains($_SERVER['CONTENT_TYPE'], 'application/json')) $corpo = json_decode(file_get_contents('php://input'), true) ?: [];
$in = fn(string $k, $padrao = '') => trim((string)($corpo[$k] ?? $_POST[$k] ?? $_GET[$k] ?? $padrao));

// autenticação (sefaz e versao em GET são públicos)
$publico = $_SERVER['REQUEST_METHOD'] === 'GET' && in_array($acao, ['sefaz', 'versao', 'versoes'], true);
if (!$publico) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = preg_match('/Bearer\s+(\S+)/i', $auth, $m) ? $m[1] : $in('token');
    if ($token === '' || !hash_equals(api_token(), $token)) responder(['erro' => 'token inválido'], 401);
}
$pdo = db(); $prod = produto();

switch ($acao) {
    case 'versao':
        $a = versao_atual(); $p = versao_proxima(); $u = versao_ultima_publicada();
        responder(['atual' => $u ? ['numero' => $u['numero'], 'codinome' => $u['codinome'], 'publicada_em' => $u['publicada_em'], 'homologacao' => $u['homologacao'] ?? '', 'nota_mage' => $u['nota_mage'] ?? ''] : null,
                   'proxima' => $p ? ['numero' => $p['numero'], 'alteracoes' => count(alteracoes_proxima())] : null]);
    case 'versoes':
        responder(['versoes' => array_values(array_map(fn($v) => ['numero' => $v['numero'], 'codinome' => $v['codinome'], 'publicada_em' => $v['publicada_em'], 'homologacao' => $v['homologacao'] ?? '', 'nota_mage' => $v['nota_mage'] ?? '', 'resumo' => $v['resumo']], array_filter(versoes_todas(), fn($v) => $v['publicada_em'])))]);
    case 'sefaz':
        $s = sefaz_situacao(); $e = sefaz_estados($s);
        responder(['lido_em' => $s['em'] ?? null, 'fonte' => $s['fonte'] ?? null, 'resumo' => sefaz_resumo($s), 'estados' => $e]);

    case 'alteracao':
        if ($in('titulo') === '') responder(['erro' => 'titulo obrigatório'], 400);
        $vid = null; if ($in('versao') !== '' && ($v = versao_por_numero($in('versao')))) $vid = (int)$v['id']; elseif ($p = versao_proxima()) $vid = (int)$p['id'];
        $pdo->prepare("INSERT INTO alteracoes(produto, versao_id, titulo, area, ref, interno, origem) VALUES (?,?,?,?,?,?,'api')")->execute([$prod, $vid, $in('titulo'), $in('area'), $in('ref'), $in('interno') ? 1 : 0]);
        responder(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
    case 'alteracoes':
        $n = 0; $p = versao_proxima();
        foreach (preg_split('/\r?\n/', $in('linhas')) as $l) {
            $l = trim($l); if ($l === '') continue; $area = $in('area'); $t = $l;
            if (preg_match('/^([^:]{2,40}):\s+(.+)$/u', $l, $m)) { $area = trim($m[1]); $t = trim($m[2]); }
            $pdo->prepare("INSERT INTO alteracoes(produto, versao_id, titulo, area, ref, interno, origem) VALUES (?,?,?,?,'',0,'api')")->execute([$prod, $p ? (int)$p['id'] : null, $t, $area]); $n++;
        }
        responder(['ok' => true, 'registradas' => $n], 201);
    case 'versao_post':
    case 'publicar':
        break;
    case 'homologar':
        $v = versao_por_numero($in('numero')); if (!$v) responder(['erro' => 'versão não encontrada'], 404);
        $h = $in('status'); if (!array_key_exists($h, homologacoes())) responder(['erro' => 'status inválido', 'aceitos' => array_keys(homologacoes())], 400);
        $pdo->prepare("UPDATE versoes SET homologacao=?, homologada_em=?, nota_mage=CASE WHEN ?='' THEN nota_mage ELSE ? END WHERE id=?")->execute([$h, $h ? date('Y-m-d H:i:s') : null, $in('nota_mage'), $in('nota_mage'), $v['id']]);
        responder(['ok' => true, 'numero' => $v['numero'], 'homologacao' => $h]);
    case 'cliente':
        $c = cliente_por_chave($in('chave')); if (!$c) responder(['erro' => 'chave não encontrada'], 404);
        $campos = []; $vals = [];
        if (preg_match('/^\d+(\.\d+){0,3}$/', $in('versao'))) { $campos[] = 'versao=?'; $vals[] = $in('versao'); $campos[] = "versao_informada_em=datetime('now','localtime')"; }
        if (in_array(strtoupper($in('uf')), ufs_brasil(), true)) { $campos[] = 'uf=?'; $vals[] = strtoupper($in('uf')); }
        $campos[] = "visto_em=datetime('now','localtime')";
        $pdo->prepare("UPDATE clientes SET " . implode(', ', $campos) . " WHERE id=?")->execute([...$vals, $c['id']]);
        $atual = versao_ultima_publicada();
        responder(['ok' => true, 'loja' => $c['nome'], 'versao_atual' => $atual['numero'] ?? null, 'homologacao' => $atual['homologacao'] ?? '', 'nota_mage' => $atual['nota_mage'] ?? '', 'atras' => ($in('versao') && $atual) ? versoes_atras($in('versao')) : null, 'link' => url_base() . '/c.php?k=' . $c['chave'] . '&ir=desde']);
}
if ($acao === 'versao' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero = $in('numero'); if (!preg_match('/^\d+(\.\d+){0,3}$/', $numero)) responder(['erro' => 'numero inválido'], 400);
    $v = versao_por_numero($numero);
    $pub = $in('publicar') === '1';
    if (!$v) { $pdo->prepare("INSERT INTO versoes(produto, numero, codinome, resumo, notas, como_atualizar, origem) VALUES (?,?,?,?,?,?,'api')")->execute([$prod, $numero, $in('codinome'), $in('resumo'), $in('notas'), $in('como_atualizar')]); $id = (int)$pdo->lastInsertId(); }
    else { $id = (int)$v['id']; $pdo->prepare("UPDATE versoes SET codinome=CASE WHEN ?='' THEN codinome ELSE ? END, resumo=CASE WHEN ?='' THEN resumo ELSE ? END, notas=CASE WHEN ?='' THEN notas ELSE ? END, como_atualizar=CASE WHEN ?='' THEN como_atualizar ELSE ? END, editado=1 WHERE id=?")->execute([$in('codinome'), $in('codinome'), $in('resumo'), $in('resumo'), $in('notas'), $in('notas'), $in('como_atualizar'), $in('como_atualizar'), $id]); }
    if ($pub && !($v['publicada_em'] ?? null)) {
        $pdo->prepare("UPDATE alteracoes SET versao_id=? WHERE produto=? AND versao_id IS NULL")->execute([$id, $prod]);
        $pdo->prepare("UPDATE versoes SET publicada_em=datetime('now','localtime') WHERE id=?")->execute([$id]);
        if (preg_match('/^\d+(\.\d+){0,3}$/', $in('proxima'))) $pdo->prepare("INSERT OR IGNORE INTO versoes(numero, produto, origem) VALUES (?,?,'api')")->execute([$in('proxima'), $prod]);
    }
    responder(['ok' => true, 'id' => $id, 'numero' => $numero, 'publicada' => $pub || !empty($v['publicada_em'])]);
}
responder(['erro' => 'ação desconhecida', 'acoes' => ['versao', 'versoes', 'sefaz', 'alteracao', 'alteracoes', 'homologar', 'cliente']], 400);
