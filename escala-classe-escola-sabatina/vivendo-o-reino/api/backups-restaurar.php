<?php
/**
 * api/backups-restaurar.php
 * Restaura um backup específico como o escalas.json atual — POST, protegido
 * por token de sessão com acesso admin ("*"). Usado pelo Painel Administrativo.
 *
 * Body esperado: { "token": "...", "arquivo": "escalas_2026-09-29_143000.json" }
 *
 * Segurança: o nome do arquivo é validado contra um padrão fixo (regex) antes
 * de qualquer operação de arquivo, pra impedir que alguém tente acessar outro
 * arquivo do servidor manipulando esse campo (path traversal).
 *
 * O estado atual do escalas.json também vira um backup antes da restauração,
 * então restaurar nunca é uma via de mão única — dá pra "desfazer o desfazer".
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_log.php';

$config = require __DIR__ . '/config.php';
$SESSAO_SECRETA = $config['sessao_secreta'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido. Use POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$corpo = json_decode(file_get_contents('php://input'), true);
$token = $corpo['token'] ?? '';
$nomeArquivo = $corpo['arquivo'] ?? '';

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

// ===== Só admin ("*") pode restaurar backups =====
$deps = $payload['deps'] ?? [];
if (!in_array('*', $deps, true)) {
    http_response_code(403);
    echo json_encode(['erro' => 'Só administradores podem restaurar backups.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== Valida o nome do arquivo (impede path traversal) =====
if (!preg_match('/^escalas_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{6}\.json$/', $nomeArquivo)) {
    http_response_code(400);
    echo json_encode(['erro' => 'Nome de arquivo de backup inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pastaBackup = __DIR__ . '/../backups';
$caminhoBackup = $pastaBackup . '/' . $nomeArquivo;

if (!file_exists($caminhoBackup)) {
    http_response_code(404);
    echo json_encode(['erro' => 'Backup não encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$conteudoBackup = file_get_contents($caminhoBackup);
$dadosBackup = json_decode($conteudoBackup, true);
if (!$dadosBackup || !isset($dadosBackup['escalaData'])) {
    http_response_code(500);
    echo json_encode(['erro' => 'O arquivo de backup está corrompido ou em formato inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$arquivoAtual = __DIR__ . '/../escalas.json';

// ===== Faz backup do estado atual antes de restaurar (permite desfazer) =====
if (file_exists($arquivoAtual)) {
    copy($arquivoAtual, $pastaBackup . '/escalas_' . date('Y-m-d_His') . '.json');
}

// ===== Restaura, atualizando o timestamp =====
$dadosBackup['updated_at'] = gmdate('c');

$ok = file_put_contents(
    $arquivoAtual,
    json_encode($dadosBackup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    LOCK_EX
);

if ($ok === false) {
    http_response_code(500);
    echo json_encode(['erro' => 'Falha ao restaurar o backup.'], JSON_UNESCAPED_UNICODE);
    exit;
}

registrarLog('restaurar_backup', $payload['email'] ?? 'desconhecido', [
    'arquivo_restaurado' => $nomeArquivo,
]);

echo json_encode([
    'sucesso'    => true,
    'mensagem'   => 'Backup restaurado com sucesso.',
    'updated_at' => $dadosBackup['updated_at'],
], JSON_UNESCAPED_UNICODE);
