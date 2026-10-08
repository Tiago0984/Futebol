<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\GradeTreino;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Mudar ou inativar um horário da grade não altera os eventos já gerados: a tela só avisa quantos
 * eventos futuros ativos ele tem (Fase 7). "Hoje" fixo: segunda, 16/11/2026, 10:00.
 */
class GradeAvisoEventosGeradosTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private int $idGrade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-16 10:00:00');

        // Sub-13 M, segunda 08:00–09:30 (os eventos de teste são criados direto, em qualquer dia)
        $this->idGrade = DB::table('tbl_grade_treino')->insertGetId([
            'dia_semana_grade_treino' => 'segunda', 'categoria_grade_treino' => 'Sub-13',
            'id_categoria' => $this->idCategoria('Sub-13', 'M'), 'tipo_grade_treino' => 'TREINO',
            'horario_inicio_grade_treino' => '08:00', 'horario_fim_grade_treino' => '09:30',
            'local_grade_treino' => 'Campo A', 'status_grade_treino' => 'ATIVO',
        ]);
    }

    // Dois futuros ativos (18 e 23/11) e três que não contam: passado, hoje já concluído e cancelado
    private function gerarEventos(): void
    {
        $grade = GradeTreino::find($this->idGrade);
        foreach (['2026-11-11', '2026-11-16', '2026-11-18', '2026-11-23', '2026-11-25'] as $data) {
            EventoCalendario::criarDaGrade($grade, Carbon::parse($data), null);
        }
        EventoCalendario::where('data_grade_evento_calendario', '2026-11-25')->first()->mudarStatus('CANCELADO', null);
    }

    // ---------- edição ----------

    public function test_editar_o_horario_avisa_os_eventos_futuros_e_nao_os_altera(): void
    {
        $this->gerarEventos();

        $this->comoAdmin()->put(route('admin.calendario.grade.update', $this->idGrade), $this->dados(['horario_inicio_grade_treino' => '07:30']))
            ->assertSessionHas('sucesso', 'Horário atualizado. Atenção: 2 evento(s) futuro(s) já gerado(s) por este horário não foram alterados;'
                . ' edite-os na lista de eventos (filtro Origem: Grade).');

        // Os eventos continuam com o horário antigo
        $this->assertSame(5, EventoCalendario::where('horario_inicio_evento_calendario', '08:00:00')->count());
    }

    public function test_cada_campo_copiado_pelo_evento_dispara_o_aviso(): void
    {
        $this->gerarEventos();

        $mudancas = [
            ['dia_semana_grade_treino' => 'terca'],
            ['horario_fim_grade_treino' => '10:00'],
            ['local_grade_treino' => 'Campo B'],
            ['id_categoria' => $this->idCategoria('Sub-15', 'M')],
            ['tipo_grade_treino' => 'LIVRE'],
        ];

        foreach ($mudancas as $mudanca) {
            $this->comoAdmin()->put(route('admin.calendario.grade.update', $this->idGrade), $this->dados($mudanca))
                ->assertSessionHas('sucesso', fn ($msg) => str_contains($msg, '2 evento(s) futuro(s)'));

            // Volta ao original para o próximo caso
            $this->comoAdmin()->put(route('admin.calendario.grade.update', $this->idGrade), $this->dados());
        }
    }

    public function test_trocar_so_o_nome_de_um_item_geral_avisa(): void
    {
        $geral = ['id_categoria' => '', 'categoria_grade_treino' => 'Integrado'];
        $this->comoAdmin()->put(route('admin.calendario.grade.update', $this->idGrade), $this->dados($geral));
        $this->gerarEventos();

        $this->comoAdmin()->put(route('admin.calendario.grade.update', $this->idGrade), $this->dados([...$geral, 'categoria_grade_treino' => 'Integrado Geral']))
            ->assertSessionHas('sucesso', fn ($msg) => str_contains($msg, '2 evento(s) futuro(s)'));
    }

    public function test_editar_so_observacao_ou_ordem_nao_avisa(): void
    {
        $this->gerarEventos();

        // "08:00" no formulário e "08:00:00" no banco são o mesmo horário
        $this->comoAdmin()->put(route('admin.calendario.grade.update', $this->idGrade),
            $this->dados(['horario_obs_grade_treino' => 'Trazer chuteira', 'ordem_grade_treino' => 3]))
            ->assertSessionHas('sucesso', 'Horário atualizado.');
    }

    public function test_editar_sem_eventos_futuros_gerados_mantem_a_mensagem(): void
    {
        // Só um evento passado
        EventoCalendario::criarDaGrade(GradeTreino::find($this->idGrade), Carbon::parse('2026-11-11'), null);

        $this->comoAdmin()->put(route('admin.calendario.grade.update', $this->idGrade), $this->dados(['horario_inicio_grade_treino' => '07:30']))
            ->assertSessionHas('sucesso', 'Horário atualizado.');
    }

    // ---------- inativar ----------

    public function test_inativar_avisa_os_eventos_futuros_que_continuam_na_agenda(): void
    {
        $this->gerarEventos();

        $this->comoAdmin()->patch(route('admin.calendario.grade.toggleStatus', $this->idGrade))
            ->assertSessionHas('sucesso', 'Horário INATIVO com sucesso. Atenção: 2 evento(s) futuro(s) já gerado(s) por este horário continuam na agenda;'
                . ' cancele-os à mão se não forem acontecer.');

        // Nada foi cancelado
        $this->assertSame(4, EventoCalendario::where('status_evento_calendario', 'ATIVO')->count());
    }

    public function test_inativar_sem_eventos_futuros_e_reativar_mantem_a_mensagem(): void
    {
        $this->comoAdmin()->patch(route('admin.calendario.grade.toggleStatus', $this->idGrade))
            ->assertSessionHas('sucesso', 'Horário INATIVO com sucesso.');

        $this->gerarEventos();
        $this->comoAdmin()->patch(route('admin.calendario.grade.toggleStatus', $this->idGrade))
            ->assertSessionHas('sucesso', 'Horário ATIVO com sucesso.');
    }

    // Formulário completo de edição com os valores atuais (como o modal)
    private function dados(array $extra = []): array
    {
        return array_merge([
            'dia_semana_grade_treino'     => 'segunda',
            'id_categoria'                => $this->idCategoria('Sub-13', 'M'),
            'categoria_grade_treino'      => '',
            'tipo_grade_treino'           => 'TREINO',
            'horario_inicio_grade_treino' => '08:00',
            'horario_fim_grade_treino'    => '09:30',
            'horario_obs_grade_treino'    => '',
            'local_grade_treino'          => 'Campo A',
            'ordem_grade_treino'          => 1,
        ], $extra);
    }
}
