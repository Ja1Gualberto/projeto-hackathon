# Projeto Hackathon

Laravel 13 com estrutura MVC + Services + Repositories e Laravel Boost instalado. Sem CRUD ou testes iniciais.

## Estrutura

```text
app/
├── Http/
│   ├── Controllers/            # Entrada HTTP e respostas
│   └── Requests/               # Validação das requisições
├── Models/                     # Entidades Eloquent
├── Services/                   # Casos de uso e regras de negócio
├── Repositories/
│   ├── Contracts/              # Interfaces dos repositories
│   └── Eloquent/               # Consultas e persistência
└── Providers/
    └── RepositoryServiceProvider.php
resources/views/
├── layouts/                    # Layouts Blade
└── welcome.blade.php           # Página inicial padrão do Laravel
routes/web.php                  # Rotas web
database/migrations/            # Estrutura do banco
```

Fluxo recomendado: **Rota → Form Request → Controller → Service → Repository → Model/banco**. A apresentação fica nas Views Blade.

As pastas de Services e Repositories estão vazias, prontas para novos módulos. Registre os vínculos entre contratos e implementações em `RepositoryServiceProvider`, já registrado em `bootstrap/providers.php`.

## Requisitos

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

## Executar localmente

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
```

Laravel é distribuído sob a licença MIT.
