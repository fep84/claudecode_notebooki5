<?php
/**
 * api/backups-criar.php
 * Cria um backup manual do escalas.json atual — POST, protegido por token
 * de sessão com acesso admin ("*"). Usado pelo botão "Fazer backup agora"
 * do Painel Administrativo, por exemplo antes de substituir arquivos no
 * servidor.
 *
 * Body esperado: { "token": "..." }
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_log.php';
require_once __DIR__ . '/_backup.php';

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

// ===== Só admin ("*") pode criar backups =====
$deps = $payload['deps'] ?? [];
if (!in_array('*', $deps, true)) {
    http_response_code(403);
    echo json_encode(['erro' => 'Só administradores podem criar backups.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$nome = backupManual();
if ($nome === false) {
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível criar o backup.'], JSON_UNESCAPED_UNICODE);
    exit;
}

registrarLog('backup_manual', $payload['email'] ?? 'desconhecido', [
    'arquivo_backup' => $nome,
]);

echo json_encode([
    'sucesso' => true,
    'arquivo' => $nome,
], JSON_UNESCAPED_UNICODE);
