<?php

namespace Database\Seeders;

use App\Enums\Area;
use App\Enums\MeioConsentimento;
use App\Enums\OrigemCompetencia;
use App\Enums\TipoCandidato;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\User;
use Database\Seeders\Support\GeradorPdfCurriculo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CandidatoSeeder extends Seeder
{
    /**
     * Ordem estável de E1–E12 e S1–S12, usada no desempate do roteiro.
     *
     * @var list<array{nome: string, area: string, competencias: list<string>}>
     */
    private const PERFIS = [
        ['nome' => 'Marina Duarte', 'area' => 'desenvolvimento', 'competencias' => ['Python', 'SQL', 'Git', 'Inglês intermediário', 'Trabalho em equipe', 'PHP', 'Laravel']],
        ['nome' => 'Lucas Andrade', 'area' => 'desenvolvimento', 'competencias' => ['Python', 'SQL', 'Git', 'Inglês básico', 'JavaScript']],
        ['nome' => 'Ana Lima', 'area' => 'desenvolvimento', 'competencias' => ['Python', 'SQL', 'Git', 'Trabalho em equipe', 'React']],
        ['nome' => 'Beatriz Nogueira', 'area' => 'desenvolvimento', 'competencias' => ['Python', 'SQL', 'Inglês avançado', 'Trabalho em equipe', 'Comunicação']],
        ['nome' => 'Rafael Monteiro', 'area' => 'dados', 'competencias' => ['Python', 'SQL', 'Power BI', 'Excel Avançado', 'Pensamento analítico']],
        ['nome' => 'Camila Farias', 'area' => 'desenvolvimento', 'competencias' => ['Python', 'SQL', 'Docker']],
        ['nome' => 'Pedro Valença', 'area' => 'desenvolvimento', 'competencias' => ['Python', 'SQL', 'Linux', 'Inglês básico']],
        ['nome' => 'Júlia Azevedo', 'area' => 'desenvolvimento', 'competencias' => ['JavaScript', 'Git', 'Trabalho em equipe', 'Figma']],
        ['nome' => 'Gabriel Tavares', 'area' => 'infraestrutura', 'competencias' => ['Linux', 'Redes de Computadores', 'Suporte a Hardware', 'Trabalho em equipe']],
        ['nome' => 'Letícia Campos', 'area' => 'suporte', 'competencias' => ['Atendimento ao público', 'Windows', 'Comunicação']],
        ['nome' => 'Isabela Prado', 'area' => 'design', 'competencias' => ['Figma', 'Comunicação', 'Inglês básico']],
        ['nome' => 'Vinícius Melo', 'area' => 'dados', 'competencias' => ['SQL', 'Excel Avançado', 'Organização']],
        ['nome' => 'Helena Ribeiro', 'area' => 'suporte', 'competencias' => ['ITIL', 'Windows Server', 'Active Directory', 'Atendimento ao público', 'Comunicação']],
        ['nome' => 'André Siqueira', 'area' => 'infraestrutura', 'competencias' => ['Linux', 'Docker', 'Redes de Computadores', 'Cloud (AWS/Azure)']],
        ['nome' => 'Bruno Peixoto', 'area' => 'dados', 'competencias' => ['SQL', 'PostgreSQL', 'Power BI', 'Python']],
        ['nome' => 'Carolina Bastos', 'area' => 'desenvolvimento', 'competencias' => ['PHP', 'Laravel', 'MySQL', 'Git', 'JavaScript', 'Scrum']],
        ['nome' => 'Daniela Moura', 'area' => 'produto', 'competencias' => ['Scrum', 'Levantamento de Requisitos', 'UML', 'Comunicação', 'Liderança']],
        ['nome' => 'Eduardo Lacerda', 'area' => 'seguranca', 'competencias' => ['Segurança da Informação', 'Linux', 'Redes de Computadores']],
        ['nome' => 'Fernanda Reis', 'area' => 'desenvolvimento', 'competencias' => ['Java', 'SQL', 'Git', 'API REST', 'Testes automatizados']],
        ['nome' => 'Gustavo Pires', 'area' => 'suporte', 'competencias' => ['Suporte a Hardware', 'Windows', 'Atendimento ao público', 'Proatividade']],
        ['nome' => 'Renata Barros', 'area' => 'dados', 'competencias' => ['Python', 'SQL', 'Excel Avançado', 'Pensamento analítico']],
        ['nome' => 'Sérgio Amaral', 'area' => 'design', 'competencias' => ['Figma', 'Comunicação', 'Organização']],
        ['nome' => 'Tatiana Freitas', 'area' => 'infraestrutura', 'competencias' => ['Windows Server', 'Active Directory', 'Redes de Computadores', 'ITIL']],
        ['nome' => 'Wesley Guimarães', 'area' => 'desenvolvimento', 'competencias' => ['C#', '.NET', 'SQL', 'Git', 'Inglês intermediário']],
    ];

    public function run(GeradorPdfCurriculo $gerador): void
    {
        $catalogo = Competencia::query()->get()->keyBy('nome')->toBase();
        $responsavel = User::query()->where('email', 'recrutador@setic.demo')->value('id');

        foreach (self::PERFIS as $indice => $perfil) {
            $email = Str::of($perfil['nome'])->ascii()->lower()->replace(' ', '.').'@exemplo.com.br';

            if (Candidato::query()->where('email', $email)->exists()) {
                continue;
            }

            $tipo = $indice < 12 ? TipoCandidato::Estagiario : TipoCandidato::Servidor;
            $area = Area::from($perfil['area']);
            $experiente = in_array($indice, [0, 3, 6, 12, 13, 14, 15, 16, 17, 18, 20, 22, 23], true);
            $telefone = '(00) 90000-'.str_pad((string) ($indice + 1), 4, '0', STR_PAD_LEFT);
            $formacoes = [($tipo === TipoCandidato::Estagiario ? 'Cursando ' : 'Graduação em ').'Sistemas de Informação — Instituto Vale Digital (fictício)'];
            $experiencias = $experiente
                ? ['Atuação em '.$area->rotulo().' no Laboratório Horizonte (fictício), com '.$perfil['competencias'][0].'.']
                : [];
            $projetos = ['Projeto acadêmico em '.$area->rotulo().' com '.$perfil['competencias'][0].'.'];
            $links = ['https://example.com/portfolio/'.Str::slug($perfil['nome'])];
            $idiomas = $catalogo->only($perfil['competencias'])->filter(fn (Competencia $item): bool => $item->ehIdioma());
            $linhas = [
                $perfil['nome'], $email, $telefone, 'Vila Horizonte — cidade fictícia',
                'FORMAÇÃO', ...$formacoes, 'EXPERIÊNCIA PROFISSIONAL', ...$experiencias,
                'PROJETOS', ...$projetos, ...$links, 'COMPETÊNCIAS',
                ...array_map(fn (array $grupo): string => implode(', ', $grupo), array_chunk($perfil['competencias'], 4)),
                'IDIOMAS', ...$idiomas->pluck('nome')->all(),
            ];
            $pdf = $gerador->gerar($linhas);
            $arquivo = Str::uuid().'.pdf';
            if (! Storage::disk('curriculos')->put($arquivo, $pdf)) {
                throw new RuntimeException('Não foi possível gravar o currículo fictício no disco privado.');
            }

            $candidato = Candidato::query()->create([
                'nome' => $perfil['nome'], 'email' => $email, 'telefone' => $telefone,
                'tipo' => $tipo, 'area' => $area,
                'data_nascimento' => now()->subYears($tipo === TipoCandidato::Estagiario ? 20 + $indice % 5 : 30 + $indice % 12)->toDateString(),
                'formacoes' => $formacoes, 'experiencias' => $experiencias,
                'experiencia_na_area' => $experiente, 'projetos' => $projetos, 'links' => $links,
                'texto_curriculo' => implode("\n", $linhas),
                'curriculo_arquivo' => $arquivo, 'curriculo_nome_original' => 'curriculo-'.Str::slug($perfil['nome']).'.pdf',
                'curriculo_tamanho' => strlen($pdf),
                'consentimento_meio' => MeioConsentimento::cases()[$indice % count(MeioConsentimento::cases())],
                'consentimento_em' => now()->subDays(5 + $indice * 5)->toDateString(),
                'consentimento_registrado_por' => $responsavel,
                'dados_atualizados_em' => in_array($indice, [17, 21, 22], true) ? now()->subYears(2)->subMonths(2) : now(),
                'created_at' => now()->subDays(30)->addMinutes($indice),
            ]);

            $candidato->competencias()->attach($catalogo->only($perfil['competencias'])->pluck('id')->all(), [
                'origem' => OrigemCompetencia::Curriculo->value,
            ]);
        }
    }
}
