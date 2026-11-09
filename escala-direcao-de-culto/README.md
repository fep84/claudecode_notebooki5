# Escala de Direção de Culto

Publicada em `iasdgloria.com.br/escalas/direcao-de-culto/`. Página pública (`index.html`), área de edição (`editar.html`), painel admin (`admin.html`) e API PHP (`api/`), com login do Google.

## Cuidados

- `api/config.php` aqui tem a `sessao_secreta` trocada por um texto de exemplo e o `google_client_id` vazio. Os valores verdadeiros ficam só no servidor. Não suba este config.php por cima do que está no servidor.
- `escalas.json` e `usuarios.json` não estão aqui: têm os nomes e e-mails das pessoas e são editados pelo site. Ficam só no servidor (assim como `backups/` e `log.ndjson`).
- Esta cópia é a da pasta do projeto em outubro de 2026. Se algo foi alterado direto no servidor depois disso, o servidor é a versão mais nova.
