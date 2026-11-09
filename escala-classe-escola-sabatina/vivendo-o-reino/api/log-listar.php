<?php
/**
 * api/log-listar.php
 * Lista os últimos registros do histórico de alterações — POST, protegido por
 * token de sessão com acesso admin ("*"). Usado pelo Painel Administrativo.
 *
 * Body esperado: { "token": "...", "quantidade": 100 }
 */

header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/_log.php';
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

// ===== Só admin ("*") pode ver o histórico =====
$deps = $payload['deps'] ?? [];
if (!in_array('*', $deps, true)) {
    http_response_code(403);
    echo json_encode(['erro' => 'Só administradores podem ver o histórico.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$quantidade = (int)($corpo['quantidade'] ?? 100);
if ($quantidade < 1) $quantidade = 100;
if ($quantidade > LOG_MAX_LINHAS) $quantidade = LOG_MAX_LINHAS;

echo json_encode([
    'sucesso'   => true,
    'registros' => array_values(lerLog($quantidade)),
], JSON_UNESCAPED_UNICODE);
