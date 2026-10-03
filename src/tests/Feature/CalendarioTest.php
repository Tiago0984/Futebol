<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CalendarioTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as private refreshDatabaseOriginal;
    }

    private const BANCO_TESTE = 'db_futebol_test';
    private const TABELA      = 'tbl_evento_calendario';
    private const PK          = 'id_evento_calendario';
    private const GUARD       = 'admin';

    // Rotas reais (php artisan route:list --path=calendario)
    private const URL_ADMIN = '/admin/calendario/eventos';
    private const URL_SITE  = '/calendario';

    /**
     * Trava de segurança: o RefreshDatabase roda migrate:fresh.
     * Se a conexão não for o banco de testes, aborta antes de apagar qualquer coisa.
     */
    public function refreshDatabase()
    {
        $banco = DB::connection()->getDatabaseName();

        if ($banco !== self::BANCO_TESTE) {
            throw new RuntimeException(
                "Testes abortados: conectado em '{$banco}', esperado '" . self::BANCO_TESTE . "'. "
                . 'Rode php artisan config:clear e confira o phpunit.xml.'
            );
        }

        $this->refreshDatabaseOriginal();
    }

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

    /**
     * Regra de tipos públicos: avaliações são dados de saúde de menores
     * e nunca aparecem no site. Falha até a regra ser implementada.
     */
    public function test_site_nao_lista_eventos_de_avaliacao(): void
    {
        $this->criarEvento([
            'titulo_evento_calendario' => 'Jogo Publico XYZ',
            'tipo_evento_calendario'   => 'JOGO',
        ]);
        $this->criarEvento([
            'titulo_evento_calendario' => 'Avaliacao Fisica XYZ',
            'tipo_evento_calendario'   => 'AVALIACAO',
        ]);

        $this->get(self::URL_SITE)
            ->assertOk()
            ->assertSee('Jogo Publico XYZ')
            ->assertDontSee('Avaliacao Fisica XYZ');
    }

    // ---------- helpers ----------

    private function comoAdmin(): static
    {
        return $this->actingAs(User::factory()->create(), self::GUARD);
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
