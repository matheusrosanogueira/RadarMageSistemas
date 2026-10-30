<?php
/**
 * Radar Mage — situação dos autorizadores da Sefaz
 *
 * Lê a página pública de disponibilidade do Portal da NF-e
 * (https://www.nfe.fazenda.gov.br/portal/disponibilidade.aspx), que mostra
 * cada autorizador com bolinha verde / amarela / vermelha por serviço.
 * Não exige certificado digital. O resultado fica em cache na tabela config.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

const SEFAZ_URL = 'https://www.nfe.fazenda.gov.br/portal/disponibilidade.aspx';
// o portal redireciona para a versão com parâmetros; tentamos as duas
const SEFAZ_URLS = [
    'https://www.nfe.fazenda.gov.br/portal/disponibilidade.aspx?versao=4.00&tipoConteudo=Skeuqr8PQBY=',
    'https://www.nfe.fazenda.gov.br/portal/disponibilidade.aspx',
];
const SEFAZ_CACHE_MIN = 10;   // idade máxima do cache para a página tentar renovar sozinha
const SEFAZ_TIMEOUT = 8;      // segundos

/** UFs atendidas por cada autorizador (para contar "estados no ar") */
function sefaz_ufs(): array {
    return [
        'AM' => ['AM'], 'BA' => ['BA'], 'GO' => ['GO'], 'MG' => ['MG'], 'MS' => ['MS'],
        'MT' => ['MT'], 'PE' => ['PE'], 'PR' => ['PR'], 'RS' => ['RS'], 'SP' => ['SP'],
        'SVAN' => ['MA', 'PA'],
        'SVRS' => ['AC', 'AL', 'AP', 'CE', 'DF', 'ES', 'PB', 'PI', 'RJ', 'RN', 'RO', 'RR', 'SC', 'SE', 'TO'],
    ];
}

/** Colunas da tabela do portal, na ordem em que aparecem */
function sefaz_servicos(): array {
    return ['Autorização', 'Retorno Autorização', 'Inutilização', 'Consulta Protocolo', 'Status Serviço', 'Tempo Médio', 'Consulta Cadastro', 'Recepção Evento'];
}

/** Retorna o cache: ['em'=>datetime, 'autorizadores'=>[...], 'erro'=>?] ou null */
function sefaz_cache(): ?array {
    $j = cfg('sefaz_json');
    if ($j === '') return null;
    $d = json_decode($j, true);
    return is_array($d) ? $d : null;
}

function sefaz_ativo(): bool { return cfg('sefaz_ativo', '1') === '1'; }

function sefaz_token(): string {
    $t = cfg('sefaz_token');
    if ($t === '') { $t = bin2hex(random_bytes(12)); cfg_set('sefaz_token', $t); }
    return $t;
}

/** Cache válido ou renovação (respeita a idade máxima). Nunca lança exceção. */
function sefaz_situacao(bool $forcar = false): ?array {
    if (!sefaz_ativo()) return null;
    $c = sefaz_cache();
    $idade = $c && !empty($c['em']) ? (time() - strtotime($c['em'])) / 60 : PHP_INT_MAX;
    if (!$forcar && $c && $idade < SEFAZ_CACHE_MIN) return $c;

    // evita que várias visitas simultâneas disparem várias consultas
    $lock = RADAR_ROOT . '/data/sefaz.lock';
    if (!$forcar && file_exists($lock) && time() - filemtime($lock) < 60) return $c;
    @touch($lock);

    // 1ª fonte: o Portal da NF-e. 2ª fonte: o Radar do fornecedor, que lê o mesmo portal.
    $novo = sefaz_consultar();
    if (!$novo['autorizadores']) {
        $alt = sefaz_consultar_fornecedor();
        if ($alt['autorizadores']) { $alt['erro_portal'] = $novo['erro']; $novo = $alt; }
        else $novo['erro'] .= ' · fornecedor: ' . $alt['erro'];
    }

    if ($novo['autorizadores']) {
        $novo['falhas_seguidas'] = 0;
        cfg_set('sefaz_json', json_encode($novo, JSON_UNESCAPED_UNICODE));
        sefaz_registrar_historico($novo);
        @unlink($lock);
        return $novo;
    }
    // falhou nas duas: mantém a última leitura boa, registra o erro e conta a falha
    if ($c) {
        $c['erro'] = $novo['erro']; $c['tentativa_em'] = $novo['em']; $c['falhas_seguidas'] = (int)($c['falhas_seguidas'] ?? 0) + 1;
        cfg_set('sefaz_json', json_encode($c, JSON_UNESCAPED_UNICODE)); @unlink($lock); return $c;
    }
    cfg_set('sefaz_json', json_encode($novo, JSON_UNESCAPED_UNICODE));
    @unlink($lock);
    return $novo;
}

/** Fonte alternativa: o sefaz.php do Radar do fornecedor (mesma leitura do portal, feita por outro servidor) */
function sefaz_consultar_fornecedor(): array {
    $res = ['em' => date('Y-m-d H:i:s'), 'autorizadores' => [], 'erro' => null, 'fonte' => 'fornecedor'];
    $base = rtrim(cfg('fonte_url', 'https://radar.lojadev.com.br'), '/');
    $json = sefaz_baixar($base . '/sefaz.php', $erro);
    if ($json === null) { $res['erro'] = $erro ?: 'sem resposta'; return $res; }
    $d = json_decode($json, true);
    if (!is_array($d) || empty($d['autorizadores'])) { $res['erro'] = 'resposta inválida'; return $res; }
    foreach ($d['autorizadores'] as $a) {
        $sigla = strtoupper((string)($a['uf'] ?? $a['sigla'] ?? '')); if ($sigla === '') continue;
        $servicos = []; $tempo = '';
        foreach ((array)($a['servicos'] ?? []) as $k => $v) {
            if ($k === 'Tempo Médio') { $tempo = (string)($v ?? ''); continue; }
            $servicos[$k] = sefaz_cor_do_valor($v);
        }
        $res['autorizadores'][] = ['sigla' => $sigla, 'nome' => $sigla, 'servicos' => $servicos, 'tempo' => $tempo];
    }
    if (!empty($d['verificado_em'])) $res['portal_verificou_em'] = (string)$d['verificado_em'];
    if (!empty($d['velho'])) $res['fonte_velha'] = true;
    return $res;
}

/** Traduz o vocabulário de outra fonte para verde / amarela / vermelha / nd */
function sefaz_cor_do_valor($v): string {
    if ($v === null || $v === '' || $v === '-') return 'nd';
    $s = mb_strtolower((string)$v);
    if (in_array($s, ['ok', 'verde', 'green', 'disponivel', 'disponível', 'no ar'], true)) return 'verde';
    if (preg_match('/vermelh|fora|indispon|erro|red|down|off/u', $s)) return 'vermelha';
    if (preg_match('/amarel|lent|inst|yellow|parcial/u', $s)) return 'amarela';
    return 'texto:' . (string)$v;
}

/* ---------- histórico e classificação por estado ---------- */

/** Grava a leitura no histórico (uma linha por leitura, com a cor de cada UF) e fecha/abre quedas */
function sefaz_registrar_historico(array $sit): void {
    $pdo = db();
    $estados = sefaz_cores_por_uf($sit);
    if (!$estados) return;
    $em = $sit['em'];
    $pdo->prepare("INSERT INTO sefaz_historico(em, fonte, estados) VALUES (?,?,?)")->execute([$em, $sit['fonte'] ?? 'portal', json_encode($estados)]);
    $pdo->prepare("DELETE FROM sefaz_historico WHERE em < datetime('now','localtime','-7 days')")->execute();

    // quedas: abre quando a UF sai do verde, fecha quando volta
    $abertas = [];
    foreach ($pdo->query("SELECT * FROM sefaz_quedas WHERE fim IS NULL") as $q) $abertas[$q['uf']] = $q;
    $ins = $pdo->prepare("INSERT INTO sefaz_quedas(uf, autorizador, inicio, pior) VALUES (?,?,?,?)");
    $pior = $pdo->prepare("UPDATE sefaz_quedas SET pior='vermelha' WHERE id=?");
    $fecha = $pdo->prepare("UPDATE sefaz_quedas SET fim=? WHERE id=?");
    $autor = sefaz_autorizador_por_uf();
    foreach ($estados as $uf => $cor) {
        if ($cor === 'verde') { if (isset($abertas[$uf])) $fecha->execute([$em, $abertas[$uf]['id']]); }
        else {
            if (!isset($abertas[$uf])) $ins->execute([$uf, $autor[$uf] ?? '', $em, $cor]);
            elseif ($cor === 'vermelha' && $abertas[$uf]['pior'] !== 'vermelha') $pior->execute([$abertas[$uf]['id']]);
        }
    }
    $pdo->prepare("DELETE FROM sefaz_quedas WHERE fim IS NOT NULL AND fim < datetime('now','localtime','-90 days')")->execute();
}

function sefaz_autorizador_por_uf(): array {
    $m = [];
    foreach (sefaz_ufs() as $sigla => $ufs) foreach ($ufs as $uf) $m[$uf] = $sigla;
    return $m;
}

/** ['AC' => 'verde', ...] a partir de uma leitura */
function sefaz_cores_por_uf(?array $sit): array {
    $out = [];
    if (!$sit || empty($sit['autorizadores'])) return $out;
    $map = sefaz_ufs();
    foreach ($sit['autorizadores'] as $a) {
        foreach ($map[$a['sigla']] ?? [] as $uf) if (!isset($out[$uf])) $out[$uf] = sefaz_cor_geral($a);
    }
    ksort($out);
    return $out;
}

/**
 * Situação de cada UF, com memória:
 *   no_ar    verde agora e nos últimos 10 minutos
 *   instavel saiu do verde agora pela primeira vez, ou voltou ao verde há menos de 10 minutos
 *   fora     fora do verde em duas leituras seguidas ou mais
 */
function sefaz_estados(?array $sit): array {
    $atual = sefaz_cores_por_uf($sit);
    if (!$atual) return [];
    $pdo = db();
    $hist = $pdo->query("SELECT em, estados FROM sefaz_historico ORDER BY em DESC LIMIT 12")->fetchAll();
    $leituras = array_map(fn($h) => ['em' => $h['em'], 'estados' => json_decode($h['estados'], true) ?: []], $hist);
    $quedas = [];
    foreach ($pdo->query("SELECT uf, inicio FROM sefaz_quedas WHERE fim IS NULL") as $q) $quedas[$q['uf']] = $q['inicio'];
    $autor = sefaz_autorizador_por_uf();
    $limite10 = date('Y-m-d H:i:s', time() - 600);
    $out = [];
    foreach ($atual as $uf => $cor) {
        $anterior = $leituras[1]['estados'][$uf] ?? null; // [0] é a leitura atual já gravada
        if ($cor !== 'verde') $sit = ($anterior !== null && $anterior !== 'verde') ? 'fora' : 'instavel';
        else {
            $sit = 'no_ar';
            foreach ($leituras as $l) { if ($l['em'] < $limite10) break; if (($l['estados'][$uf] ?? 'verde') !== 'verde') { $sit = 'instavel'; break; } }
        }
        $out[$uf] = ['cor' => $cor, 'situacao' => $sit, 'autorizador' => $autor[$uf] ?? '', 'desde' => $quedas[$uf] ?? null];
    }
    return $out;
}

/** Quedas (abertas e fechadas) do período, mais recentes primeiro */
function sefaz_quedas(int $dias = 7): array {
    $st = db()->prepare("SELECT * FROM sefaz_quedas WHERE inicio >= datetime('now','localtime', ?) ORDER BY inicio DESC LIMIT 100");
    $st->execute(["-$dias days"]);
    return $st->fetchAll();
}

function sefaz_quedas_hoje(): int {
    return (int)db()->query("SELECT COUNT(*) FROM sefaz_quedas WHERE inicio >= date('now','localtime')")->fetchColumn();
}

/** Frase do cabeçalho: "Sefaz: os 27 estados no ar" / "Sefaz: 2 estados fora" */
function sefaz_frase(?array $sit): string {
    $e = sefaz_estados($sit);
    if (!$e) return 'Sefaz: sem leitura';
    $fora = array_keys(array_filter($e, fn($x) => $x['situacao'] === 'fora'));
    $inst = array_keys(array_filter($e, fn($x) => $x['situacao'] === 'instavel'));
    if ($fora) return 'Sefaz: ' . (count($fora) > 3 ? count($fora) . ' estados fora' : implode(', ', $fora) . ' fora do ar');
    if ($inst) return 'Sefaz: ' . (count($inst) > 3 ? count($inst) . ' estados instáveis' : implode(', ', $inst) . ' instável');
    return 'Sefaz: os ' . count($e) . ' estados no ar';
}

/** Duração legível entre dois datetimes */
function duracao(string $a, ?string $b): string {
    $s = (($b ? strtotime($b) : time()) - strtotime($a));
    if ($s < 60) return 'menos de 1 min';
    $m = intdiv($s, 60); if ($m < 60) return "$m min";
    $h = intdiv($m, 60); $m %= 60;
    if ($h < 24) return $h . 'h' . ($m ? sprintf('%02d', $m) : '');
    $d = intdiv($h, 24); return "$d " . ($d > 1 ? 'dias' : 'dia');
}

/** Baixa e interpreta a página do portal */
function sefaz_consultar(): array {
    $res = ['em' => date('Y-m-d H:i:s'), 'autorizadores' => [], 'erro' => null, 'fonte' => 'portal'];
    $erros = [];
    foreach (SEFAZ_URLS as $url) {
        $html = sefaz_baixar($url, $erro);
        if ($html === null) { $erros[] = $erro ?: 'sem resposta'; continue; }
        $lista = sefaz_interpretar($html);
        if ($lista) { $res['autorizadores'] = $lista; return $res; }
        $erros[] = 'página sem a tabela de disponibilidade (' . strlen($html) . ' bytes)';
    }
    $res['erro'] = implode(' · ', array_unique($erros));
    return $res;
}

function sefaz_baixar(string $url, ?string &$erro = null): ?string {
    $erro = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 6,
            CURLOPT_COOKIEFILE => '', CURLOPT_COOKIEJAR => '',   // aceita o cookie de sessão que o portal exige
            CURLOPT_ENCODING => '',
            CURLOPT_TIMEOUT => SEFAZ_TIMEOUT, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36 RadarMage/1.0',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: pt-BR,pt;q=0.9'],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $html = curl_exec($ch);
        if ($html === false) { $erro = curl_error($ch); curl_close($ch); return null; }
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        if ($code >= 400) { $erro = "portal respondeu HTTP $code"; return null; }
        return (string)$html;
    }
    $ctx = stream_context_create(['http' => ['timeout' => SEFAZ_TIMEOUT, 'user_agent' => 'Mozilla/5.0 (compatible; RadarMage/1.0)']]);
    $html = @file_get_contents($url, false, $ctx);
    if ($html === false) { $erro = 'file_get_contents falhou (sem cURL e sem allow_url_fopen?)'; return null; }
    return $html;
}

/**
 * Interpreta a tabela. Cada linha: nome do autorizador + bolinhas.
 * Sai: [['sigla'=>'SVRS','servicos'=>['Autorização'=>'verde',...],'tempo'=>'...'], ...]
 */
function sefaz_interpretar(string $html): array {
    $out = [];
    if (!preg_match_all('#<tr[^>]*>(.*?)</tr>#si', $html, $linhas)) return [];
    $cols = sefaz_servicos();
    foreach ($linhas[1] as $linha) {
        if (!preg_match_all('#<td[^>]*>(.*?)</td>#si', $linha, $celulas)) continue;
        $cel = $celulas[1];
        if (count($cel) < 5) continue;
        $nome = trim(html_entity_decode(strip_tags($cel[0]), ENT_QUOTES, 'UTF-8'));
        $sigla = strtoupper(preg_replace('/[^A-Za-z\-]/', '', $nome));
        if ($sigla === '' || strlen($sigla) > 8) continue;
        $servicos = []; $tempo = ''; $temBola = false;
        foreach (array_slice($cel, 1) as $i => $c) {
            $rotulo = $cols[$i] ?? "Coluna $i";
            if (preg_match('/bola_(verde|amarela|vermelha)/i', $c, $m)) { $servicos[$rotulo] = strtolower($m[1]); $temBola = true; }
            else {
                $txt = trim(html_entity_decode(strip_tags($c), ENT_QUOTES, 'UTF-8'));
                if ($rotulo === 'Tempo Médio') $tempo = $txt;
                elseif ($txt !== '' && $txt !== '-') $servicos[$rotulo] = 'texto:' . $txt;
                else $servicos[$rotulo] = 'nd';
            }
        }
        if (!$temBola) continue;
        $out[] = ['sigla' => $sigla, 'nome' => $nome, 'servicos' => $servicos, 'tempo' => $tempo];
    }
    return $out;
}

/** Cor geral do autorizador olhando só os serviços que importam para emitir */
function sefaz_cor_geral(array $a): string {
    $chaves = ['Autorização', 'Retorno Autorização', 'Status Serviço'];
    $pior = 'verde';
    foreach ($chaves as $k) {
        $v = $a['servicos'][$k] ?? 'nd';
        if ($v === 'vermelha') return 'vermelha';
        if ($v === 'amarela') $pior = 'amarela';
    }
    return $pior;
}

/** Resumo para o card: ['no_ar'=>N,'total'=>27,'instaveis'=>[...],'fora'=>[...]] */
function sefaz_resumo(?array $sit): array {
    $r = ['no_ar' => 0, 'total' => 27, 'instaveis' => [], 'fora' => [], 'ok' => false];
    $e = sefaz_estados($sit);
    if (!$e) return $r;
    foreach ($e as $uf => $x) {
        if ($x['situacao'] === 'no_ar') $r['no_ar']++;
        elseif ($x['situacao'] === 'instavel') $r['instaveis'][] = $uf;
        else $r['fora'][] = $uf;
    }
    $r['ok'] = true;
    $r['total'] = max($r['total'], count($e));
    return $r;
}
