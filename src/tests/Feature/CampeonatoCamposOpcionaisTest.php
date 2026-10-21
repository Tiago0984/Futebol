<?php

namespace Tests\Feature;

use App\Models\Campeonato;
use App\Models\Jogo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Campos opcionais do campeonato (21/10/2026): local, organizador, logo e banner podem ficar vazios (antes, o
 * banco recusava com erro 500). O local do campeonato é só o "local principal": cada jogo tem o seu, e o site
 * mostra os locais dos jogos quando ele está vazio (Campeonato::textoDosLocais).
 */
class CampeonatoCamposOpcionaisTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $idSub15M;
    private int $botafogo;
    private int $santos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->botafogo = $this->time('Botafogo');
        $this->santos   = $this->time('Santos');
    }

    public function test_colunas_aceitam_vazio(): void
    {
        $colunas = collect(DB::select("SELECT COLUMN_NAME c, IS_NULLABLE n FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_campeonato'"))->pluck('n', 'c');

        foreach (['local_evento', 'organizador_campeonato', 'logo_evento', 'banner_evento'] as $coluna) {
            $this->assertSame('YES', $colunas[$coluna], $coluna);
        }
        $this->assertSame('NO', $colunas['nome_campeonato']);
    }

    public function test_cria_sem_local_organizador_logo_e_banner_e_apaga_o_local_na_edicao(): void
    {
        $this->comoAdmin()->post(route('admin.campeonatos.store'), [...$this->dados('Copa Simples'), 'local_evento' => '', 'organizador_campeonato' => ''])
            ->assertSessionHasNoErrors()->assertSessionHas('sucesso');

        $copa = Campeonato::where('nome_campeonato', 'Copa Simples')->sole();
        $this->assertNull($copa->local_evento);
        $this->assertNull($copa->organizador_campeonato);
        $this->assertNull($copa->logo_evento);
        $this->assertNull($copa->banner_url);

        // Com local, e depois apagando
        $this->comoAdmin()->put(route('admin.campeonatos.update', $copa->id_campeonato), [...$this->dados('Copa Simples'), 'local_evento' => 'Arena A'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Arena A', $copa->fresh()->local_evento);

        $this->comoAdmin()->put(route('admin.campeonatos.update', $copa->id_campeonato), [...$this->dados('Copa Simples'), 'local_evento' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($copa->fresh()->local_evento);

        // A lista e a edição do admin abrem sem logo
        $this->comoAdmin()->get(route('admin.campeonatos.index'))->assertOk()->assertSee('Local principal');
        $this->comoAdmin()->get(route('admin.campeonatos.edit', $copa->id_campeonato))->assertOk();
    }

    public function test_textos_acima_de_100_caracteres_sao_recusados_com_mensagem(): void
    {
        $longo = str_repeat('a', 101);

        $this->comoAdmin()->post(route('admin.campeonatos.store'), [
            ...$this->dados($longo), 'local_evento' => $longo, 'organizador_campeonato' => $longo,
        ])->assertSessionHasErrors([
            'nome_campeonato'        => 'O nome do campeonato pode ter no máximo 100 caracteres.',
            'local_evento'           => 'O local principal pode ter no máximo 100 caracteres.',
            'organizador_campeonato' => 'O organizador pode ter no máximo 100 caracteres.',
        ]);
        $this->assertSame(0, Campeonato::count());

        $this->comoAdmin()->post(route('admin.campeonatos.store'), $this->dados(str_repeat('a', 100)))->assertSessionHasNoErrors();
    }

    public function test_jogo_sem_local_usa_o_do_campeonato_e_com_local_fica_com_o_seu(): void
    {
        $copa = $this->campeonato('Copa', 'Arena Principal');

        $this->jogo($copa, '');
        $this->jogo($copa, 'Arena do Bairro');

        $this->assertSame(['Arena Principal', 'Arena do Bairro'], $this->locaisDosJogos($copa));

        // Sem local no campeonato, o jogo sem local fica sem
        $liga = $this->campeonato('Liga', null);
        $this->jogo($liga, '');
        $this->assertSame([null], $this->locaisDosJogos($liga));
    }

    public function test_texto_dos_locais(): void
    {
        $semLocal = new Campeonato(['local_evento' => null]);
        $jogos = fn (array $locais) => collect($locais)->map(fn ($local) => tap(new Jogo())->setRelation('evento', (object) ['local_evento_calendario' => $local]));

        $this->assertSame('Arena Principal', (new Campeonato(['local_evento' => 'Arena Principal']))->textoDosLocais($jogos(['Arena A'])));
        $this->assertNull($semLocal->textoDosLocais([]));
        $this->assertNull($semLocal->textoDosLocais($jogos([null, ''])));
        $this->assertSame('Arena A', $semLocal->textoDosLocais($jogos(['Arena A', 'arena a ', null])));
        $this->assertSame('Arena A e Arena B', $semLocal->textoDosLocais($jogos(['Arena A', 'Arena B', 'Arena A'])));
        $this->assertSame('Arena A, Arena B e mais 2', $semLocal->textoDosLocais($jogos(['Arena A', 'Arena B', 'Arena C', 'Arena D'])));

        $this->assertSame('CC', (new Campeonato(['nome_campeonato' => 'Copa da Cidade']))->iniciais);
    }

    public function test_site_mostra_o_local_principal_ou_os_locais_dos_jogos(): void
    {
        $principal = $this->campeonato('Copa Principal', 'Arena Principal');
        $this->jogo($principal, 'Arena Escondida');

        $varios = $this->campeonato('Copa Itinerante', null);
        foreach (['Arena A', 'Arena B', 'Arena C'] as $local) {
            $this->jogo($varios, $local);
        }

        $vazio = $this->campeonato('Copa Sem Local', null);

        // No topo, o local principal; no cartão de cada jogo, o local dele (com o horário)
        $this->get(route('campeonato.show', $principal))->assertOk()
            ->assertSeeInOrder(['Arena Principal', '14:00', 'Arena Escondida']);
        $this->get(route('campeonato.show', $varios))->assertOk()
            ->assertSeeInOrder(['Arena A, Arena B e mais 1', 'Arena A', 'Arena B', 'Arena C']);
        $this->get(route('campeonato.show', $vazio))->assertOk()
            ->assertDontSee('fa-map-marker', false);

        $this->get(route('campeonato'))->assertOk()
            ->assertSee('Arena Principal')->assertDontSee('Arena Escondida')->assertSee('Arena A, Arena B e mais 1');
    }

    public function test_site_e_api_sem_logo_e_sem_banner(): void
    {
        $copa = $this->campeonato('Copa da Cidade', null, comImagens: false);

        $this->get(route('campeonato.show', $copa))->assertOk()
            ->assertSee('camp-hero-section sem-imagem', false)->assertDontSee('camp-hero-logo-wrap', false)
            ->assertDontSee('futebol/images/campeonatos/"', false);
        $this->get(route('campeonato'))->assertOk()->assertSee('camp-card-banner sem-imagem', false);
        $this->get(route('home'))->assertOk()->assertSee('home-camp-logo-iniciais', false)->assertSee('>CC<', false);

        $this->getJson("/api/v1/campeonatos/{$copa}")->assertOk()
            ->assertJsonPath('data.local_evento', null)
            ->assertJsonPath('data.organizador_campeonato', null)
            ->assertJsonPath('data.logo_url', null)
            ->assertJsonPath('data.banner_url', null);
    }

    // ---------- auxiliares ----------

    private function comoAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    private function dados(string $nome): array
    {
        return [
            'nome_campeonato' => $nome, 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'categorias' => [$this->idSub15M],
        ];
    }

    private function campeonato(string $nome, ?string $local, bool $comImagens = true): int
    {
        $id = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub15M, 'nome_campeonato' => $nome, 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'local_evento' => $local, 'status_campeonato' => 'ATIVO',
            ...($comImagens ? ['logo_evento' => 'logo.png', 'banner_evento' => 'banner.png', 'organizador_campeonato' => 'Liga'] : []),
        ]);
        $this->participantes($id, [$this->botafogo, $this->santos], $this->idSub15M);

        return $id;
    }

    // Jogo entre dois externos, pela tela de Jogos: nasce publicado (aparece no site)
    private function jogo(int $idCampeonato, string $local): void
    {
        $this->comoAdmin()->post(route('admin.jogos.store'), [
            'id_campeonato' => $idCampeonato, 'id_categoria' => $this->idSub15M,
            'id_time_casa' => $this->botafogo, 'id_time_visitante' => $this->santos,
            'data_evento_calendario' => now()->addDays(10 + Jogo::count())->toDateString(),
            'horario_inicio_evento_calendario' => '14:00', 'horario_fim_evento_calendario' => '',
            'local_evento_calendario' => $local, 'placar_time_casa_jogos' => '', 'placar_time_visitante_jogos' => '',
        ])->assertSessionHasNoErrors();
    }

    private function locaisDosJogos(int $idCampeonato): array
    {
        return Jogo::with('evento')->where('id_campeonato', $idCampeonato)->orderBy('id_jogo')->get()
            ->map(fn ($jogo) => $jogo->evento->local_evento_calendario)->all();
    }

    private function time(string $nome): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub15M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => 'EXTERNO',
            'status_time' => 'ATIVO',
        ]);
    }
}
