<?php
/**
 * api/backups-listar.php
 * Lista os backups de escalas.json disponíveis — POST, protegido por token
 * de sessão com acesso admin ("*"). Usado pelo Painel Administrativo.
 *
 * Body esperado: { "token": "..." }
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
$arquivos = [];
if (is_dir($pastaBackup)) {
    foreach (['escalas', 'diario', 'manual'] as $prefixo) {
        $arquivos = array_merge($arquivos, glob($pastaBackup . '/' . $prefixo . '_*.json') ?: []);
    }
}

// Responsável pelos backups "antes de cada alteração": o backup é gravado
// logo antes da alteração, e a alteração é registrada no Histórico logo
// depois. Então o registro do Histórico até 10 segundos após o backup é o
// de quem fez a alteração.
$ACOES_COM_BACKUP = ['salvar_departamento', 'salvar_completo', 'restaurar_backup'];
$registrosLog = array_values(array_filter(lerLog(LOG_MAX_LINHAS), function($r) use ($ACOES_COM_BACKUP) {
    return isset($r['acao'], $r['data']) && in_array($r['acao'], $ACOES_COM_BACKUP, true);
}));

function responsavelDoBackup($momentoBackup, $registros) {
    $melhor = null;
    foreach ($registros as $r) {
        $diferenca = strtotime($r['data']) - $momentoBackup;
        if ($diferenca >= 0 && $diferenca <= 10 && ($melhor === null || $diferenca < $melhor['dif'])) {
            $melhor = ['dif' => $diferenca, 'registro' => $r];
        }
    }
    if ($melhor === null) return null;
    $r = $melhor['registro'];
    return [
        'ator'         => $r['ator'] ?? 'desconhecido',
        'acao'         => $r['acao'],
        'departamento' => $r['detalhes']['departamento'] ?? null,
    ];
}

// Tipo pelo prefixo do nome: "alteracao" (antes de cada alteração),
// "diario" (automático) ou "manual" (botão do painel)
$lista = array_map(function($caminho) use ($registrosLog) {
    $nome = basename($caminho);
    $tipo = strpos($nome, 'diario_') === 0 ? 'diario' : (strpos($nome, 'manual_') === 0 ? 'manual' : 'alteracao');
    return [
        'arquivo'      => $nome,
        'tipo'         => $tipo,
        'responsavel'  => $tipo === 'alteracao' ? responsavelDoBackup(filemtime($caminho), $registrosLog) : null,
        'tamanho'      => filesize($caminho),
        'criado_em'    => gmdate('c', filemtime($caminho)),
    ];
}, $arquivos);

// Mais recente primeiro
usort($lista, function($a, $b) {
    return strcmp($b['criado_em'], $a['criado_em']);
});

echo json_encode([
    'sucesso' => true,
    'backups' => $lista,
], JSON_UNESCAPED_UNICODE);
