<?php
/**
 * api/licoes.php
 * Lições oficiais da Escola Sabatina (adultos, português) — GET, público.
 *   ?trimestre=2026-04
 *
 * Fonte: sabbath-school.adventech.io (o mesmo conteúdo de
 * https://sabbath-school.adventech.io/pt/2026-04).
 * A resposta fica guardada em cache/ por 7 dias, para não depender do site
 * a cada visita. Se o site estiver fora do ar, usa a cópia guardada.
 *
 * Devolve cada lição já ligada ao sábado em que é estudada na classe
 * (o sábado logo depois da semana de estudo da lição):
 *   { "trimestre": "2026-04", "titulo": "...", "capa": "...", "link": "...",
 *     "licoes": { "03/10/2026": { "numero": "1", "tema": "...", "capa": "..." } } }
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
require_once __DIR__ . '/_auth.php';

$trimestre = $_GET['trimestre'] ?? '';
if (!preg_match('/^(20\d{2})-0([1-4])$/', $trimestre)) falharAPI(400, 'Trimestre inválido. Use o formato 2026-04.');

// 1) Trimestre gravado no pacote (licoes/2026-04.json): tem prioridade e não depende de internet
$fixo = __DIR__ . '/../licoes/' . $trimestre . '.json';
if (file_exists($fixo)) {
    echo file_get_contents($fixo);
    exit;
}

// 2) Senão, busca no site oficial e guarda em cache
$pasta = __DIR__ . '/../cache';
$cache = $pasta . '/licoes_' . $trimestre . '.json';
$validade = 7 * 24 * 60 * 60;

if (file_exists($cache) && time() - filemtime($cache) < $validade) {
    echo file_get_contents($cache);
    exit;
}

function baixar($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $r = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $codigo === 200 ? $r : false;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 15]]);
    return @file_get_contents($url, false, $ctx);
}

$bruto = baixar('https://sabbath-school.adventech.io/api/v2/pt/quarterlies/' . $trimestre . '/index.json');
$fonte = $bruto ? json_decode($bruto, true) : null;

if (!is_array($fonte) || empty($fonte['lessons'])) {
    if (file_exists($cache)) { echo file_get_contents($cache); exit; }   // cópia antiga é melhor que nada
    falharAPI(502, 'Não foi possível buscar as lições deste trimestre agora.');
}

$licoes = [];
foreach ($fonte['lessons'] as $l) {
    $fim = DateTime::createFromFormat('d/m/Y', $l['end_date'] ?? '');
    if (!$fim) continue;
    $fim->modify('+1 day');   // sábado em que a lição é estudada na classe
    $licoes[$fim->format('d/m/Y')] = [
        'numero' => ltrim((string)($l['id'] ?? ''), '0'),
        'tema'   => (string)($l['title'] ?? ''),
        'capa'   => (string)($l['cover'] ?? ''),
    ];
}

$saida = json_encode([
    'trimestre' => $trimestre,
    'titulo'    => (string)($fonte['quarterly']['title'] ?? ''),
    'capa'      => (string)($fonte['quarterly']['cover'] ?? ''),
    'link'      => 'https://sabbath-school.adventech.io/pt/' . $trimestre,
    'licoes'    => (object)$licoes,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

if (!is_dir($pasta)) mkdir($pasta, 0755, true);
file_put_contents($cache, $saida, LOCK_EX);
echo $saida;
