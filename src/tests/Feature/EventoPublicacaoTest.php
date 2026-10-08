<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\Notificacao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Rascunho e publicação do evento (Fase 10, Etapa 4, Etapa A): data_publicacao_evento_calendario NULL =
 * rascunho. Todo caminho de criação nasce publicado; rascunho não avisa ninguém (inscrição, remoção,
 * alteração, cancelamento, reativação); publicar() grava a data e o histórico uma vez só.
 * A migration (eventos existentes ficam publicados) está em EventoPublicacaoMigrationTest.
 */
class EventoPublicacaoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    // ---------- coluna e criação ----------

    public function test_coluna_datetime_nullable_com_default_current_timestamp(): void
    {
        $coluna = DB::selectOne("SELECT DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_evento_calendario'
            AND COLUMN_NAME = 'data_publicacao_evento_calendario'");

        $this->assertSame(['datetime', 'YES', 'CURRENT_TIMESTAMP'], [$coluna->DATA_TYPE, $coluna->IS_NULLABLE, $coluna->COLUMN_DEFAULT]);
    }

    public function test_evento_nasce_publicado_por_qualquer_caminho(): void
    {
        $peloAdmin = $this->criarEvento();
        $this->assertTrue($peloAdmin->estaPublicado()); // já na memória, sem reler do banco
        $this->assertTrue($peloAdmin->fresh()->estaPublicado());

        $peloCreate = EventoCalendario::create($this->dadosEvento());
        $this->assertTrue($peloCreate->estaPublicado());

        // Insert direto (sem o model): o DEFAULT do banco publica
        $id = DB::table('tbl_evento_calendario')->insertGetId($this->dadosEvento());
        $this->assertTrue(EventoCalendario::find($id)->estaPublicado());
    }

    public function test_publicacao_fica_fora_do_fillable(): void
    {
        $evento = $this->rascunho();

        $evento->update(['data_publicacao_evento_calendario' => now(), 'titulo_evento_calendario' => 'Outro']);

        $this->assertFalse($evento->fresh()->estaPublicado());
        $this->assertSame('Outro', $evento->fresh()->titulo_evento_calendario);
    }

    public function test_escopo_publicados_deixa_o_rascunho_de_fora(): void
    {
        $publicado = $this->criarEvento();
        $this->rascunho();

        $this->assertSame([$publicado->id_evento_calendario],
            EventoCalendario::publicados()->pluck('id_evento_calendario')->all());
    }

    // ---------- rascunho não avisa ----------

    public function test_rascunho_nao_avisa_inscricao_nem_remocao(): void
    {
        $evento = $this->rascunho();
        $ana = $this->atleta('Ana');

        $this->assertTrue($evento->inscrever($ana, 'INDIVIDUAL', null));
        $this->assertTrue($evento->removerInscricao($ana));

        $this->assertSame(0, Notificacao::count());
        $this->assertSame(0, $evento->atletasNotificados);
    }

    public function test_rascunho_nao_avisa_inscricao_pela_tela_do_evento(): void
    {
        $evento = $this->rascunho();
        $ana = $this->atleta('Ana');

        $this->comoAdmin()->post(route('admin.calendario.eventos.inscricoes.store', $evento->id_evento_calendario), ['id_atleta' => $ana])
            ->assertSessionHas('sucesso', fn ($msg) => str_contains($msg, '0 atleta(s) notificado(s).'));

        $this->assertSame(1, $evento->inscricoes()->count());
        $this->assertSame(0, Notificacao::count());
    }

    public function test_rascunho_nao_avisa_alteracao(): void
    {
        $evento = $this->rascunhoComInscrita();

        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $evento->id_evento_calendario), [
            'titulo_evento_calendario'         => $evento->titulo_evento_calendario,
            'tipo_evento_calendario'           => $evento->tipo_evento_calendario,
            'data_evento_calendario'           => '2099-05-06',
            'horario_inicio_evento_calendario' => '09:00',
            'horario_fim_evento_calendario'    => '10:30',
            'local_evento_calendario'          => 'Campo B',
        ])->assertSessionHas('sucesso', 'Evento atualizado. 0 atleta(s) notificado(s).');

        $this->assertSame('Campo B', $evento->fresh()->local_evento_calendario);
        $this->assertSame(0, Notificacao::count());
    }

    public function test_rascunho_nao_avisa_cancelamento_reativacao_ocultar_nem_mostrar(): void
    {
        $evento = $this->rascunhoComInscrita();

        $evento->mudarStatus('CANCELADO', null);
        $evento->mudarStatus('ATIVO', null);
        $evento->mudarStatus('INATIVO', null);
        $evento->mudarStatus('ATIVO', null);

        $this->assertSame('ATIVO', $evento->fresh()->status_evento_calendario);
        $this->assertSame(0, Notificacao::count());
    }

    public function test_evento_publicado_continua_avisando(): void
    {
        $evento = $this->criarEvento();
        $evento->inscrever($this->atleta('Ana'), 'INDIVIDUAL', null);
        $evento->mudarStatus('CANCELADO', null);

        $this->assertSame(['INSCRICAO', 'CANCELAMENTO'], Notificacao::orderBy('id_notificacao')->pluck('tipo_notificacao')->all());
    }

    // ---------- publicar ----------

    public function test_publicar_grava_a_data_e_o_historico_uma_vez_so(): void
    {
        $this->travelTo('2099-05-01 14:30:00');
        $evento = $this->rascunho();

        $this->assertTrue($evento->publicar($this->admin->id_usuario));

        $this->assertTrue($evento->estaPublicado());
        $this->assertSame('2099-05-01 14:30:00', $evento->fresh()->data_publicacao_evento_calendario->format('Y-m-d H:i:s'));

        $historico = $evento->historico()->sole();
        $this->assertSame('data_publicacao_evento_calendario', $historico->campo_evento_historico);
        $this->assertNull($historico->valor_antigo_evento_historico);
        $this->assertSame('2099-05-01 14:30', $historico->valor_novo_evento_historico);
        $this->assertSame($this->admin->id_usuario, $historico->id_usuario);
        $this->assertSame('Publicação: Rascunho → Publicado em 01/05/2099 14:30', $historico->resumo);

        // De novo (ou por outro admin, com o model desatualizado): não muda nada
        $this->travelTo('2099-05-01 15:00:00');
        $this->assertFalse($evento->publicar($this->admin->id_usuario));
        $this->assertFalse(EventoCalendario::find($evento->id_evento_calendario)->publicar(null));
        $this->assertSame(1, $evento->historico()->count());
        $this->assertSame('2099-05-01 14:30:00', $evento->fresh()->data_publicacao_evento_calendario->format('Y-m-d H:i:s'));
    }

    public function test_publicar_evento_ja_publicado_nao_faz_nada(): void
    {
        $evento = $this->criarEvento();

        $this->assertFalse($evento->publicar(null));
        $this->assertSame(0, $evento->historico()->count());
    }

    public function test_publicacao_no_historico_nao_deixa_o_evento_alterado(): void
    {
        $evento = $this->rascunho();
        $evento->publicar(null);

        $this->assertFalse($evento->fresh()->foiAlterado());
        $this->assertSame('ATIVO', EventoCalendario::comAlteracao()->find($evento->id_evento_calendario)->situacao);
    }

    // ---------- helpers ----------

    private function comoAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    // Treino em 05/05/2099 (ter), 09:00 às 10:30, Campo A
    private function dadosEvento(): array
    {
        return [
            'titulo_evento_calendario'         => 'Treino',
            'tipo_evento_calendario'           => 'TREINO',
            'data_evento_calendario'           => '2099-05-05',
            'horario_inicio_evento_calendario' => '09:00:00',
            'horario_fim_evento_calendario'    => '10:30:00',
            'local_evento_calendario'          => 'Campo A',
            'status_evento_calendario'         => 'ATIVO',
        ];
    }

    private function criarEvento(): EventoCalendario
    {
        return EventoCalendario::criarPor(null, $this->dadosEvento());
    }

    // O mesmo treino, em rascunho: a data de publicação gravada NULL de propósito (o booted() respeita)
    private function rascunho(): EventoCalendario
    {
        $evento = new EventoCalendario($this->dadosEvento());
        $evento->data_publicacao_evento_calendario = null;
        $evento->save();

        $this->assertFalse($evento->fresh()->estaPublicado());

        return $evento;
    }

    private function rascunhoComInscrita(): EventoCalendario
    {
        $evento = $this->rascunho();
        $evento->inscrever($this->atleta('Ana'), 'INDIVIDUAL', null);

        return $evento;
    }

    private function atleta(string $nome): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
        $this->colocarNaCategoria($id, $this->idCategoria('Sub-13', 'M'));

        return $id;
    }
}
