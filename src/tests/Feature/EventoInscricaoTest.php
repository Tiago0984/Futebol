<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Inscrição de atletas em eventos (Fase 5, Etapa 1): pela categoria do evento, individual
 * e "adicionar todos de uma categoria". Sem status: remover apaga a linha.
 */
class EventoInscricaoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    // ---------- pela categoria, ao criar ----------

    public function test_evento_com_categoria_nasce_com_os_atletas_ativos_dela(): void
    {
        $idSub13M = $this->idCategoria('Sub-13', 'M');
        $ativo1   = $this->atletaNaCategoria('Ana Ativa', 'Sub-13', 'M');
        $ativo2   = $this->atletaNaCategoria('Bruno Ativo', 'Sub-13', 'M');
        $this->atletaNaCategoria('Inativo da Sub-13', 'Sub-13', 'M', 'INATIVO');
        $this->atletaNaCategoria('Atleta da Sub-15', 'Sub-15', 'M');

        // Saiu da Sub-13 (linha ENCERRADO): não entra
        $saiu = $this->criarAtleta($this->nascidoComIdade(12), 'M', 'ATIVO');
        DB::table('tbl_categoria_atleta')->insert([
            'id_categoria' => $idSub13M, 'id_atleta' => $saiu, 'data_inicio_categoria_atleta' => now()->subYear(),
            'data_fim_categoria_atleta' => now()->subMonth(), 'status_categoria_atleta' => 'ENCERRADO',
        ]);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.calendario.eventos.store'), $this->dadosEvento(['id_categoria' => $idSub13M]))
            ->assertSessionHas('sucesso', 'Evento adicionado ao calendário. 2 atleta(s) da categoria inscrito(s). 2 atleta(s) notificado(s).');

        $inscritos = DB::table('tbl_evento_atleta')->orderBy('id_atleta')->get();
        $this->assertSame([$ativo1, $ativo2], $inscritos->pluck('id_atleta')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(['CATEGORIA'], $inscritos->pluck('origem_evento_atleta')->unique()->values()->all());
        $this->assertSame($admin->id_usuario, (int) $inscritos->first()->id_usuario);
        $this->assertNull($inscritos->first()->id_time);
    }

    public function test_evento_sem_categoria_nasce_sem_inscritos(): void
    {
        $this->atletaNaCategoria('Ana', 'Sub-13', 'M');

        $this->comoAdmin()
            ->post(route('admin.calendario.eventos.store'), $this->dadosEvento())
            ->assertSessionHas('sucesso', 'Evento adicionado ao calendário.');

        $this->assertSame(0, DB::table('tbl_evento_atleta')->count());
    }

    // ---------- individual ----------

    public function test_inscricao_individual_e_repetida_sem_erro(): void
    {
        $evento = $this->criarEvento();
        $atleta = $this->atletaNaCategoria('Carla', 'Sub-15', 'F');

        $this->comoAdmin()
            ->post(route('admin.calendario.eventos.inscricoes.store', $evento->id_evento_calendario), ['id_atleta' => $atleta])
            ->assertSessionHas('sucesso', 'Atleta inscrito. 1 atleta(s) notificado(s).');

        $this->comoAdmin()
            ->post(route('admin.calendario.eventos.inscricoes.store', $evento->id_evento_calendario), ['id_atleta' => $atleta])
            ->assertSessionHas('sucesso', 'O atleta já estava inscrito.');

        $this->assertDatabaseHas('tbl_evento_atleta', ['id_atleta' => $atleta, 'origem_evento_atleta' => 'INDIVIDUAL']);
        $this->assertSame(1, DB::table('tbl_evento_atleta')->count());
    }

    public function test_inscricao_individual_recusa_atleta_inativo(): void
    {
        $evento  = $this->criarEvento();
        $inativo = $this->atletaNaCategoria('Inativo', 'Sub-15', 'M', 'INATIVO');

        $this->comoAdmin()
            ->post(route('admin.calendario.eventos.inscricoes.store', $evento->id_evento_calendario), ['id_atleta' => $inativo])
            ->assertSessionHasErrors('id_atleta');

        $this->assertSame(0, DB::table('tbl_evento_atleta')->count());
    }

    public function test_adicionar_todos_de_uma_categoria_varias_vezes(): void
    {
        $evento = $this->criarEvento(['tipo_evento_calendario' => 'AVALIACAO']);
        $this->atletaNaCategoria('Ana', 'Sub-13', 'M');
        $this->atletaNaCategoria('Bia', 'Sub-13', 'M');
        $this->atletaNaCategoria('Caio', 'Sub-15', 'M');
        $rota = route('admin.calendario.eventos.inscricoes.categoria', $evento->id_evento_calendario);

        $this->comoAdmin()->post($rota, ['id_categoria' => $this->idCategoria('Sub-13', 'M')])
            ->assertSessionHas('sucesso', 'Sub-13 Masculino: 2 atleta(s) inscrito(s). 2 atleta(s) notificado(s).');
        $this->comoAdmin()->post($rota, ['id_categoria' => $this->idCategoria('Sub-15', 'M')])
            ->assertSessionHas('sucesso', 'Sub-15 Masculino: 1 atleta(s) inscrito(s). 1 atleta(s) notificado(s).');

        // De novo: ninguém novo, sem erro
        $this->comoAdmin()->post($rota, ['id_categoria' => $this->idCategoria('Sub-13', 'M')])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('sucesso', 'Sub-13 Masculino: 0 atleta(s) inscrito(s) (todos já estavam inscritos ou não há atletas ativos).');

        $this->assertSame(3, DB::table('tbl_evento_atleta')->count());
        // Escolha do admin: origem INDIVIDUAL (a sincronização pela categoria do evento não mexe nelas)
        $this->assertSame(['INDIVIDUAL'], DB::table('tbl_evento_atleta')->distinct()->pluck('origem_evento_atleta')->all());
    }

    public function test_remover_inscricao_apaga_a_linha(): void
    {
        $evento = $this->criarEvento();
        $atleta = $this->atletaNaCategoria('Duda', 'Sub-15', 'F');
        $evento->inscrever($atleta, 'INDIVIDUAL', null);

        $this->comoAdmin()
            ->delete(route('admin.calendario.eventos.inscricoes.destroy', [$evento->id_evento_calendario, $atleta]))
            ->assertSessionHas('sucesso', 'Inscrição removida. 1 atleta(s) notificado(s).');

        $this->assertSame(0, DB::table('tbl_evento_atleta')->count());
    }

    // ---------- tela ----------

    public function test_tela_do_evento_lista_so_inscritos_ativos(): void
    {
        $evento  = $this->criarEvento();
        $ativo   = $this->atletaNaCategoria('Elisa Ativa', 'Sub-15', 'F');
        $inativo = $this->atletaNaCategoria('Fabio Inativo', 'Sub-15', 'M');
        $livre   = $this->atletaNaCategoria('Gabi Disponivel', 'Sub-13', 'F');
        $evento->inscrever($ativo, 'INDIVIDUAL', null);
        $evento->inscrever($inativo, 'INDIVIDUAL', null);
        DB::table('tbl_atletas')->where('id_atleta', $inativo)->update(['status_atleta' => 'INATIVO']);

        $resposta = $this->comoAdmin()
            ->get(route('admin.calendario.eventos.show', $evento->id_evento_calendario))
            ->assertOk()
            ->assertSee('Inscritos (1)')
            ->assertSee('Elisa Ativa')
            ->assertDontSee('Fabio Inativo')
            ->assertSee('1 inscrito(s) com atleta inativo');

        // Select de inscrição: só quem ainda não está inscrito, agrupado pela categoria
        $resposta->assertSee('<optgroup label="Sub-13 Feminino">', false)
            ->assertSee('<option value="' . $livre . '">Gabi Disponivel</option>', false)
            ->assertDontSee('<option value="' . $ativo . '">', false);
    }

    public function test_lista_do_calendario_mostra_quantos_inscritos(): void
    {
        $evento = $this->criarEvento();
        $evento->inscrever($this->atletaNaCategoria('Hugo', 'Sub-15', 'M'), 'INDIVIDUAL', null);
        $evento->inscrever($this->atletaNaCategoria('Iris', 'Sub-15', 'F'), 'INDIVIDUAL', null);

        $this->comoAdmin()
            ->get(route('admin.calendario.index'))
            ->assertOk()
            ->assertSee('title="Inscritos (2)"', false)
            ->assertSee(route('admin.calendario.eventos.show', $evento->id_evento_calendario), false);
    }

    // ---------- schema ----------

    public function test_banco_impede_inscrever_o_mesmo_atleta_duas_vezes(): void
    {
        $evento = $this->criarEvento();
        $atleta = $this->atletaNaCategoria('Joao', 'Sub-15', 'M');
        $linha  = ['id_evento_calendario' => $evento->id_evento_calendario, 'id_atleta' => $atleta, 'origem_evento_atleta' => 'INDIVIDUAL'];

        DB::table('tbl_evento_atleta')->insert($linha);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('tbl_evento_atleta')->insert($linha);
    }

    public function test_tipos_das_colunas_e_fks(): void
    {
        $colunas = collect(Schema::getColumns('tbl_evento_atleta'))->keyBy('name');

        $this->assertSame('int unsigned', $colunas['id_evento_calendario']['type']);
        $this->assertSame('int', $colunas['id_atleta']['type']);
        $this->assertSame('int', $colunas['id_time']['type']);
        $this->assertTrue($colunas['id_time']['nullable']);
        $this->assertSame('bigint unsigned', $colunas['id_usuario']['type']);

        $fks = collect(Schema::getForeignKeys('tbl_evento_atleta'))->pluck('name')->sort()->values()->all();
        $this->assertSame(['fk_evento_atleta_atleta', 'fk_evento_atleta_evento', 'fk_evento_atleta_time', 'fk_evento_atleta_usuario'], $fks);
    }

    // ---------- helpers ----------

    private function atletaNaCategoria(string $nome, string $categoria, string $sexo, string $status = 'ATIVO'): int
    {
        $idade = ['Sub-9' => 9, 'Sub-11' => 11, 'Sub-13' => 13, 'Sub-15' => 15, 'Sub-17' => 17][$categoria];
        $id    = $this->criarAtleta($this->nascidoComIdade($idade), $sexo, $status);
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
        $this->colocarNaCategoria($id, $this->idCategoria($categoria, $sexo));

        return $id;
    }

    private function criarEvento(array $extra = []): EventoCalendario
    {
        return EventoCalendario::criarPor(null, [...$this->dadosEvento($extra), 'status_evento_calendario' => 'ATIVO']);
    }

    private function dadosEvento(array $extra = []): array
    {
        return array_merge([
            'titulo_evento_calendario'         => 'Evento de Teste',
            'tipo_evento_calendario'           => 'TREINO',
            'data_evento_calendario'           => now()->addWeek()->toDateString(),
            'horario_inicio_evento_calendario' => '09:00',
            'horario_fim_evento_calendario'    => '10:30',
            'local_evento_calendario'          => 'Campo A',
        ], $extra);
    }
}
