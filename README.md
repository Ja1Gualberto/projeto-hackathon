# Banco de Talentos SETIC

Sistema para encontrar talentos por competência e conduzir seleções com triagem anônima. Laravel 13
com estrutura MVC + Services + Repositories, DTOs e Laravel Boost instalado.

## Demonstração

Requer Docker. Prepara tudo do zero, com dados fictícios: recrutador, catálogo, 24 candidatos com
PDF e 3 vagas.

```powershell
.\hackaton.ps1 demo      # Windows (PowerShell)
```

```bash
./hackaton demo          # Linux
```

Acesse `http://localhost:8000` com **`recrutador@setic.demo`** / **`demo1234`**. O comando `status`
mostra as portas efetivas.

> **Rode o `demo` com internet.** Quando o código muda, a imagem é reconstruída e precisa baixar
> dependências e a fonte. Depois de pronto, o sistema funciona sem internet, e o Wi-Fi pode ser
> desligado. O container `vite` precisa continuar no ar: parado, as telas ficam sem estilo ou
> respondem erro 500. Atenção: `demo` executa `migrate:fresh` e **apaga os dados do banco local**.

| Comando | Para quê |
|---|---|
| `php artisan demo:curriculos` | Gera novamente os PDFs de exemplo em `database/seeders/curriculos` |
| `php artisan energia:analisar` | Analisa o padrão de energia de quem ainda não tem perfil; `--todos` reanalisa os de origem IA, nunca os revisados por humano |

No Docker, use `.\hackaton.ps1 artisan <comando>` ou `./hackaton artisan <comando>`. A análise de
energia é opcional e exige internet, `ENERGIA_IA_HABILITADA=true` e `ANTHROPIC_API_KEY` no seu
`.env`. Sem isso, o sistema mostra "Análise indisponível" e todo o resto funciona normalmente.

Roteiro completo do pitch e cenários de validação:
[`specs/001-banco-de-talentos-mvp/quickstart.md`](specs/001-banco-de-talentos-mvp/quickstart.md).

## Estrutura

```text
app/
├── DTOs/                       # Objetos de transferência de dados, com sufixo DTO
├── Http/
│   ├── Controllers/            # Entrada HTTP e respostas
│   └── Requests/               # Validação das requisições
├── Models/                     # Entidades Eloquent
├── Services/                   # Casos de uso e regras de negócio
├── Repositories/
│   ├── Interfaces/             # {Entidade}RepositoryInterface.php
│   └── {Entidade}Repository.php # Implementações: consultas e persistência
└── Providers/
    └── RepositoryServiceProvider.php
resources/views/
├── layouts/                    # Layouts Blade
├── components/                 # Design system (x-botao, x-campo, x-modal...)
└── {módulo}/                   # Telas e partials de cada módulo
routes/                         # web.php e um arquivo por módulo
database/migrations/            # Estrutura do banco
```

Fluxo recomendado: **Rota → Form Request → Controller → Service → Repository → Model/banco**. A apresentação fica nas Views Blade.

Cada repository tem uma interface em `app/Repositories/Interfaces/{Entidade}RepositoryInterface.php` e uma implementação diretamente em `app/Repositories/{Entidade}Repository.php`, sem subpastas `Contracts`/`Eloquent` nem prefixo `Eloquent`. Services dependem das interfaces; registre os vínculos interface → implementação em `RepositoryServiceProvider`, já registrado em `bootstrap/providers.php`.

DTOs ficam em `app/DTOs/`, com sufixo `DTO` (ex.: `FiltroCandidatosDTO`). As operações de candidato ficam centralizadas em `app/Services/CandidatoService.php`; o painel usa `app/Services/PainelService.php`. Form Requests continuam responsáveis pela validação e autorização da entrada HTTP.

## Requisitos

### Com Docker (Linux e Windows)

- Linux: Docker Engine e Docker Compose.
- Windows 10/11: Docker Desktop com backend WSL 2, usando **containers Linux**, e PowerShell 5.1 ou superior (recomendado: PowerShell 7).
- Git para clonar o projeto. PHP, Composer e Node.js são instalados na imagem; não precisam estar no host.

### Sem Docker

- PHP 8.5 e Composer 2 para reproduzir o ambiente desta instalação.
- Extensões PHP exigidas pelo Laravel e **pdo_sqlite** para o banco padrão.
- Node.js/npm para trabalhar com os assets Vite/Tailwind.

### SQLite no Arch Linux/CachyOS

O ambiente inicial não tinha o driver SQLite. Instale o pacote mantendo o sistema atualizado:

```bash
sudo pacman -Syu php-sqlite
```

Habilite no `php.ini` utilizado pelo PHP (`php --ini` mostra o caminho):

```ini
extension=pdo_sqlite
extension=sqlite3
```

Confirme com `php -m`. Reinicie servidores PHP persistentes após alterar as extensões.

## Executar com Docker

### Windows (PowerShell)

Inicie o Docker Desktop e, no PowerShell aberto na pasta do projeto, execute:

```powershell
.\hackaton.ps1 setup
.\hackaton.ps1 artisan migrate --no-interaction
.\hackaton.ps1 status
```

Se o PowerShell bloquear a execução de scripts, libere apenas a sessão atual e tente novamente:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
```

O `setup` cria o `.env` a partir de `.env.example` somente se ele não existir, compila os assets, inicia os serviços e gera a chave da aplicação somente se estiver ausente. Execute esses comandos localmente; não compartilhe seu `.env` nem seus segredos.

Outros comandos disponíveis:

```powershell
.\hackaton.ps1 help
.\hackaton.ps1 dev
.\hackaton.ps1 logs app
.\hackaton.ps1 artisan migrate:status
.\hackaton.ps1 stop
.\hackaton.ps1 down
```

O script funciona sem Git Bash e localiza o `compose.yaml` pela pasta do próprio script, mesmo quando chamado de outro diretório ou quando o caminho contém espaços.

### Linux (Bash)

```bash
./hackaton setup
./hackaton artisan migrate --no-interaction
./hackaton status
```

### Comportamento do ambiente Docker

- Portas padrão: aplicação **8000**, phpMyAdmin **8080** e MySQL **3307**, disponíveis apenas na máquina local. `status` mostra as portas efetivas.
- O código é copiado para a imagem, não sincronizado com o host. Após alterações, execute `dev` para reconstruir e reiniciar a aplicação.
- Composer e Pint executados no container não alteram os arquivos do projeto no host.
- `stop` para os serviços; `down` remove os containers, mas preserva os volumes. **Não use `down --volumes` se quiser manter os dados do banco e do storage.**
- Se alguma porta estiver ocupada, configure `DOCKER_APP_PORT`, `DOCKER_PHPMYADMIN_PORT` ou `DOCKER_MYSQL_PORT` localmente antes de iniciar o ambiente.

## Executar sem Docker

As dependências, o `.env`, a chave e o arquivo SQLite já foram criados. Depois de habilitar o driver:

```bash
php artisan migrate
php artisan serve
```

Para configurar um novo clone:

```bash
composer install
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
php artisan serve
```

No PowerShell, substitua `cp .env.example .env` por `Copy-Item .env.example .env`. Execute `npm install --ignore-scripts` e `npm run build` para gerar os assets antes de abrir a aplicação; durante o desenvolvimento, use `npm run dev` em outro terminal.

Não versione o `.env`, credenciais ou o banco local.

## Laravel Boost

Instalado como dependência de desenvolvimento (`laravel/boost`). O servidor MCP e as orientações para agentes estão configurados pelo instalador do Boost.

Para atualizar as orientações ou reconfigurar a integração:

```bash
php artisan boost:update
php artisan boost:install
```

## Verificação

```bash
composer validate --strict
vendor/bin/pint --format agent
php artisan route:list --except-vendor
php artisan test
node --test tests/js/*.test.js
```

Laravel é distribuído sob a licença MIT.
