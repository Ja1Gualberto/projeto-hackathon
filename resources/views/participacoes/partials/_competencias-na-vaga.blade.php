{{--
    Conteúdo da região "competencias-candidato" no perfil na vaga: a aderência e as competências,
    que mudam juntas quando uma anotação registra competências observadas. Recebe só o PerfilNaVagaDTO.
--}}
<section aria-labelledby="perfil-aderencia" class="flex flex-col gap-3 rounded-card border border-borda bg-superficie p-5">
    <h2 id="perfil-aderencia" class="text-xl font-semibold tracking-tight">Aderência a esta vaga</h2>
    <x-barra-score :percentual="$perfil->resultado->percentual" class="max-w-md" />
    <p class="leading-relaxed">{{ $perfil->resultado->explicacao() }}</p>
    <div class="flex flex-wrap gap-2">
        @foreach ($perfil->resultado->atendidas as $competencia)
            <x-tag-competencia :nome="$competencia['nome']" :origem="$competencia['origem']" :peso="$competencia['peso']" />
        @endforeach
        @foreach ($perfil->resultado->naoIdentificadas as $competencia)
            <x-tag-competencia :nome="$competencia['nome']" :faltante="true" :peso="$competencia['peso']" />
        @endforeach
    </div>
</section>

@include('candidatos.partials._competencias', ['competencias' => $perfil->competencias])
