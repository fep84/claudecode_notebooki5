<?php
/**
 * api/_auth.php
 * Funções comuns dos endpoints novos da escala de professores.
 * Não é um endpoint (o "_" no nome indica isso).
 */

function responder($codigo, $dados) {
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function falharAPI($codigo, $mensagem) {
    responder($codigo, ['erro' => $mensagem]);
}

/**
 * Confere o token de sessão e se ele dá acesso à classe (ou é admin).
 * Devolve o payload do token (email, deps, exp).
 */
function exigirEditor($config, $token, $somenteAdmin = false) {
    $partes = explode('.', (string)$token);
    if (count($partes) !== 2) falharAPI(401, 'Token inválido.');

    [$payloadB64, $assinatura] = $partes;
    $esperada = hash_hmac('sha256', $payloadB64, $config['sessao_secreta']);
    if (!hash_equals($esperada, $assinatura)) falharAPI(401, 'Token inválido ou adulterado.');

    $payload = json_decode(base64_decode($payloadB64), true);
    if (!$payload || !isset($payload['exp']) || time() > $payload['exp']) {
        falharAPI(401, 'Sessão expirada. Faça login novamente.');
    }

    $deps = $payload['deps'] ?? [];
    $admin = in_array('*', $deps, true);
    if ($somenteAdmin && !$admin) falharAPI(403, 'Só administradores podem fazer isso.');
    if (!$admin && !in_array($config['classe'], $deps, true)) {
        falharAPI(403, 'Você não tem permissão para editar esta classe.');
    }
    return $payload;
}

function lerJSON($arquivo, $padrao) {
    if (!file_exists($arquivo)) return $padrao;
    $dados = json_decode(file_get_contents($arquivo), true);
    return is_array($dados) ? $dados : $padrao;
}

function gravarJSON($arquivo, $dados) {
    $json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    return $json !== false && file_put_contents($arquivo, $json, LOCK_EX) !== false;
}

/** Copia o arquivo para backups/<prefixo>_AAAA-MM-DD_HHMMSS.json, mantendo os últimos 30. */
function fazerBackup($arquivo, $prefixo) {
    if (!file_exists($arquivo)) return;
    $pasta = __DIR__ . '/../backups';
    if (!is_dir($pasta)) mkdir($pasta, 0755, true);
    copy($arquivo, $pasta . '/' . $prefixo . '_' . date('Y-m-d_His') . '.json');

    $todos = glob($pasta . '/' . $prefixo . '_*.json');
    if ($todos !== false && count($todos) > 30) {
        sort($todos);
        foreach (array_slice($todos, 0, count($todos) - 30) as $antigo) unlink($antigo);
    }
}

/** Texto limpo, sem quebras de linha, com tamanho máximo. */
function textoCurto($valor, $max) {
    $valor = trim(preg_replace('/\s+/u', ' ', (string)$valor));
    return mb_substr($valor, 0, $max);
}

/** Lista de links [{titulo, url}] validada (só http/https, no máximo $max). */
function limparLinks($links, $max) {
    $saida = [];
    if (!is_array($links)) return $saida;
    foreach ($links as $link) {
        if (!is_array($link)) continue;
        $url = trim((string)($link['url'] ?? ''));
        if ($url === '') continue;
        if (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            falharAPI(400, 'Link inválido: ' . mb_substr($url, 0, 80) . '. Use um endereço começando com https://');
        }
        $titulo = textoCurto($link['titulo'] ?? '', 60);
        $saida[] = ['titulo' => $titulo !== '' ? $titulo : 'Link', 'url' => mb_substr($url, 0, 500)];
        if (count($saida) >= $max) break;
    }
    return $saida;
}
