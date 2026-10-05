<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\EventoCalendario;
use App\Models\Notificacao;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Notificações do atleta (Fase 8, Etapa 1): tabela própria tbl_notificacao, model e relações.
 * Nenhum disparo nesta etapa: as notificações são criadas à mão nos testes.
 */
class NotificacaoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    // ---------- schema ----------

    public function test_tipos_das_colunas(): void
    {
        $colunas = collect(Schema::getColumns('tbl_notificacao'))->keyBy('name');

        $this->assertSame('int unsigned', $colunas['id_notificacao']['type']);
        $this->assertTrue($colunas['id_notificacao']['auto_increment']);
        $this->assertSame('int', $colunas['id_atleta']['type']);                         // como tbl_atletas.id_atleta
        $this->assertFalse($colunas['id_atleta']['nullable']);
        $this->assertSame('int unsigned', $colunas['id_evento_calendario']['type']);     // como tbl_evento_calendario
        $this->assertTrue($colunas['id_evento_calendario']['nullable']);                 // NULL no resumo (AGENDA)
        $this->assertSame('bigint unsigned', $colunas['id_usuario']['type']);            // como tbl_usuarios.id_usuario
        $this->assertTrue($colunas['id_usuario']['nullable']);

        $this->assertSame(
            "enum('" . implode("','", array_keys(Notificacao::TIPOS)) . "')",
            $colunas['tipo_notificacao']['type'],
        );
        $this->assertSame('varchar(120)', $colunas['titulo_notificacao']['type']);
        $this->assertSame('varchar(500)', $colunas['mensagem_notificacao']['type']);
        $this->assertSame('utf8mb4_general_ci', $colunas['mensagem_notificacao']['collation']);
        $this->assertSame('json', $colunas['dados_notificacao']['type']);
        $this->assertTrue($colunas['dados_notificacao']['nullable']);
        $this->assertSame('datetime', $colunas['data_notificacao']['type']);
        $this->assertFalse($colunas['data_notificacao']['nullable']);
        $this->assertSame('datetime', $colunas['data_leitura_notificacao']['type']);
        $this->assertTrue($colunas['data_leitura_notificacao']['nullable']);
    }

    public function test_fks_sem_cascade_e_indices(): void
    {
        $fks = collect(Schema::getForeignKeys('tbl_notificacao'))->keyBy('name');

        $this->assertSame(['fk_notificacao_atleta', 'fk_notificacao_evento', 'fk_notificacao_usuario'], $fks->keys()->sort()->values()->all());
        foreach ($fks as $fk) {
            $this->assertSame('no action', strtolower($fk['on_delete']), "{$fk['name']} deveria ser NO ACTION");
        }

        $indices = collect(Schema::getIndexes('tbl_notificacao'))->keyBy('name');
        $this->assertSame(['id_atleta', 'data_notificacao'], $indices['idx_notificacao_atleta_data']['columns']);
        $this->assertSame(['id_atleta', 'data_leitura_notificacao'], $indices['idx_notificacao_atleta_leitura']['columns']);
    }

    // ---------- relações ----------

    public function test_relacoes_com_atleta_evento_e_usuario(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        $evento   = $this->criarEvento();
        $admin    = User::factory()->admin()->create();

        $notificacao = $this->notificar($idAtleta, [
            'id_evento_calendario' => $evento->id_evento_calendario,
            'id_usuario'           => $admin->id_usuario,
        ]);

        $this->assertSame($idAtleta, $notificacao->atleta->id_atleta);
        $this->assertSame($evento->id_evento_calendario, $notificacao->evento->id_evento_calendario);
        $this->assertSame($admin->id_usuario, $notificacao->usuario->id_usuario);
        $this->assertSame('Inscrição', $notificacao->tipo_label);
    }

    public function test_resumo_agenda_sem_evento_com_dados_em_json(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');

        $notificacao = $this->notificar($idAtleta, [
            'tipo_notificacao'  => 'AGENDA',
            'dados_notificacao' => ['mes' => '2026-12', 'eventos' => 18],
        ]);

        $lida = Notificacao::find($notificacao->id_notificacao);
        $this->assertNull($lida->evento);
        $this->assertSame(['mes' => '2026-12', 'eventos' => 18], $lida->dados_notificacao);
        $this->assertSame('Agenda', $lida->tipo_label);
    }

    public function test_notificacoes_do_atleta_da_mais_nova_para_a_mais_antiga(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        $outro    = $this->criarAtleta($this->nascidoComIdade(15), 'M', 'ATIVO');

        $antiga = $this->notificar($idAtleta, ['data_notificacao' => '2026-10-01 08:00:00']);
        $nova   = $this->notificar($idAtleta, ['data_notificacao' => '2026-10-03 08:00:00']);
        $meio   = $this->notificar($idAtleta, ['data_notificacao' => '2026-10-02 08:00:00']);
        $this->notificar($outro);

        $this->assertSame(
            [$nova->id_notificacao, $meio->id_notificacao, $antiga->id_notificacao],
            Atleta::find($idAtleta)->notificacoes->pluck('id_notificacao')->all(),
        );
    }

    public function test_data_no_json_com_o_fuso_de_brasilia(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        $notificacao = $this->notificar($idAtleta, ['data_notificacao' => '2099-05-01 19:00:00']);

        $json = Notificacao::find($notificacao->id_notificacao)->toArray();

        $this->assertSame('2099-05-01T19:00:00-03:00', $json['data_notificacao']);
        $this->assertNull($json['data_leitura_notificacao']);
    }

    // ---------- lidas e não lidas ----------

    public function test_nao_lidas_e_marcar_como_lida(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        $primeira = $this->notificar($idAtleta);
        $this->notificar($idAtleta);

        $this->assertSame(2, Atleta::find($idAtleta)->notificacoes()->naoLidas()->count());
        $this->assertFalse($primeira->estaLida());

        $this->travelTo(Carbon::parse('2099-05-01 10:00:00'));
        $this->assertTrue($primeira->marcarComoLida());

        // Já lida: mantém a data da primeira leitura
        $this->travelTo(Carbon::parse('2099-05-02 10:00:00'));
        $this->assertFalse($primeira->fresh()->marcarComoLida());

        $this->assertSame('2099-05-01 10:00:00', $primeira->fresh()->data_leitura_notificacao->format('Y-m-d H:i:s'));
        $this->assertSame(1, Atleta::find($idAtleta)->notificacoes()->naoLidas()->count());
    }

    public function test_marcar_todas_como_lidas_so_mexe_no_atleta_informado(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
        $outro    = $this->criarAtleta($this->nascidoComIdade(15), 'M', 'ATIVO');
        $this->notificar($idAtleta);
        $this->notificar($idAtleta);
        $this->notificar($outro);

        $this->assertSame(2, Notificacao::marcarTodasComoLidas($idAtleta));
        $this->assertSame(0, Notificacao::marcarTodasComoLidas($idAtleta)); // nada mais a marcar

        $this->assertSame(0, Atleta::find($idAtleta)->notificacoes()->naoLidas()->count());
        $this->assertSame(1, Atleta::find($outro)->notificacoes()->naoLidas()->count());
    }

    public function test_data_de_leitura_fora_do_fillable(): void
    {
        $idAtleta = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');

        $notificacao = $this->notificar($idAtleta, ['data_leitura_notificacao' => '2026-10-01 08:00:00']);

        $this->assertFalse($notificacao->fresh()->estaLida());
    }

    // ---------- helpers ----------

    private function notificar(int $idAtleta, array $extra = []): Notificacao
    {
        return Notificacao::create(array_merge([
            'id_atleta'            => $idAtleta,
            'tipo_notificacao'     => 'INSCRICAO',
            'titulo_notificacao'   => 'Nova atividade na sua agenda',
            'mensagem_notificacao' => 'Treino Sub-13 Masculino · ter, 06/10 · 18:00 às 19:30 · Campo A',
        ], $extra));
    }

    private function criarEvento(): EventoCalendario
    {
        return EventoCalendario::criarPor(null, [
            'titulo_evento_calendario' => 'Evento de Teste',
            'tipo_evento_calendario'   => 'TREINO',
            'data_evento_calendario'   => now()->addWeek()->toDateString(),
            'status_evento_calendario' => 'ATIVO',
        ]);
    }
}
