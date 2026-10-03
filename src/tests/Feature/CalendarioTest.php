<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

class CalendarioTest extends TestCase
{
    use RefreshBancoDeTestes;

    private const TABELA      = 'tbl_evento_calendario';
    private const PK          = 'id_evento_calendario';
    private const GUARD       = 'admin';

    // Rotas reais (php artisan route:list --path=calendario)
    private const URL_ADMIN = '/admin/calendario/eventos';
    private const URL_SITE  = '/calendario';

    public static function tipos(): array
    {
        return [
            'JOGO'             => ['JOGO'],
            'TREINO'           => ['TREINO'],
            'CAMPEONATO'       => ['CAMPEONATO'],
            'EVENTO'           => ['EVENTO'],
            'REUNIAO'          => ['REUNIAO'],
            'CONFRATERNIZACAO' => ['CONFRATERNIZACAO'],
            'AVALIACAO'        => ['AVALIACAO'],
        ];
    }

    #[DataProvider('tipos')]
    public function test_admin_cria_evento_de_cada_tipo(string $tipo): void
    {
        $this->comoAdmin()
            ->post(self::URL_ADMIN, $this->dados(['tipo_evento_calendario' => $tipo]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas(self::TABELA, [
            'titulo_evento_calendario' => 'Evento de Teste',
            'tipo_evento_calendario'   => $tipo,
        ]);
    }

    public function test_admin_edita_campeonato_e_o_tipo_permanece(): void
    {
        $id = $this->criarEvento([
            'titulo_evento_calendario'  => 'Torneio Original',
            'tipo_evento_calendario'    => 'CAMPEONATO',
            'subtipo_evento_calendario' => 'Campeonato',
        ]);

        $this->comoAdmin()->put(self::URL_ADMIN . '/' . $id, $this->dados([
            'titulo_evento_calendario'  => 'Torneio Editado',
            'tipo_evento_calendario'    => 'CAMPEONATO',
            'subtipo_evento_calendario' => 'Campeonato',
            'local_evento_calendario'   => 'Estádio Novo',
            'status_evento_calendario'  => 'CONFIRMADO',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas(self::TABELA, [
            self::PK                   => $id,
            'titulo_evento_calendario' => 'Torneio Editado',
            'local_evento_calendario'  => 'Estádio Novo',
            'tipo_evento_calendario'   => 'CAMPEONATO',
        ]);
    }

    public function test_admin_nao_cria_evento_com_tipo_invalido(): void
    {
        $this->comoAdmin()
            ->post(self::URL_ADMIN, $this->dados(['tipo_evento_calendario' => 'INVALIDO']))
            ->assertSessionHasErrors('tipo_evento_calendario');

        $this->assertDatabaseCount(self::TABELA, 0);
    }

    public function test_admin_nao_edita_evento_para_tipo_invalido(): void
    {
        $id = $this->criarEvento(['tipo_evento_calendario' => 'CAMPEONATO']);

        $this->comoAdmin()->put(self::URL_ADMIN . '/' . $id, $this->dados([
            'tipo_evento_calendario'   => 'INVALIDO',
            'status_evento_calendario' => 'CONFIRMADO',
        ]))->assertSessionHasErrors('tipo_evento_calendario');

        $this->assertDatabaseHas(self::TABELA, [
            self::PK                 => $id,
            'tipo_evento_calendario' => 'CAMPEONATO',
        ]);
    }

    public function test_site_nao_lista_eventos_cancelados(): void
    {
        $this->criarEvento(['titulo_evento_calendario' => 'Amistoso Confirmado XYZ']);
        $cancelado = $this->criarEvento(['titulo_evento_calendario' => 'Amistoso Cancelado XYZ']);

        DB::table(self::TABELA)
            ->where(self::PK, $cancelado)
            ->update(['status_evento_calendario' => 'CANCELADO']);

        $this->get(self::URL_SITE)
            ->assertOk()
            ->assertSee('Amistoso Confirmado XYZ')
            ->assertDontSee('Amistoso Cancelado XYZ');
    }

    // Regra provisória de tipos públicos (EventoCalendario::TIPOS_PUBLICOS)
    public static function tiposPublicos(): array
    {
        return [
            'JOGO'       => ['JOGO'],
            'TREINO'     => ['TREINO'],
            'CAMPEONATO' => ['CAMPEONATO'],
        ];
    }

    public static function tiposInternos(): array
    {
        return [
            'EVENTO'           => ['EVENTO'],
            'REUNIAO'          => ['REUNIAO'],
            'CONFRATERNIZACAO' => ['CONFRATERNIZACAO'],
            'AVALIACAO'        => ['AVALIACAO'],
        ];
    }

    /** Um único evento futuro: cobre a lista e o bloco "Próximo Evento". */
    #[DataProvider('tiposPublicos')]
    public function test_site_lista_eventos_de_tipo_publico(string $tipo): void
    {
        $this->criarEvento([
            'titulo_evento_calendario' => "Evento Publico {$tipo} XYZ",
            'tipo_evento_calendario'   => $tipo,
        ]);

        $this->get(self::URL_SITE)
            ->assertOk()
            ->assertSee("Evento Publico {$tipo} XYZ");
    }

    #[DataProvider('tiposInternos')]
    public function test_site_nao_lista_eventos_de_tipo_interno(string $tipo): void
    {
        $this->criarEvento([
            'titulo_evento_calendario' => "Evento Interno {$tipo} XYZ",
            'tipo_evento_calendario'   => $tipo,
        ]);

        $this->get(self::URL_SITE)
            ->assertOk()
            ->assertDontSee("Evento Interno {$tipo} XYZ");
    }

    // ---------- helpers ----------

    private function comoAdmin(): static
    {
        return $this->actingAs(User::factory()->admin()->create(), self::GUARD);
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'titulo_evento_calendario'         => 'Evento de Teste',
            'data_evento_calendario'           => '2026-11-15',
            'tipo_evento_calendario'           => 'JOGO',
            'subtipo_evento_calendario'        => 'Jogo Oficial',
            'horario_inicio_evento_calendario' => '09:00',
            'horario_fim_evento_calendario'    => '11:00',
            'local_evento_calendario'          => 'Campo Central',
            'descricao_evento_calendario'      => 'Criado pelo teste automatizado.',
        ], $extra);
    }

    /** Cria o evento pela rota do admin e devolve o id gravado. */
    private function criarEvento(array $extra = []): int
    {
        $dados = $this->dados($extra);

        $this->comoAdmin()->post(self::URL_ADMIN, $dados)->assertSessionHasNoErrors();

        $id = DB::table(self::TABELA)
            ->where('titulo_evento_calendario', $dados['titulo_evento_calendario'])
            ->value(self::PK);

        $this->assertNotNull($id, 'O evento não foi gravado pela rota do admin.');

        return (int) $id;
    }
}
