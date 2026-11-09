<?php
/**
 * api/professores-salvar.php
 * Adiciona, edita ou remove um professor — POST, token de editor.
 *
 * Body:
 *   {
 *     "token": "...",
 *     "acao": "salvar" | "remover",
 *     "id": "ana-souza",              // vazio ao cadastrar um novo
 *     "nome": "Ana Souza",
 *     "telefone": "47 99999-0000",    // opcional, só aparece na área de edição
 *     "foto": "data:image/jpeg;base64,..." | "remover" | omitido (mantém)
 *   }
 *
 * A foto chega já reduzida pelo navegador (400x400) e é gravada em fotos/<id>.jpg.
 * Um professor que ainda tem aula marcada de hoje em diante não pode ser removido.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_log.php';
require_once __DIR__ . '/_auth.php';

$config = require __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') falharAPI(405, 'Método não permitido. Use POST.');

$corpo = json_decode(file_get_contents('php://input'), true);
$payload = exigirEditor($config, $corpo['token'] ?? '');

$acao = $corpo['acao'] ?? '';
$id = (string)($corpo['id'] ?? '');
if (!in_array($acao, ['salvar', 'remover'], true)) falharAPI(400, 'Ação inválida.');
if ($id !== '' && !preg_match('/^[a-z0-9-]{1,60}$/', $id)) falharAPI(400, 'Identificador inválido.');

$arquivo = __DIR__ . '/../professores.json';
$pastaFotos = __DIR__ . '/../fotos';
$professores = lerJSON($arquivo, []);

function apagarFoto($pastaFotos, $id) {
    $caminho = $pastaFotos . '/' . $id . '.jpg';
    if (file_exists($caminho)) unlink($caminho);
}

if ($acao === 'remover') {
    if ($id === '' || !isset($professores[$id])) falharAPI(404, 'Responsável não encontrado.');

    $escalas = lerJSON(__DIR__ . '/../escalas.json', []);
    $hoje = date('Ymd');
    foreach (($escalas['escalaData'] ?? []) as $data => $aula) {
        $chave = implode('', array_reverse(explode('/', $data)));
        if ($chave >= $hoje && (($aula['professor'] ?? '') === $id || ($aula['pastoreio'] ?? '') === $id)) {
            falharAPI(409, $professores[$id]['nome'] . ' ainda está escalado(a) em ' . $data . '. Troque o responsável desse sábado antes de remover.');
        }
    }

    fazerBackup($arquivo, 'professores');
    $nome = $professores[$id]['nome'];
    unset($professores[$id]);
    apagarFoto($pastaFotos, $id);
    if (!gravarJSON($arquivo, $professores)) falharAPI(500, 'Falha ao gravar o cadastro.');
    registrarLog('remover_professor', $payload['email'] ?? 'desconhecido', ['professor' => $nome]);
    responder(200, ['sucesso' => true, 'professores' => (object)$professores]);
}

// ===== salvar =====
$nome = textoCurto($corpo['nome'] ?? '', 80);
if ($nome === '') falharAPI(400, 'Informe o nome do responsável.');
$telefone = preg_replace('/[^0-9+ ()-]/', '', (string)($corpo['telefone'] ?? ''));
$telefone = mb_substr(trim($telefone), 0, 25);

$novo = $id === '';
if ($novo) {
    $base = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome) ?: 'professor');
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', $base), '-') ?: 'professor';
    $base = substr($base, 0, 50);
    $id = $base;
    for ($n = 2; isset($professores[$id]); $n++) $id = $base . '-' . $n;
    $professores[$id] = ['nome' => $nome, 'telefone' => '', 'foto' => ''];
} elseif (!isset($professores[$id])) {
    falharAPI(404, 'Responsável não encontrado.');
}

$fotoAntes = $professores[$id]['foto'] ?? '';
$foto = $corpo['foto'] ?? null;
$jpeg = null;
if (is_string($foto) && $foto !== '' && $foto !== 'remover') {
    if (!preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#', $foto, $m)) {
        falharAPI(400, 'Formato de foto inválido. Envie uma imagem JPG ou PNG.');
    }
    $jpeg = base64_decode($m[1], true);
    if ($jpeg === false || strlen($jpeg) > 1024 * 1024) falharAPI(400, 'A foto ficou grande demais (máximo 1 MB).');
    $info = @getimagesizefromstring($jpeg);
    if (!$info || $info[2] !== IMAGETYPE_JPEG || $info[0] > 1200 || $info[1] > 1200) {
        falharAPI(400, 'A foto não é uma imagem JPG válida.');
    }
    // Regrava a imagem quando o servidor tem a extensão GD (remove dados extras)
    if (function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring($jpeg);
        if ($img) {
            ob_start();
            imagejpeg($img, null, 85);
            $jpeg = ob_get_clean();
            imagedestroy($img);
        }
    }
}

fazerBackup($arquivo, 'professores');

if ($jpeg !== null) {
    if (!is_dir($pastaFotos)) mkdir($pastaFotos, 0755, true);
    if (file_put_contents($pastaFotos . '/' . $id . '.jpg', $jpeg, LOCK_EX) === false) {
        falharAPI(500, 'Falha ao gravar a foto.');
    }
    $professores[$id]['foto'] = 'fotos/' . $id . '.jpg?v=' . time();
} elseif ($foto === 'remover') {
    apagarFoto($pastaFotos, $id);
    $professores[$id]['foto'] = '';
}

$professores[$id]['nome'] = $nome;
$professores[$id]['telefone'] = $telefone;

uasort($professores, function ($a, $b) { return strcoll($a['nome'], $b['nome']); });
if (!gravarJSON($arquivo, $professores)) falharAPI(500, 'Falha ao gravar o cadastro.');

registrarLog($novo ? 'criar_professor' : 'editar_professor', $payload['email'] ?? 'desconhecido', [
    'professor' => $nome,
    'foto'      => $jpeg !== null ? 'nova' : ($foto === 'remover' ? 'removida' : ($fotoAntes ? 'mantida' : 'sem foto')),
]);

responder(200, ['sucesso' => true, 'id' => $id, 'professores' => (object)$professores]);
