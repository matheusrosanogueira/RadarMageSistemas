<?php
/**
 * Radar Mage — avisos aos clientes (versão nova e Sefaz do estado)
 *
 * Fluxo: algo acontece -> enfileira em `avisos` (um por cliente e canal) -> o cron envia.
 * Canais: e-mail (mail() do PHP) e WhatsApp (webhook configurável, para encaixar na API que a Mage usa).
 */
declare(strict_types=1);

function avisos_ativos(): bool { return cfg('aviso_ativo', '0') === '1'; }

/** Enfileira para um cliente, nos canais que ele tem */
function aviso_enfileirar(array $cliente, string $tipo, string $referencia, string $assunto, string $mensagem): int {
    $pdo = db(); $n = 0;
    $ja = $pdo->prepare("SELECT COUNT(*) FROM avisos WHERE cliente_id=? AND tipo=? AND referencia=? AND canal=?");
    $ins = $pdo->prepare("INSERT INTO avisos(tipo, referencia, cliente_id, canal, destino, assunto, mensagem) VALUES (?,?,?,?,?,?,?)");
    foreach ([['email', trim((string)$cliente['email'])], ['whatsapp', preg_replace('/\D+/', '', (string)$cliente['telefone'])]] as [$canal, $destino]) {
        if ($destino === '' || ($canal === 'email' && !filter_var($destino, FILTER_VALIDATE_EMAIL))) continue;
        if ($canal === 'whatsapp' && cfg('whats_url') === '') continue;
        if ($canal === 'email' && cfg('aviso_email_de') === '') continue;
        $ja->execute([$cliente['id'], $tipo, $referencia, $canal]);
        if ((int)$ja->fetchColumn()) continue;
        $ins->execute([$tipo, $referencia, $cliente['id'], $canal, $destino, $assunto, $mensagem]); $n++;
    }
    return $n;
}

/** Versões que atingiram o gatilho (publicada, ou liberada pela Mage) e ainda não foram avisadas */
function avisos_gerar_versoes(): int {
    if (!avisos_ativos()) return 0;
    $pdo = db(); $gatilho = cfg('aviso_versao_gatilho', 'liberada'); $n = 0;
    $cli = $pdo->query("SELECT * FROM clientes WHERE avisar_versao = 1")->fetchAll();
    if (!$cli) return 0;
    foreach (versoes_todas() as $v) {
        if (!$v['publicada_em'] || !empty($v['avisada'])) continue;
        if ($gatilho === 'liberada' && !in_array($v['homologacao'] ?? '', ['liberada', 'ressalva'], true)) continue;
        $link = url_base() . '/v.php?n=' . rawurlencode($v['numero']);
        foreach ($cli as $c) {
            if ($c['versao'] !== '' && version_compare($c['versao'], $v['numero'], '>=')) continue; // já tem
            $nome = cfg('sistema_nome');
            $assunto = "$nome {$v['numero']}" . ($v['codinome'] ? " — {$v['codinome']}" : '') . ' disponível';
            $msg = "Olá, {$c['nome']}! Saiu a versão {$v['numero']} do $nome" . ($v['codinome'] ? " ({$v['codinome']})" : '') . ".\n\n"
                 . ($v['nota_mage'] ? cfg('empresa') . ' diz: ' . strip_tags(md_inline($v['nota_mage'])) . "\n\n" : '')
                 . ($v['resumo'] ? "Em uma linha: {$v['resumo']}\n\n" : '')
                 . ($v['homologacao'] === 'ressalva' ? "Atenção: liberada com ressalva — leia a nota antes de atualizar.\n\n" : '')
                 . "Veja tudo o que mudou: $link"
                 . ($c['chave'] ? "\nO que falta para a sua loja: " . url_base() . "/c.php?k={$c['chave']}&ir=desde" : '');
            $n += aviso_enfileirar($c, 'versao', $v['numero'], $assunto, $msg);
        }
        $pdo->prepare("UPDATE versoes SET avisada = 1 WHERE id = ?")->execute([$v['id']]);
    }
    return $n;
}

/** Quedas vermelhas abertas (ou fechadas) nos estados dos clientes que pediram aviso */
function avisos_gerar_sefaz(): int {
    if (!avisos_ativos()) return 0;
    $pdo = db(); $n = 0;
    $cli = $pdo->query("SELECT * FROM clientes WHERE avisar_sefaz = 1 AND uf <> ''")->fetchAll();
    if (!$cli) return 0;
    // quedas vermelhas iniciadas nas últimas 24h (abertas ou fechadas), uma por autorizador
    $quedas = $pdo->query("SELECT * FROM sefaz_quedas WHERE pior = 'vermelha' AND inicio >= datetime('now','localtime','-1 day') ORDER BY inicio")->fetchAll();
    foreach ($quedas as $q) {
        foreach ($cli as $c) {
            if ($c['uf'] !== $q['uf']) continue;
            $refAbre = 'queda-' . $q['id'] . '-abre'; $refFecha = 'queda-' . $q['id'] . '-fecha';
            $msgAbre = "Atenção, {$c['nome']}: a Sefaz do {$q['uf']} (autorizador {$q['autorizador']}) está fora do ar desde " . data_hora_br($q['inicio']) . ". A NFC-e sai em contingência e a NF-e espera a volta. Acompanhe: " . url_base() . '/fiscal.php#sefaz';
            $n += aviso_enfileirar($c, 'sefaz', $refAbre, "Sefaz {$q['uf']} fora do ar", $msgAbre);
            if ($q['fim']) {
                $msgFecha = "{$c['nome']}: a Sefaz do {$q['uf']} voltou ao normal às " . data_hora_br($q['fim']) . " (ficou fora " . duracao($q['inicio'], $q['fim']) . "). As notas em contingência podem ser transmitidas.";
                $n += aviso_enfileirar($c, 'sefaz', $refFecha, "Sefaz {$q['uf']} voltou", $msgFecha);
            }
        }
    }
    return $n;
}

/** Envia os pendentes (chamado pelo cron). Retorna [enviados, falhas] */
function avisos_enviar(int $max = 30): array {
    $pdo = db(); $ok = 0; $erro = 0;
    $lista = $pdo->prepare("SELECT * FROM avisos WHERE status = 'pendente' ORDER BY id LIMIT ?"); $lista->execute([$max]);
    $upd = $pdo->prepare("UPDATE avisos SET status=?, erro=?, enviado_em=? WHERE id=?");
    foreach ($lista as $a) {
        $e = null;
        $res = $a['canal'] === 'email' ? aviso_email($a['destino'], $a['assunto'], $a['mensagem'], $e) : aviso_whatsapp($a['destino'], $a['mensagem'], $e);
        if ($res) { $upd->execute(['enviado', '', date('Y-m-d H:i:s'), $a['id']]); $ok++; }
        else { $upd->execute(['erro', (string)$e, null, $a['id']]); $erro++; }
    }
    return [$ok, $erro];
}

function aviso_email(string $para, string $assunto, string $mensagem, ?string &$erro = null): bool {
    $de = cfg('aviso_email_de'); if ($de === '') { $erro = 'remetente não configurado'; return false; }
    $nome = cfg('site_nome');
    $cab = "From: =?UTF-8?B?" . base64_encode($nome) . "?= <$de>\r\nReply-To: $de\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
    $ok = @mail($para, '=?UTF-8?B?' . base64_encode($assunto) . '?=', $mensagem, $cab);
    if (!$ok) $erro = 'mail() recusou (confira o e-mail remetente e o SPF do domínio)';
    return $ok;
}

/**
 * WhatsApp por webhook configurável:
 *   whats_url     ex.: https://api.exemplo.com/message/sendText/INSTANCIA
 *   whats_metodo  GET | POST
 *   whats_corpo   JSON (POST) com {telefone} e {mensagem}, ex.: {"number":"55{telefone}","text":"{mensagem}"}
 *   whats_cabecalhos  um por linha, ex.: apikey: XXXX
 */
function aviso_whatsapp(string $telefone, string $mensagem, ?string &$erro = null): bool {
    $url = cfg('whats_url'); if ($url === '') { $erro = 'webhook não configurado'; return false; }
    $tel = preg_replace('/\D+/', '', $telefone);
    $subst = ['{telefone}' => $tel, '{mensagem}' => $mensagem, '{telefone_url}' => rawurlencode($tel), '{mensagem_url}' => rawurlencode($mensagem), '{mensagem_json}' => trim(json_encode($mensagem, JSON_UNESCAPED_UNICODE), '"')];
    $metodo = strtoupper(cfg('whats_metodo', 'POST'));
    $ch = curl_init(strtr($url, $subst));
    $cab = ['User-Agent: RadarMage/1.0'];
    foreach (preg_split('/\r?\n/', cfg('whats_cabecalhos')) as $l) if (str_contains($l, ':')) $cab[] = trim($l);
    $op = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => true];
    if ($metodo === 'POST') {
        $corpo = strtr(cfg('whats_corpo', '{"number":"55{telefone}","text":"{mensagem_json}"}'), $subst);
        $op[CURLOPT_POST] = true; $op[CURLOPT_POSTFIELDS] = $corpo;
        if (!preg_grep('/^content-type:/i', $cab)) $cab[] = 'Content-Type: application/json';
    }
    $op[CURLOPT_HTTPHEADER] = $cab;
    curl_setopt_array($ch, $op);
    $resp = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $ce = curl_error($ch); curl_close($ch);
    if ($resp === false) { $erro = $ce; return false; }
    if ($code >= 300) { $erro = "HTTP $code: " . mb_strimwidth((string)$resp, 0, 200, '…'); return false; }
    return true;
}
