<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\EventoHistorico;
use App\Models\GradeTreino;
use App\Models\Local;
use App\Models\Notificacao;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Etapa L2 (30/10/2026): o select "Local" do cadastro de locais nos formulários (evento, grade, jogo, campeonato),
 * com o texto antigo e o local inativo mantidos, o "Novo local" em JSON, o nome copiado no texto, a renomeação levada
 * a todos os usos e o "Ligar os usos" de um texto antigo (sem aviso aos atletas).
 */
class LocaisNosFormulariosTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private int $campo;
    private int $arena;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campo = $this->local('Campo AACJ');
        $this->arena = $this->local('Arena Norte');
    }

    private function local(string $nome, string $status = 'ATIVO'): int
    {
        return DB::table('tbl_local')->insertGetId([
            'nome_local' => $nome, 'rua_local' => 'Rua A', 'numero_local' => '10', 'bairro_local' => 'Centro',
            'cidade_local' => 'São Carlos', 'estado_local' => 'SP', 'status_local' => $status,
        ]);
    }

    private function dadosEvento(array $extra = []): array
    {
        return array_merge([
            'titulo_evento_calendario' => 'Reunião de pais', 'tipo_evento_calendario' => 'REUNIAO',
            'data_evento_calendario' => now()->addDays(10)->toDateString(), 'horario_inicio_evento_calendario' => '19:00',
        ], $extra);
    }

    public function test_evento_grava_o_local_escolhido_com_o_nome_no_texto(): void
    {
        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), $this->dadosEvento(['id_local' => (string) $this->campo]))
            ->assertSessionHasNoErrors();

        $evento = EventoCalendario::sole();
        $this->assertSame($this->campo, $evento->id_local);
        $this->assertSame('Campo AACJ', $evento->local_evento_calendario);

        // Sem local
        $this->post(route('admin.calendario.eventos.update', $evento->id_evento_calendario), $this->dadosEvento(['_method' => 'PUT', 'id_local' => '']))
            ->assertSessionHasNoErrors();
        $this->assertNull($evento->fresh()->id_local);
        $this->assertNull($evento->fresh()->local_evento_calendario);
    }

    public function test_texto_antigo_e_local_inativo_ficam_ate_alguem_escolher_da_lista(): void
    {
        $evento = EventoCalendario::criarPor(null, $this->dadosEvento(['local_evento_calendario' => 'Quadra A']));

        // A tela do evento mostra o texto antigo como opção, já escolhida
        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $evento->id_evento_calendario))
            ->assertOk()->assertSee('Quadra A (texto antigo)')->assertSee('>Campo AACJ</option>', false);

        $editar = fn (string $valor) => $this->post(route('admin.calendario.eventos.update', $evento->id_evento_calendario),
            $this->dadosEvento(['_method' => 'PUT', 'id_local' => $valor]));

        $editar(Local::TEXTO_ANTIGO)->assertSessionHasNoErrors();
        $this->assertNull($evento->fresh()->id_local);
        $this->assertSame('Quadra A', $evento->fresh()->local_evento_calendario);

        // Escolher da lista troca o texto pelo nome do local
        $editar((string) $this->arena)->assertSessionHasNoErrors();
        $this->assertSame([$this->arena, 'Arena Norte'], [$evento->fresh()->id_local, $evento->fresh()->local_evento_calendario]);

        // Desativado: continua no evento em que já está, mas não é escolhido para outro
        DB::table('tbl_local')->where('id_local', $this->arena)->update(['status_local' => 'INATIVO']);
        $this->get(route('admin.calendario.eventos.show', $evento->id_evento_calendario))->assertSee('Arena Norte (inativo)');
        $editar((string) $this->arena)->assertSessionHasNoErrors();
        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), $this->dadosEvento(['id_local' => (string) $this->arena]))
            ->assertSessionHasErrors(['id_local' => 'Escolha um local ativo da lista.']);
        $editar('Quadra B')->assertSessionHasErrors(['id_local' => 'Escolha um local da lista.']);
    }

    public function test_grade_aceita_sem_local_e_a_geracao_copia_o_local(): void
    {
        $dados = [
            'dias_semana_grade_treino' => ['terca'], 'tipo_grade_treino' => 'TREINO', 'categoria_grade_treino' => 'Integrado',
            'horario_inicio_grade_treino' => '14:00', 'horario_fim_grade_treino' => '17:00',
        ];
        $this->comoAdmin()->post(route('admin.calendario.grade.store'), [...$dados, 'id_local' => ''])->assertSessionHasNoErrors();
        $this->assertNull(GradeTreino::sole()->local_grade_treino);

        $this->post(route('admin.calendario.grade.store'), [...$dados, 'id_local' => (string) $this->campo])->assertSessionHasNoErrors();
        $grade = GradeTreino::where('id_local', $this->campo)->sole();
        $this->assertSame('Campo AACJ', $grade->local_grade_treino);

        $doEvento = $grade->dadosEventoPara(now()->next('Tuesday'));
        $this->assertSame([$this->campo, 'Campo AACJ'], [$doEvento['id_local'], $doEvento['local_evento_calendario']]);
    }

    public function test_novo_local_pelo_formulario_responde_em_json(): void
    {
        $this->comoAdmin()->postJson(route('admin.locais.rapido'), [
            'nome_local' => 'Quadra do Sesc', 'rua_local' => 'Av. B', 'numero_local' => 'S/N', 'bairro_local' => 'Centro',
            'cidade_local' => 'São Carlos', 'estado_local' => 'sp',
        ])->assertCreated()
            ->assertJsonPath('nome', 'Quadra do Sesc')
            ->assertJsonPath('endereco', 'Av. B, S/N – Centro, São Carlos/SP');

        $this->postJson(route('admin.locais.rapido'), ['nome_local' => 'campo aacj'])
            ->assertStatus(422)
            ->assertJsonPath('errors.nome_local.0', 'Já existe um local com este nome.')
            ->assertJsonValidationErrors(['rua_local', 'numero_local', 'bairro_local', 'cidade_local', 'estado_local']);
    }

    public function test_renomear_leva_o_nome_novo_para_todos_os_usos_sem_aviso_nem_historico(): void
    {
        $evento = EventoCalendario::criarPor(null, $this->dadosEvento(['id_local' => $this->campo, 'local_evento_calendario' => 'Campo AACJ']));
        $campeonato = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idCategoria('Sub-13', 'M'), 'nome_campeonato' => 'Copa', 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'local_evento' => 'Campo AACJ', 'id_local' => $this->campo,
        ]);

        Local::find($this->campo)->update(['nome_local' => 'Campo da AACJ']);

        $this->assertSame('Campo da AACJ', $evento->fresh()->local_evento_calendario);
        $this->assertSame('Campo da AACJ', DB::table('tbl_campeonato')->where('id_campeonato', $campeonato)->value('local_evento'));
        $this->assertSame(0, EventoHistorico::count());
        $this->assertSame(0, Notificacao::count());
    }

    public function test_ligar_os_usos_de_um_texto_antigo_sem_aviso_e_com_historico(): void
    {
        $evento = EventoCalendario::criarPor(null, $this->dadosEvento(['local_evento_calendario' => 'campo da aacj']));
        $outro  = EventoCalendario::criarPor(null, $this->dadosEvento(['local_evento_calendario' => 'Quadra A']));
        DB::table('tbl_grade_treino')->insert([
            'dia_semana_grade_treino' => 'terca', 'categoria_grade_treino' => 'Integrado', 'tipo_grade_treino' => 'TREINO',
            'local_grade_treino' => 'Campo da AACJ', 'status_grade_treino' => 'ATIVO',
        ]);
        Local::find($this->campo)->update(['nome_local' => 'Campo da AACJ']);

        $this->assertSame(['tbl_evento_calendario' => 1, 'tbl_grade_treino' => 1, 'tbl_campeonato' => 0], Local::usosDoTexto('Campo da AACJ'));
        $this->comoAdmin()->get(route('admin.locais.edit', $this->campo))
            ->assertOk()->assertSee('Ligar os 2 uso(s) de'); // o texto pode vir em outra caixa (os iguais contam juntos)

        $this->post(route('admin.locais.ligarUsos', $this->campo), ['texto' => 'Campo da AACJ'])
            ->assertRedirect(route('admin.locais.index'))
            ->assertSessionHas('sucesso', 'Ligados a Campo da AACJ: 1 evento(s), 1 horário(s) da grade. Ninguém foi avisado (o lugar é o mesmo).');

        $this->assertSame([$this->campo, 'Campo da AACJ'], [$evento->fresh()->id_local, $evento->fresh()->local_evento_calendario]);
        $this->assertNull($outro->fresh()->id_local);
        $this->assertSame($this->campo, (int) DB::table('tbl_grade_treino')->value('id_local'));
        $this->assertSame('Local: campo da aacj → Campo da AACJ', EventoHistorico::sole()->campo_label . ': ' . EventoHistorico::sole()->valor_antigo_evento_historico . ' → ' . EventoHistorico::sole()->valor_novo_evento_historico);
        $this->assertSame(0, Notificacao::count());

        // Ligados, os textos saem da lista de soltos
        $this->assertSame(['Quadra A'], Local::textosSoltos()->pluck('texto')->all());
    }
}
