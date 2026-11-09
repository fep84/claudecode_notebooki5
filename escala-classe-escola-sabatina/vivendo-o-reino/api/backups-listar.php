<?php
/**
 * api/backups-listar.php
 * Lista os backups de escalas.json disponíveis — POST, protegido por token
 * de sessão com acesso admin ("*"). Usado pelo Painel Administrativo.
 *
 * Body esperado: { "token": "..." }
 */

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';
$SESSAO_SECRETA = $config['sessao_secreta'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido. Use POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$corpo = json_decode(file_get_contents('php://input'), true);
$token = $corpo['token'] ?? '';

// ===== Verifica o token =====
$partes = explode('.', $token);
if (count($partes) !== 2) {
    http_response_code(401);
    echo json_encode(['erro' => 'Token inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

[$payloadB64, $assinaturaRecebida] = $partes;
$assinaturaEsperada = hash_hmac('sha256', $payloadB64, $SESSAO_SECRETA);

if (!hash_equals($assinaturaEsperada, $assinaturaRecebida)) {
    http_response_code(401);
    echo json_encode(['erro' => 'Token inválido ou adulterado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode(base64_decode($payloadB64), true);
if (!$payload || !isset($payload['exp']) || time() > $payload['exp']) {
    http_response_code(401);
    echo json_encode(['erro' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== Só admin ("*") pode ver/restaurar backups =====
$deps = $payload['deps'] ?? [];
if (!in_array('*', $deps, true)) {
    http_response_code(403);
    echo json_encode(['erro' => 'Só administradores podem ver os backups.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pastaBackup = __DIR__ . '/../backups';
$arquivos = is_dir($pastaBackup) ? glob($pastaBackup . '/escalas_*.json') : [];

$lista = array_map(function($caminho) {
    return [
        'arquivo'      => basename($caminho),
        'tamanho'      => filesize($caminho),
        'criado_em'    => gmdate('c', filemtime($caminho)),
    ];
}, $arquivos ?: []);

// Mais recente primeiro
usort($lista, function($a, $b) {
    return strcmp($b['criado_em'], $a['criado_em']);
});

echo json_encode([
    'sucesso' => true,
    'backups' => $lista,
], JSON_UNESCAPED_UNICODE);
