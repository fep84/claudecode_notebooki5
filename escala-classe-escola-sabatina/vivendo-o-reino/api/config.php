<?php
/**
 * api/config.php
 * Configurações da escala de professores da Classe Vivendo o Reino.
 * Incluído por outros arquivos da API — não é um endpoint.
 *
 * Permissões (usuarios.json, editável pelo Painel Administrativo):
 *   ["*"]               = administrador (usuários, backups, histórico e tudo mais)
 *   ["Vivendo o Reino"] = editor da classe (aulas, professores e links)
 *
 * LOGIN: com 'google_client_id' vazio vale o login de teste (só o e-mail,
 * sem senha). Preenchido, só o login com Google funciona.
 */

$permissoesArquivo = __DIR__ . '/../usuarios.json';
$permissoes = [];
if (file_exists($permissoesArquivo)) {
    $decodificado = json_decode(file_get_contents($permissoesArquivo), true);
    if (is_array($decodificado)) {
        $permissoes = $decodificado;
    }
}

return [
    // Nome da classe. É também o nome da permissão de editor.
    'classe' => 'Vivendo o Reino',

    // Chave usada para assinar os tokens de sessão (não compartilhe).
    // É diferente da chave das escalas DC e MJ de propósito.
    'sessao_secreta' => 'TROQUE_POR_UMA_CHAVE_ALEATORIA',

    // Quanto tempo a sessão dura, em segundos (8 horas)
    'sessao_duracao' => 8 * 60 * 60,

    // ID do cliente OAuth do Google (o mesmo das escalas DC e MJ).
    // Vazio = login de teste. Preenchido = só login com Google.
    'google_client_id' => '',

    'permissoes' => $permissoes,
];
