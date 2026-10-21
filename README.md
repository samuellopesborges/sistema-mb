# Smart Encartes

Aplicacao Node/Express pronta para deploy no Easypanel. Os leads ficam no
arquivo persistente `/app/data/leads.json`.

## Variaveis obrigatorias

Configure na aba **Ambiente** do Easypanel:

```env
NODE_ENV=production
PORT=3000
DATA_DIR=/app/data
TRUST_PROXY=1
LEADS_USER=seu_usuario_administrativo
LEADS_PASSWORD=uma_senha_aleatoria_com_pelo_menos_20_caracteres
```

O servidor nao inicia sem usuario e senha, ou quando a senha tem menos de
20 caracteres.

## Easypanel

- Build: `Dockerfile`
- Proxy port: `3000`
- Replicas: `1`
- Volume name: `leads-data`
- Volume mount path: `/app/data`
- Porta publicada: nenhuma; use somente **Domains & Proxy**
- HTTPS: habilitado

Use apenas uma replica enquanto o armazenamento for um arquivo JSON.

## Atualizacao sem perder dados

1. Confirme que o volume `leads-data` esta montado em `/app/data`.
2. Faca backup de `/app/data/leads.json`.
3. Envie o codigo ao Git e execute o deploy.
4. Confira `/healthz`, `/diagnostico` e `/leads`.
5. Reinicie o servico e confirme que os leads continuam no painel.

## Desenvolvimento local

```powershell
$env:LEADS_USER='admin-local'
$env:LEADS_PASSWORD='uma-senha-local-com-20-caracteres'
npm install
npm start
```

Rotas principais:

- `/` site
- `/guia-de-automacao` guia completo de automacao (SEO)
- `/automacao-encartes-photoshop` automacao no Photoshop (SEO)
- `/software-encartes-supermercado` software para supermercado (SEO)
- `/encartes-para-agencias` encartes para agencias (SEO)
- `/preencher-encartes-automaticamente` preenchimento automatico (SEO)
- `/como-fazer-encarte-supermercado` como fazer encartes (SEO)
- `/remover-fundo-produtos-encarte` remocao de fundo de produtos (SEO)
- `/diagnostico` formulario
- `/leads` painel protegido
- `/healthz` verificacao de saude
