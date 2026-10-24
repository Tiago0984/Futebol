<?php

namespace Tests\Feature;

use App\Models\ComissaoTecnica;
use App\Models\EventoCalendario;
use App\Models\GradeTreino;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Responsável pela atividade escolhido da comissão técnica (Etapa C2, 25/10/2026; antes, texto livre de 24/10):
 * select só com os ativos no evento e na grade, copiado pelo "Gerar agenda do mês", "responsavel" (nome) e
 * "responsavel_id" na agenda do app. O texto antigo continua até alguém escolher da lista; o profissional
 * desativado continua onde estava, mas não é escolhido de novo. Mudar não avisa os atletas.
 * "Hoje" fixo: segunda-feira, 16/11/2026.
 */
class ResponsavelPelaAtividadeTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-16 10:00:00');
    }

    public function test_evento_grava_o_profissional_mostra_no_app_e_a_troca_vai_so_para_o_historico(): void
    {
        $carlos = $this->profissional('Carlos Mendes');
        $bruna = $this->profissional('Bruna Lima');
        $atleta = $this->atletaComLogin('ana@exemplo.com');
        $sub13 = $this->idCategoria('Sub-13', 'M');
        $this->colocarNaCategoria($atleta, $sub13);

        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), $this->dados(['id_categoria' => $sub13, 'id_comissao_tecnica' => $carlos]))
            ->assertSessionHasNoErrors();
        $evento = EventoCalendario::firstOrFail();
        $this->assertSame([$carlos, null], [$evento->id_comissao_tecnica, $evento->responsavel_evento_calendario]);
        $this->assertNotNull($evento->id_usuario); // quem criou continua à parte

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'ana@exemplo.com', 'senha' => 'teste1234'])->json('data.token');
        $this->withToken($token)->getJson('/api/v1/agenda')
            ->assertJsonPath('data.proximos.0.responsavel', 'Carlos Mendes')->assertJsonPath('data.proximos.0.responsavel_id', $carlos);
        $this->withToken($token)->getJson("/api/v1/agenda/{$evento->id_evento_calendario}")
            ->assertJsonPath('data.responsavel', 'Carlos Mendes')->assertJsonPath('data.responsavel_id', $carlos);

        // Trocar: histórico com os nomes, sem aviso aos atletas e sem "Alterado"
        $avisosAntes = DB::table('tbl_notificacao')->count();
        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $evento->id_evento_calendario), $this->dados([
            'id_categoria' => $sub13, 'id_comissao_tecnica' => $bruna,
        ]))->assertSessionHasNoErrors();
        $this->assertSame($avisosAntes, DB::table('tbl_notificacao')->count());
        $this->assertSame(['Responsável pela atividade: Carlos Mendes → Bruna Lima'], $evento->fresh()->historico()->get()->pluck('resumo')->all());
        $this->assertFalse($evento->fresh()->foiAlterado());

        // Sem responsável: os dois null no app
        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $evento->id_evento_calendario), $this->dados(['id_categoria' => $sub13, 'id_comissao_tecnica' => '']));
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/agenda')
            ->assertJsonPath('data.proximos.0.responsavel', null)->assertJsonPath('data.proximos.0.responsavel_id', null);
    }

    public function test_texto_antigo_continua_ate_escolher_da_lista(): void
    {
        $carlos = $this->profissional('Carlos Mendes');
        $atleta = $this->atletaComLogin('ana@exemplo.com');
        $evento = $this->eventoAntigo('Prof. Carlos', [$atleta]);
        $token = $this->postJson('/api/v1/auth/login', ['email' => 'ana@exemplo.com', 'senha' => 'teste1234'])->json('data.token');

        $this->withToken($token)->getJson('/api/v1/agenda')
            ->assertJsonPath('data.proximos.0.responsavel', 'Prof. Carlos')->assertJsonPath('data.proximos.0.responsavel_id', null);

        // A tela do evento oferece o texto antigo, já selecionado
        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $evento))->assertOk()
            ->assertSee('<option value="texto-antigo" class="js-opcao-extra" selected>Prof. Carlos (texto antigo)</option>', false);

        // Salvar mantendo o texto antigo não muda nada
        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $evento), $this->dados(['id_comissao_tecnica' => ComissaoTecnica::TEXTO_ANTIGO]))
            ->assertSessionHasNoErrors();
        $this->assertSame([null, 'Prof. Carlos'], [EventoCalendario::find($evento)->id_comissao_tecnica, EventoCalendario::find($evento)->responsavel_evento_calendario]);

        // Escolher da lista limpa o texto
        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $evento), $this->dados(['id_comissao_tecnica' => $carlos]));
        $this->assertSame([$carlos, null], [EventoCalendario::find($evento)->id_comissao_tecnica, EventoCalendario::find($evento)->responsavel_evento_calendario]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/agenda')->assertJsonPath('data.proximos.0.responsavel', 'Carlos Mendes');

        // Texto antigo não pode ser escolhido num evento que não tem (nem no novo)
        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), $this->dados(['id_comissao_tecnica' => ComissaoTecnica::TEXTO_ANTIGO]))
            ->assertSessionHasErrors(['id_comissao_tecnica' => 'Escolha um profissional da lista.']);
    }

    public function test_desativado_fica_onde_estava_mas_nao_e_escolhido_de_novo(): void
    {
        $carlos = $this->profissional('Carlos Mendes');
        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), $this->dados(['id_comissao_tecnica' => $carlos]));
        $evento = EventoCalendario::firstOrFail();
        DB::table('tbl_comissao_tecnica')->where('id_comissao_tecnica', $carlos)->update(['status_comissao_tecnica' => 'INATIVO']);

        // Novo evento: fora da lista e recusado pelo servidor
        $this->comoAdmin()->get(route('admin.calendario.index', ['mes' => '2026-11']))->assertOk()
            ->assertDontSee('Carlos Mendes · Treinador técnico')       // opção do select
            ->assertSee('Responsável: Carlos Mendes');                 // a linha do evento continua com ele
        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), $this->dados(['id_comissao_tecnica' => $carlos]))
            ->assertSessionHasErrors(['id_comissao_tecnica' => 'Escolha um profissional ativo da comissão técnica.']);

        // No evento em que já estava: continua, com o "(inativo)", e pode ser mantido
        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $evento->id_evento_calendario))->assertOk()
            ->assertSee('Carlos Mendes (inativo)');
        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $evento->id_evento_calendario), $this->dados(['id_comissao_tecnica' => $carlos]))
            ->assertSessionHasNoErrors();
        $this->assertSame($carlos, $evento->fresh()->id_comissao_tecnica);
        $this->assertSame('Carlos Mendes', $evento->fresh()->nomeDoResponsavel());

        // Valor que não é profissional
        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), $this->dados(['id_comissao_tecnica' => 'Prof. Carlos']))
            ->assertSessionHasErrors(['id_comissao_tecnica' => 'Escolha um profissional da lista.']);
    }

    public function test_grade_grava_o_profissional_e_a_geracao_do_mes_copia_para_os_treinos(): void
    {
        $carlos = $this->profissional('Carlos Mendes');
        $bruna = $this->profissional('Bruna Lima');
        $this->comoAdmin()->post(route('admin.calendario.grade.store'), $this->dadosGrade([
            'dias_semana_grade_treino' => ['terca', 'quinta'], 'id_comissao_tecnica' => $carlos,
        ]))->assertSessionHasNoErrors();
        $this->assertSame([$carlos, $carlos], GradeTreino::pluck('id_comissao_tecnica')->all());

        // Linha antiga com texto: os treinos dela levam o texto
        $antiga = GradeTreino::create($this->dadosGrade(['dia_semana_grade_treino' => 'sabado', 'tipo_grade_treino' => 'LIVRE'])
            + ['responsavel_grade_treino' => 'Prof. Joel', 'status_grade_treino' => 'ATIVO']);

        $this->comoAdmin()->post(route('admin.calendario.grade.gerar'), ['mes' => '2026-11'])->assertSessionHas('sucesso');

        // Terças e quintas de 17/11 em diante (17, 19, 24 e 26) e sábados (21 e 28)
        $gerados = EventoCalendario::whereNotNull('id_grade_treino')->orderBy('data_evento_calendario')->get();
        $this->assertSame([$carlos, $carlos, null, $carlos, $carlos, null], $gerados->pluck('id_comissao_tecnica')->all());
        $this->assertSame(['Prof. Joel', 'Prof. Joel'], $gerados->where('id_grade_treino', $antiga->id_grade_treino)->pluck('responsavel_evento_calendario')->values()->all());

        // Trocar o responsável da linha não muda os já gerados: só avisa
        $linha = GradeTreino::where('dia_semana_grade_treino', 'terca')->firstOrFail();
        $this->comoAdmin()->put(route('admin.calendario.grade.update', $linha->id_grade_treino), $this->dadosGrade([
            'dia_semana_grade_treino' => 'terca', 'id_comissao_tecnica' => $bruna,
        ]))->assertSessionHas('sucesso', fn ($mensagem) => str_contains($mensagem, '2 evento(s) futuro(s) já gerado(s)'));
        $this->assertSame($bruna, $linha->fresh()->id_comissao_tecnica);
        $this->assertSame(0, EventoCalendario::where('id_comissao_tecnica', $bruna)->count());

        // A lista da grade mostra os nomes; os dados do Editar levam o id e o texto antigo
        $this->comoAdmin()->get(route('admin.calendario.index', ['tab' => 'grade']))->assertOk()
            ->assertSee('Bruna Lima')->assertSee('Prof. Joel (texto antigo)')
            ->assertSee('data-responsavel-id="' . $bruna . '"', false)
            ->assertSee('data-responsavel-texto="Prof. Joel"', false);
    }

    public function test_formularios_tem_so_o_select_com_os_ativos(): void
    {
        $this->profissional('Carlos Mendes');
        $this->profissional('Zélia Inativa', 'INATIVO');

        $this->comoAdmin()->get(route('admin.calendario.index', ['mes' => '2026-11']))->assertOk()
            ->assertSee('Carlos Mendes · Treinador técnico')
            ->assertDontSee('Zélia Inativa')
            ->assertDontSee('name="responsavel_evento_calendario"', false);
        $this->comoAdmin()->get(route('admin.calendario.index', ['tab' => 'grade']))->assertOk()
            ->assertDontSee('name="responsavel_grade_treino"', false);
        $this->comoAdmin()->get(route('admin.calendario.index', ['ramo' => 'individuais']))->assertOk()
            ->assertSee('id="ind_responsavel"', false);
    }

    // ---------- apoio ----------

    private function profissional(string $nome, string $status = 'ATIVO'): int
    {
        return DB::table('tbl_comissao_tecnica')->insertGetId([
            'nome_comissao_tecnica' => $nome, 'cargo_comissao_tecnica' => 'TREINADOR_TECNICO', 'status_comissao_tecnica' => $status,
        ]);
    }

    // Evento de antes da Etapa C2, com o responsável em texto livre
    private function eventoAntigo(string $texto, array $inscritos): int
    {
        $id = DB::table('tbl_evento_calendario')->insertGetId([
            'titulo_evento_calendario' => 'Treino de Teste', 'tipo_evento_calendario' => 'TREINO', 'data_evento_calendario' => '2026-11-20',
            'horario_inicio_evento_calendario' => '14:00', 'local_evento_calendario' => 'Campo A', 'status_evento_calendario' => 'ATIVO',
            'responsavel_evento_calendario' => $texto,
        ]);
        foreach ($inscritos as $idAtleta) {
            DB::table('tbl_evento_atleta')->insert(['id_evento_calendario' => $id, 'id_atleta' => $idAtleta, 'origem_evento_atleta' => 'INDIVIDUAL']);
        }

        return $id;
    }

    private function atletaComLogin(string $email): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['email_atleta' => $email, 'password' => Hash::make('teste1234')]);

        return $id;
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'titulo_evento_calendario'         => 'Treino de Teste',
            'tipo_evento_calendario'           => 'TREINO',
            'data_evento_calendario'           => '2026-11-20',
            'horario_inicio_evento_calendario' => '14:00',
            'local_evento_calendario'          => 'Campo A',
        ], $extra);
    }

    private function dadosGrade(array $extra = []): array
    {
        return array_merge([
            'dias_semana_grade_treino'    => ['terca'],
            'categoria_grade_treino'      => 'Integrado',
            'tipo_grade_treino'           => 'TREINO',
            'horario_inicio_grade_treino' => '14:00',
            'horario_fim_grade_treino'    => '17:00',
            'local_grade_treino'          => 'Campo A',
        ], $extra);
    }
}
