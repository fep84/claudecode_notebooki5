<?php
/**
 * api/salvar-aulas.php
 * ESCRITA restrita — POST, token de editor da classe ou admin.
 *
 * Body:
 *   {
 *     "token": "...",
 *     "atualizacoes": {
 *       "14/11/2026": { "professor": "ana-souza", "pastoreio": "joao-pereira", "licao": "7", "tema": "...",
 *                       "links": [{"titulo": "Vídeo", "url": "https://..."}] },
 *       "21/11/2026": null          // null apaga a aula daquele sábado
 *     },
 *     "linksFixos": [{"titulo": "Lição online", "url": "https://..."}]   // opcional
 *   }
 *
 * Só as datas enviadas são alteradas. Faz backup antes de gravar.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_log.php';
require_once __DIR__ . '/_auth.php';

$config = require __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') falharAPI(405, 'Método não permitido. Use POST.');

$corpo = json_decode(file_get_contents('php://input'), true);
$payload = exigirEditor($config, $corpo['token'] ?? '');

$atualizacoes = $corpo['atualizacoes'] ?? [];
$temLinksFixos = array_key_exists('linksFixos', $corpo ?? []);
if (!is_array($atualizacoes) || (count($atualizacoes) === 0 && !$temLinksFixos)) {
    falharAPI(400, 'Nada para salvar.');
}

$professores = lerJSON(__DIR__ . '/../professores.json', []);
$arquivo = __DIR__ . '/../escalas.json';
$atual = lerJSON($arquivo, null);
if (!is_array($atual)) falharAPI(500, 'Não foi possível ler os dados atuais.');
if (!isset($atual['escalaData']) || !is_array($atual['escalaData'])) $atual['escalaData'] = [];

// Valida tudo antes de gravar qualquer coisa
$novas = [];
foreach ($atualizacoes as $data => $aula) {
    if (!preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $data, $m) || !checkdate((int)$m[2], (int)$m[1], (int)$m[3])) {
        falharAPI(400, 'Data inválida: ' . $data);
    }
    if ($aula === null) { $novas[$data] = null; continue; }
    if (!is_array($aula)) falharAPI(400, 'Formato inválido na data ' . $data);

    $prof = (string)($aula['professor'] ?? '');
    if ($prof !== '' && !isset($professores[$prof])) {
        falharAPI(400, 'Responsável pela lição não encontrado no cadastro (data ' . $data . ').');
    }
    $past = (string)($aula['pastoreio'] ?? '');
    if ($past !== '' && !isset($professores[$past])) {
        falharAPI(400, 'Responsável pelo pastoreio não encontrado no cadastro (data ' . $data . ').');
    }
    $limpa = [
        'professor' => $prof,
        'pastoreio' => $past,
        'licao'     => textoCurto($aula['licao'] ?? '', 10),
        'tema'      => textoCurto($aula['tema'] ?? '', 150),
        'links'     => limparLinks($aula['links'] ?? [], 5),
    ];
    $vazia = $limpa['professor'] === '' && $limpa['pastoreio'] === '' && $limpa['licao'] === '' && $limpa['tema'] === '' && !$limpa['links'];
    $novas[$data] = $vazia ? null : $limpa;
}
$linksFixos = $temLinksFixos ? limparLinks($corpo['linksFixos'], 8) : null;

fazerBackup($arquivo, 'escalas');

foreach ($novas as $data => $aula) {
    if ($aula === null) unset($atual['escalaData'][$data]);
    else $atual['escalaData'][$data] = $aula;
}
if ($linksFixos !== null) $atual['linksFixos'] = $linksFixos;

// Mantém as datas em ordem cronológica no arquivo
uksort($atual['escalaData'], function ($a, $b) {
    return strcmp(implode('', array_reverse(explode('/', $a))), implode('', array_reverse(explode('/', $b))));
});
$atual['updated_at'] = gmdate('c');

if (!gravarJSON($arquivo, $atual)) falharAPI(500, 'Falha ao gravar o arquivo de escalas.');

registrarLog('salvar_aulas', $payload['email'] ?? 'desconhecido', [
    'datas'       => array_keys($novas),
    'links_fixos' => $linksFixos !== null,
]);

responder(200, ['sucesso' => true, 'mensagem' => 'Escala atualizada.', 'updated_at' => $atual['updated_at']]);
