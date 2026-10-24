<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\GradeTreino;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Responsável pela atividade (24/10/2026, pedido do app; opção (b) do dono do projeto): campo opcional no
 * formulário do evento e na grade, copiado pelo "Gerar agenda do mês" e devolvido na agenda do app
 * ("responsavel": o nome ou null). Não é quem criou o evento (id_usuario), e mudar não avisa os atletas.
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

    public function test_evento_grava_o_responsavel_mostra_no_app_e_a_mudanca_vai_so_para_o_historico(): void
    {
        $atleta = $this->atletaComLogin('ana@exemplo.com');
        $sub13 = $this->idCategoria('Sub-13', 'M');
        $this->colocarNaCategoria($atleta, $sub13);

        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), $this->dados([
            'id_categoria' => $sub13, 'responsavel_evento_calendario' => '  Prof. Carlos ',
        ]))->assertSessionHasNoErrors();
        $evento = EventoCalendario::firstOrFail();
        $this->assertSame('Prof. Carlos', $evento->responsavel_evento_calendario);
        $this->assertNotNull($evento->id_usuario); // quem criou continua à parte

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'ana@exemplo.com', 'senha' => 'teste1234'])->json('data.token');
        $this->withToken($token)->getJson('/api/v1/agenda')->assertJsonPath('data.proximos.0.responsavel', 'Prof. Carlos');
        $this->withToken($token)->getJson("/api/v1/agenda/{$evento->id_evento_calendario}")->assertJsonPath('data.responsavel', 'Prof. Carlos');

        // Trocar o responsável: histórico, sem aviso aos atletas e sem o selo "Alterado"
        $avisosAntes = DB::table('tbl_notificacao')->count();
        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $evento->id_evento_calendario), $this->dados([
            'id_categoria' => $sub13, 'responsavel_evento_calendario' => 'Prof. Bruna',
        ]))->assertSessionHasNoErrors();

        $this->assertSame($avisosAntes, DB::table('tbl_notificacao')->count());
        $historico = $evento->fresh()->historico()->get();
        $this->assertSame(['Responsável pela atividade: Prof. Carlos → Prof. Bruna'], $historico->pluck('resumo')->all());
        $this->assertFalse($evento->fresh()->foiAlterado());

        // Vazio volta a null (no app também)
        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $evento->id_evento_calendario), $this->dados([
            'id_categoria' => $sub13, 'responsavel_evento_calendario' => '',
        ]))->assertSessionHasNoErrors();
        $this->assertNull($evento->fresh()->responsavel_evento_calendario);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/agenda')->assertJsonPath('data.proximos.0.responsavel', null);
    }

    public function test_tela_do_evento_e_lista_mostram_o_responsavel_e_quem_criou(): void
    {
        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), $this->dados(['responsavel_evento_calendario' => 'Prof. Carlos']));
        $evento = EventoCalendario::firstOrFail();

        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $evento->id_evento_calendario))->assertOk()
            ->assertSeeInOrder(['Responsável pela atividade', 'Prof. Carlos', 'Criado por']);
        $this->comoAdmin()->get(route('admin.calendario.index', ['mes' => '2026-11']))->assertOk()
            ->assertSee('Responsável: Prof. Carlos')
            ->assertSee('data-responsavel-atividade="Prof. Carlos"', false)
            ->assertSee('<option value="Prof. Carlos">', false); // sugestão do campo
    }

    public function test_responsavel_com_mais_de_100_caracteres_e_recusado(): void
    {
        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), $this->dados(['responsavel_evento_calendario' => str_repeat('a', 101)]))
            ->assertSessionHasErrors(['responsavel_evento_calendario' => 'O responsável pela atividade pode ter no máximo 100 caracteres.']);

        $this->comoAdmin()->post(route('admin.calendario.grade.store'), $this->dadosGrade(['responsavel_grade_treino' => str_repeat('a', 101)]))
            ->assertSessionHasErrors(['responsavel_grade_treino' => 'O responsável pelo treino pode ter no máximo 100 caracteres.']);
        $this->assertSame(0, EventoCalendario::count() + GradeTreino::count());
    }

    public function test_grade_grava_o_responsavel_e_a_geracao_do_mes_copia_para_os_treinos(): void
    {
        $this->comoAdmin()->post(route('admin.calendario.grade.store'), $this->dadosGrade([
            'dias_semana_grade_treino' => ['terca', 'quinta'], 'responsavel_grade_treino' => 'Prof. Carlos',
        ]))->assertSessionHasNoErrors();
        $this->assertSame(['Prof. Carlos', 'Prof. Carlos'], GradeTreino::pluck('responsavel_grade_treino')->all());

        $this->comoAdmin()->post(route('admin.calendario.grade.gerar'), ['mes' => '2026-11'])->assertSessionHas('sucesso');

        // Terças e quintas de 17/11 em diante: 17, 19, 24 e 26
        $gerados = EventoCalendario::whereNotNull('id_grade_treino')->pluck('responsavel_evento_calendario');
        $this->assertSame(['Prof. Carlos', 'Prof. Carlos', 'Prof. Carlos', 'Prof. Carlos'], $gerados->all());

        // Mudar o responsável da linha não muda os já gerados: só avisa
        $linha = GradeTreino::where('dia_semana_grade_treino', 'terca')->firstOrFail();
        $this->comoAdmin()->put(route('admin.calendario.grade.update', $linha->id_grade_treino), $this->dadosGrade([
            'dia_semana_grade_treino' => 'terca', 'responsavel_grade_treino' => 'Prof. Bruna',
        ]))->assertSessionHas('sucesso', fn ($mensagem) => str_contains($mensagem, '2 evento(s) futuro(s) já gerado(s)'));
        $this->assertSame('Prof. Bruna', $linha->fresh()->responsavel_grade_treino);
        $this->assertSame(0, EventoCalendario::where('responsavel_evento_calendario', 'Prof. Bruna')->count());

        // A tela da grade e o Editar mostram o nome
        $this->comoAdmin()->get(route('admin.calendario.index', ['tab' => 'grade']))->assertOk()
            ->assertSee('data-responsavel="Prof. Bruna"', false)
            ->assertSee('name="responsavel_grade_treino"', false);
    }

    // ---------- apoio ----------

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
