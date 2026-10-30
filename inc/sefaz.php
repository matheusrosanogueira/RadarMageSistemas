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

    $novo = sefaz_consultar();
    if ($novo['autorizadores']) {
        cfg_set('sefaz_json', json_encode($novo, JSON_UNESCAPED_UNICODE));
        @unlink($lock);
        return $novo;
    }
    // falhou: mantém o cache antigo, mas registra o erro
    if ($c) { $c['erro'] = $novo['erro']; $c['tentativa_em'] = $novo['em']; cfg_set('sefaz_json', json_encode($c, JSON_UNESCAPED_UNICODE)); @unlink($lock); return $c; }
    cfg_set('sefaz_json', json_encode($novo, JSON_UNESCAPED_UNICODE));
    @unlink($lock);
    return $novo;
}

/** Baixa e interpreta a página do portal */
function sefaz_consultar(): array {
    $res = ['em' => date('Y-m-d H:i:s'), 'autorizadores' => [], 'erro' => null];
    $html = sefaz_baixar(SEFAZ_URL, $erro);
    if ($html === null) { $res['erro'] = $erro ?: 'sem resposta do portal'; return $res; }
    $lista = sefaz_interpretar($html);
    if (!$lista) { $res['erro'] = 'a página do portal mudou de formato ou veio vazia'; return $res; }
    $res['autorizadores'] = $lista;
    return $res;
}

function sefaz_baixar(string $url, ?string &$erro = null): ?string {
    $erro = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => SEFAZ_TIMEOUT, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; RadarMage/1.0; +' . cfg('site_url') . ')',
            CURLOPT_HTTPHEADER => ['Accept: text/html', 'Accept-Language: pt-BR'],
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
    if (!$sit || !$sit['autorizadores']) return $r;
    $map = sefaz_ufs(); $vistos = [];
    foreach ($sit['autorizadores'] as $a) {
        $ufs = $map[$a['sigla']] ?? null;
        if (!$ufs) continue; // SVC-AN, SVC-RS e outros não contam como estado
        $cor = sefaz_cor_geral($a);
        foreach ($ufs as $uf) {
            if (isset($vistos[$uf])) continue; $vistos[$uf] = true;
            if ($cor === 'verde') $r['no_ar']++;
            elseif ($cor === 'amarela') $r['instaveis'][] = $uf;
            else $r['fora'][] = $uf;
        }
    }
    $r['ok'] = count($vistos) > 0;
    $r['total'] = max($r['total'], count($vistos));
    return $r;
}
