<?php
/**
 * api/config.php
 * Configurações compartilhadas do login de teste e das permissões por e-mail.
 * Incluído por outros arquivos da API — não é um endpoint, não deve ser
 * acessado diretamente pelo navegador.
 *
 * MODO TESTE: o login aqui não verifica senha nem identidade real — qualquer
 * pessoa que souber um e-mail cadastrado em usuarios.json consegue "entrar"
 * como aquele usuário. Isso é temporário, até trocar pelo login real do
 * Google (Fase 2b).
 *
 * FASE 2b: assim que 'google_client_id' (abaixo) for preenchido, as páginas
 * passam a mostrar o botão "Fazer login com o Google" (api/login-google.php)
 * e o login-teste.php para de funcionar.
 *
 * As permissões (quem pode editar o quê) ficam em usuarios.json, ao lado
 * deste arquivo, e são editáveis pelo Painel Administrativo — não fixas
 * aqui no código.
 */

$permissoesArquivo = __DIR__ . '/../usuarios.json';
$permissoes = [];
if (file_exists($permissoesArquivo)) {
    $conteudo = file_get_contents($permissoesArquivo);
    $decodificado = json_decode($conteudo, true);
    if (is_array($decodificado)) {
        $permissoes = $decodificado;
    }
}

return [
    // Chave usada para assinar os tokens de sessão (não compartilhe)
    'sessao_secreta' => 'TROQUE_POR_UMA_CHAVE_ALEATORIA',

    // Quanto tempo a sessão dura, em segundos (8 horas)
    'sessao_duracao' => 8 * 60 * 60,

    // ID do cliente OAuth do Google (Google Cloud > Credenciais), formato
    // "123...apps.googleusercontent.com". Vazio = login com Google desligado
    // e vale o login de teste. Preenchido = só login com Google.
    'google_client_id' => '',

    // Quem pode editar o quê — carregado de usuarios.json.
    // Chave: e-mail em minúsculas. Valor: lista de departamentos, ou ["*"]
    // para acesso a todos os departamentos (admin).
    'permissoes' => $permissoes,
];
