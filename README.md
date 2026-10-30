# Radar Mage

Painel público de versões do **Mage Lite** — o que está sendo feito agora, o que vem na próxima versão, histórico por versão, tarefas planejadas, Notas Técnicas da Sefaz e situação dos autorizadores. Publicado em **radar.magesistemas.com.br**.

- Site público: `index.php`, `agora.php`, `versoes.php`, `planejado.php`, `fiscal.php`, `projeto.php`
- Painel administrativo: `admin/` (primeiro acesso define a senha)
- Dados: SQLite em `data/radar.db`, criado sozinho na primeira execução (fora do git)
- Sefaz: `inc/sefaz.php` lê a página de disponibilidade do Portal da NF-e; `sefaz-atualizar.php` é o endpoint para o cron

Requisitos: PHP 8.1+ com `pdo_sqlite`, `mbstring` e `curl`.

## Deploy

Push na branch `main` → cPanel > Git™ Version Control > **Update from Remote** → **Deploy HEAD Commit**. O `.cpanel.yml` copia tudo para a pasta do subdomínio, exceto `data/`, para o banco em produção nunca ser sobrescrito.

Detalhes de instalação e uso em `LEIA-ME.txt`.
