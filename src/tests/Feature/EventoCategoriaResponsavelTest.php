<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Evento com categoria (nullable: vazio em evento individual) e responsável
 * (quem criou; gravado só na criação, nunca sobrescrito). Fase 4, Etapa 2.
 */
class EventoCategoriaResponsavelTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    public function test_responsavel_e_quem_criou(): void
    {
        $criador = User::factory()->admin()->create(['nome_usuario' => 'Criador Teste']);

        $this->actingAs($criador, 'admin')
            ->post(route('admin.calendario.eventos.store'), $this->dados())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_evento_calendario', [
            'titulo_evento_calendario' => 'Evento de Teste', 'id_usuario' => $criador->id_usuario,
        ]);
    }

    public function test_criacao_ignora_id_usuario_enviado_no_formulario(): void
    {
        $criador = User::factory()->admin()->create();
        $outro   = User::factory()->admin()->create();

        $this->actingAs($criador, 'admin')
            ->post(route('admin.calendario.eventos.store'), $this->dados(['id_usuario' => $outro->id_usuario]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_evento_calendario', ['id_usuario' => $criador->id_usuario]);
    }

    public function test_edicao_por_outro_admin_nao_troca_o_responsavel(): void
    {
        $criador = User::factory()->admin()->create();
        $editor  = User::factory()->admin()->create();

        $this->actingAs($criador, 'admin')->post(route('admin.calendario.eventos.store'), $this->dados());
        $id = DB::table('tbl_evento_calendario')->value('id_evento_calendario');

        $this->actingAs($editor, 'admin')
            ->put(route('admin.calendario.eventos.update', $id), $this->dados([
                'titulo_evento_calendario' => 'Editado por Outro',
                'id_usuario'               => $editor->id_usuario,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_evento_calendario', [
            'id_evento_calendario' => $id, 'titulo_evento_calendario' => 'Editado por Outro', 'id_usuario' => $criador->id_usuario,
        ]);
    }

    public function test_evento_com_e_sem_categoria(): void
    {
        $idSub13F = $this->idCategoria('Sub-13', 'F');

        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), $this->dados([
            'titulo_evento_calendario' => 'Treino Sub-13 F', 'id_categoria' => $idSub13F,
        ]))->assertSessionHasNoErrors();

        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), $this->dados([
            'titulo_evento_calendario' => 'Exame Individual', 'tipo_evento_calendario' => 'AVALIACAO', 'id_categoria' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_evento_calendario', ['titulo_evento_calendario' => 'Treino Sub-13 F', 'id_categoria' => $idSub13F]);
        $this->assertDatabaseHas('tbl_evento_calendario', ['titulo_evento_calendario' => 'Exame Individual', 'id_categoria' => null]);
    }

    public function test_categoria_inativa_e_recusada_na_criacao(): void
    {
        $idSub9F = $this->idCategoria('Sub-9', 'F');
        DB::table('tbl_categoria')->where('id_categoria', $idSub9F)->update(['status_categoria' => 'INATIVO']);

        $this->comoAdmin()
            ->post(route('admin.calendario.eventos.store'), $this->dados(['id_categoria' => $idSub9F]))
            ->assertSessionHasErrors('id_categoria');

        $this->assertDatabaseCount('tbl_evento_calendario', 0);
    }

    public function test_edicao_aceita_a_categoria_atual_mesmo_inativada_depois(): void
    {
        $idSub9F = $this->idCategoria('Sub-9', 'F');
        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), $this->dados(['id_categoria' => $idSub9F]));
        $id = DB::table('tbl_evento_calendario')->value('id_evento_calendario');

        DB::table('tbl_categoria')->where('id_categoria', $idSub9F)->update(['status_categoria' => 'INATIVO']);

        $this->comoAdmin()
            ->put(route('admin.calendario.eventos.update', $id), $this->dados(['id_categoria' => $idSub9F, 'titulo_evento_calendario' => 'Outro Título']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tbl_evento_calendario', ['id_evento_calendario' => $id, 'id_categoria' => $idSub9F]);
    }

    public function test_edicao_oferece_a_categoria_atual_inativa_marcada_como_inativa(): void
    {
        $idSub9F  = $this->idCategoria('Sub-9', 'F');   // inativada e usada por um evento
        $idSub11F = $this->idCategoria('Sub-11', 'F');  // inativada e sem evento: não deve aparecer
        $this->comoAdmin()->post(route('admin.calendario.eventos.store'), $this->dados(['id_categoria' => $idSub9F]));
        DB::table('tbl_categoria')->whereIn('id_categoria', [$idSub9F, $idSub11F])->update(['status_categoria' => 'INATIVO']);

        $resposta = $this->comoAdmin()->get(route('admin.calendario.index'))->assertOk();

        // O modal de edição recebe a opção (o JS a mostra e seleciona só para o evento dela)
        $resposta->assertSee('<option value="' . $idSub9F . '" class="js-categoria-inativa" hidden disabled>Sub-9 Feminino (inativa)</option>', false)
            ->assertSee('data-id-categoria="' . $idSub9F . '"', false)
            ->assertDontSee('Sub-11 Feminino (inativa)');

        // Só no select da edição: o cadastro não oferece categoria inativa
        $this->assertSame(1, substr_count($resposta->getContent(), 'Sub-9 Feminino (inativa)'));
    }

    public function test_lista_do_admin_mostra_categoria_e_responsavel(): void
    {
        $criador = User::factory()->admin()->create(['nome_usuario' => 'Fulana Criadora']);
        $this->actingAs($criador, 'admin')->post(route('admin.calendario.eventos.store'), $this->dados([
            'id_categoria' => $this->idCategoria('Sub-15', 'M'),
        ]));

        // Evento antigo, sem responsável
        DB::table('tbl_evento_calendario')->insert([
            'titulo_evento_calendario' => 'Evento Antigo', 'tipo_evento_calendario' => 'JOGO',
            'data_evento_calendario' => '2026-06-21', 'status_evento_calendario' => 'ATIVO',
        ]);

        $this->comoAdmin()
            ->get(route('admin.calendario.index'))
            ->assertOk()
            ->assertSee('Sub-15 Masculino · Responsável: Fulana Criadora')
            ->assertSee('Sem categoria · Responsável: —');
    }

    public function test_tipos_das_colunas_novas(): void
    {
        $colunas = collect(Schema::getColumns('tbl_evento_calendario'))->keyBy('name');

        $this->assertSame('int', $colunas['id_categoria']['type']);                // como tbl_categoria.id_categoria
        $this->assertSame('bigint unsigned', $colunas['id_usuario']['type']);      // como tbl_usuarios.id_usuario
        $this->assertTrue($colunas['id_categoria']['nullable']);
        $this->assertTrue($colunas['id_usuario']['nullable']);

        $fks = collect(Schema::getForeignKeys('tbl_evento_calendario'))->pluck('name');
        $this->assertContains('fk_evento_categoria', $fks);
        $this->assertContains('fk_evento_usuario', $fks);
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'titulo_evento_calendario'         => 'Evento de Teste',
            'tipo_evento_calendario'           => 'JOGO',
            'data_evento_calendario'           => now()->addWeek()->toDateString(),
            'horario_inicio_evento_calendario' => '09:00',
            'local_evento_calendario'          => 'Campo A',
        ], $extra);
    }
}
