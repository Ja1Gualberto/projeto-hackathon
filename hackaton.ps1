#Requires -Version 5.1
# Equivalente PowerShell do script hackaton, sem depender de Bash no host.

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$PSNativeCommandUseErrorActionPreference = $false

$ProjectDirectory = $PSScriptRoot
$Service = 'app'
$ComposeExecutable = 'docker'
$ComposePrefix = @('compose')
$Commands = [ordered]@{
    dev = 'Compila as imagens e inicia os servicos em segundo plano'
    stop = 'Para os containers (aceita servicos e opcoes do Compose)'
    down = 'Remove containers e rede, preservando volumes por padrao'
    restart = 'Reinicia os servicos existentes (aceita servicos e opcoes)'
    build = 'Reconstroi as imagens (aceita servicos e opcoes)'
    status = 'Mostra o estado dos containers'
    ps = 'Atalho para status'
    logs = 'Acompanha os logs (aceita servicos e opcoes)'
    artisan = 'Executa php artisan <args> no container'
    tinker = 'Atalho para php artisan tinker'
    composer = 'Executa composer <args> no container'
    php = 'Executa php <args> no container'
    pint = 'Executa vendor/bin/pint <args> no container'
    check = 'Formata o PHP com Pint no container'
    setup = 'Compila, inicia o ambiente e gera a APP_KEY se faltar'
    shell = 'Abre um Bash no container da aplicacao'
    exec = 'Executa um comando arbitrario no container'
    help = 'Mostra esta ajuda'
}

function Write-Info {
    param([string]$Message)

    [Console]::Error.WriteLine("[hackaton] $Message")
}

function Resolve-Compose {
    if (-not (Get-Command docker -CommandType Application -ErrorAction SilentlyContinue)) {
        throw 'Docker nao encontrado. Instale e inicie o Docker Desktop com containers Linux.'
    }

    $ErrorActionPreference = 'Continue'
    & docker compose version > $null 2>&1

    if ($LASTEXITCODE -eq 0) {
        return
    }

    if (Get-Command docker-compose -CommandType Application -ErrorAction SilentlyContinue) {
        $script:ComposeExecutable = 'docker-compose'
        $script:ComposePrefix = @()
        return
    }

    throw 'Docker Compose nao encontrado. Instale ou atualize o Docker Desktop.'
}

function Invoke-Compose {
    param([string[]]$ComposeArguments)

    $nativeArguments = @($script:ComposePrefix) + @(
        '--project-directory', $ProjectDirectory,
        '--file', (Join-Path $ProjectDirectory 'compose.yaml')
    ) + $ComposeArguments

    $ErrorActionPreference = 'Continue'
    & $script:ComposeExecutable @nativeArguments
    $exitCode = $LASTEXITCODE

    if ($exitCode -ne 0) {
        $exception = [Exception]::new("Docker Compose encerrou com codigo $exitCode.")
        $exception.Data['ExitCode'] = $exitCode
        throw $exception
    }
}

function Initialize-Environment {
    $environmentPath = Join-Path $ProjectDirectory '.env'

    if (-not (Test-Path -LiteralPath $environmentPath)) {
        Copy-Item -LiteralPath (Join-Path $ProjectDirectory '.env.example') -Destination $environmentPath
        Write-Info '.env criado a partir de .env.example. Use .\hackaton.ps1 setup para gerar a APP_KEY.'
    }
}

function Invoke-Development {
    param([string[]]$CommandArguments)

    Initialize-Environment
    Invoke-Compose (@('up', '-d', '--build') + $CommandArguments)
    Write-Info 'Servicos iniciados. Consulte as portas com .\hackaton.ps1 status.'
    Write-Info 'Acompanhe os logs com .\hackaton.ps1 logs.'
}

function Invoke-InContainer {
    param([string[]]$ContainerArguments)

    Initialize-Environment
    $containerIds = @(Invoke-Compose @('ps', '--status', 'running', '-q', $Service))

    if (-not ($containerIds -join '').Trim()) {
        Write-Info 'Container da aplicacao nao esta rodando. Iniciando com suas dependencias...'
        Invoke-Compose @('up', '-d', $Service)
    }

    $ttyArguments = @()
    if ([Console]::IsInputRedirected -or [Console]::IsOutputRedirected) {
        $ttyArguments = @('-T')
    }

    Invoke-Compose (@('exec') + $ttyArguments + @($Service) + $ContainerArguments)
}

function Show-Help {
    Write-Output ''
    Write-Output 'Projeto Hackathon - Laravel + Docker Compose'
    Write-Output 'Uso: .\hackaton.ps1 <comando> [argumentos]'
    Write-Output ''
    Write-Output 'Comandos disponiveis:'

    foreach ($entry in $Commands.GetEnumerator()) {
        Write-Output ('  {0,-10} {1}' -f $entry.Key, $entry.Value)
    }

    Write-Output ''
    Write-Output 'Exemplos:'
    Write-Output '  .\hackaton.ps1 setup'
    Write-Output '  .\hackaton.ps1 artisan migrate --no-interaction'
    Write-Output '  .\hackaton.ps1 logs app'
    Write-Output '  .\hackaton.ps1 exec ls -la'
    Write-Output ''
    Write-Output 'O build instala Composer/npm e compila os assets; Node nao esta na imagem final.'
    Write-Output 'O codigo e copiado para a imagem: apos alteracoes locais, execute .\hackaton.ps1 dev.'
    Write-Output 'Composer e Pint no container nao alteram os arquivos do projeto no host.'
    Write-Output 'Atencao: down --volumes apaga os dados persistidos, incluindo o banco.'
}

$command = 'help'
$commandArguments = @()

if ($args.Count -gt 0) {
    $command = $args[0]
    $commandArguments = @($args | Select-Object -Skip 1)
}

if ($command -in @('-h', '--help')) {
    $command = 'help'
}

if (-not $Commands.Contains($command)) {
    Write-Info "Comando desconhecido: $command"
    Show-Help
    exit 2
}

try {
    if ($command -ne 'help') {
        Resolve-Compose
    }

    switch ($command) {
        'help' { Show-Help }
        'dev' { Invoke-Development $commandArguments }
        'stop' { Invoke-Compose (@('stop') + $commandArguments) }
        'down' { Invoke-Compose (@('down') + $commandArguments) }
        'restart' { Invoke-Compose (@('restart') + $commandArguments) }
        'build' {
            Initialize-Environment
            Invoke-Compose (@('build') + $commandArguments)
        }
        { $_ -in @('status', 'ps') } { Invoke-Compose (@('ps') + $commandArguments) }
        'logs' { Invoke-Compose (@('logs', '--follow', '--tail=100') + $commandArguments) }
        'artisan' { Invoke-InContainer (@('php', 'artisan') + $commandArguments) }
        'tinker' { Invoke-InContainer (@('php', 'artisan', 'tinker') + $commandArguments) }
        'composer' { Invoke-InContainer (@('composer') + $commandArguments) }
        'php' { Invoke-InContainer (@('php') + $commandArguments) }
        'pint' { Invoke-InContainer (@('vendor/bin/pint') + $commandArguments) }
        'check' { Invoke-InContainer (@('vendor/bin/pint', '--format', 'agent') + $commandArguments) }
        'shell' { Invoke-InContainer (@('bash') + $commandArguments) }
        'exec' {
            if ($commandArguments.Count -eq 0) {
                throw 'Informe o comando. Ex.: .\hackaton.ps1 exec ls -la'
            }

            Invoke-InContainer $commandArguments
        }
        'setup' {
            Invoke-Development $commandArguments

            if (-not (Select-String -LiteralPath (Join-Path $ProjectDirectory '.env') -Pattern '^APP_KEY=[''"]?[^''"\s#]+' -Quiet)) {
                Invoke-InContainer @('php', 'artisan', 'key:generate', '--no-interaction')
            }

            Write-Info 'Ambiente configurado. Execute .\hackaton.ps1 artisan migrate --no-interaction quando necessario.'
        }
    }
} catch {
    Write-Info $_.Exception.Message

    if ($_.Exception.Data.Contains('ExitCode')) {
        exit $_.Exception.Data['ExitCode']
    }

    exit 1
}

exit 0
