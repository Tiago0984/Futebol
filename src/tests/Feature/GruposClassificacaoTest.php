<?php

namespace Tests\Feature;

use App\Models\Campeonato;
use App\Models\CampeonatoGrupo;
use App\Models\EventoCalendario;
use App\Models\Jogo;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase H, Etapa H4: grupos por categoria do campeonato (opcionais), fase do jogo (lista fixa) e classificação por
 * categoria e grupo, contando só a fase de grupos. A tela "Grupos e classificação" cria os grupos, põe os times
 * neles e mostra as tabelas.
 */
class GruposClassificacaoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $idSub15M;
    private int $idSub17M;
    private int $copa;
    private int $aacj15;
    private int $botafogo;
    private int $santos;
    private int $saoPaulo;
    private int $aacj17;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->idSub17M = $this->idCategoria('Sub-17', 'M');
        $this->copa = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub17M, 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png',
            'nome_campeonato' => 'Copa São Carlos', 'organizador_campeonato' => 'Liga', 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'local_evento' => 'Arena',
        ]);
        $this->aacj15   = $this->time('AACJ Sub-15', 'INTERNO');
        $this->botafogo = $this->time('Botafogo', 'EXTERNO');
        $this->santos   = $this->time('Santos', 'EXTERNO');
        $this->saoPaulo = $this->time('São Paulo', 'EXTERNO');
        $this->aacj17   = $this->time('AACJ Sub-17', 'INTERNO');
        $this->participantes($this->copa, [$this->aacj15, $this->botafogo, $this->santos, $this->saoPaulo], $this->idSub15M);
        $this->participantes($this->copa, [$this->aacj17, $this->botafogo], $this->idSub17M);
    }

    // ---------- tabelas ----------

    public function test_tabelas_com_as_fks_compostas_e_a_fase_em_lista_fixa(): void
    {
        $fks = collect(DB::select("SELECT CONSTRAINT_NAME n, TABLE_NAME t FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME IN ('fk_cg_campeonato_categoria', 'fk_ct_grupo')"))->pluck('t', 'n');
        $this->assertSame(['fk_cg_campeonato_categoria' => 'tbl_campeonato_grupo', 'fk_ct_grupo' => 'tbl_campeonato_time'], $fks->sortKeys()->all());

        $fase = DB::selectOne("SELECT COLUMN_TYPE t, IS_NULLABLE n FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_jogos' AND COLUMN_NAME = 'fase_jogo'");
        // A repescagem entrou depois, no fim do ENUM; na lista (e no formulário), logo depois da fase de grupos
        $this->assertSame("enum('GRUPOS','DEZESSEIS_AVOS','OITAVAS','QUARTAS','SEMIFINAL','TERCEIRO','FINAL','REPESCAGEM')", $fase->t);
        $this->assertSame('YES', $fase->n);
        $this->assertSame(['GRUPOS', 'REPESCAGEM', 'DEZESSEIS_AVOS', 'OITAVAS', 'QUARTAS', 'SEMIFINAL', 'TERCEIRO', 'FINAL'], array_keys(Jogo::FASES));

        $classificados = DB::selectOne("SELECT COLUMN_TYPE t, IS_NULLABLE n FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_campeonato_categoria' AND COLUMN_NAME = 'classificados_campeonato_categoria'");
        $this->assertSame('tinyint unsigned', $classificados->t);
        $this->assertSame('YES', $classificados->n);
    }

    public function test_classificados_por_grupo_destacados_e_repescagem_fora_da_conta(): void
    {
        $grupo = $this->grupo('Chave A', $this->idSub15M);
        Campeonato::find($this->copa)->definirGrupos($this->idSub15M, [$this->aacj15 => $grupo, $this->botafogo => $grupo, $this->santos => $grupo]);
        $this->jogo($this->aacj15, $this->botafogo, 2, 0);
        $this->jogo($this->botafogo, $this->santos, 1, 0);
        $this->jogo($this->santos, $this->aacj15, 3, 0, 'REPESCAGEM'); // não conta

        // 2 classificados por grupo (chave) no Sub-15
        $this->comoAdmin()->put(route('admin.campeonatos.grupos.classificados', [$this->copa, $this->idSub15M]), ['classificados' => 2])
            ->assertSessionHas('sucesso', '2 classificado(s) por grupo (chave).');
        $this->comoAdmin()->put(route('admin.campeonatos.grupos.classificados', [$this->copa, $this->idSub15M]), ['classificados' => 0])
            ->assertSessionHasErrors(['classificados' => 'Informe de 1 a 16 classificados por grupo (chave), ou deixe vazio.']);

        [$sub15, $sub17] = Campeonato::find($this->copa)->classificacoes();
        $this->assertSame(2, $sub15['classificados']);
        $this->assertSame([2, null], array_column($sub15['tabelas'], 'classificados')); // "Sem grupo" (São Paulo) não destaca
        $this->assertSame([['AACJ Sub-15', 3, 1], ['Botafogo', 3, 2], ['Santos', 0, 1]], $this->resumo($sub15['tabelas'][0]['tabela']));
        $this->assertNull($sub17['classificados']);

        // A tela destaca os 2 primeiros da chave
        $resposta = $this->comoAdmin()->get(route('admin.campeonatos.grupos', $this->copa))->assertOk()
            ->assertSee('Grupos (chaves) e classificação')
            ->assertSee('value="2"', false);
        $this->assertSame(2, substr_count($resposta->getContent(), 'js-classificado'));

        // Vazio: sem definição
        $this->comoAdmin()->put(route('admin.campeonatos.grupos.classificados', [$this->copa, $this->idSub15M]), ['classificados' => ''])
            ->assertSessionHas('sucesso', 'Classificados por grupo (chave) sem definição.');
        $this->assertNull(Campeonato::find($this->copa)->classificacoes()[0]['classificados']);
    }

    public function test_banco_recusa_grupo_de_outra_categoria_no_participante(): void
    {
        $grupo17 = $this->grupo('Grupo A', $this->idSub17M);

        $this->expectException(QueryException::class);
        DB::table('tbl_campeonato_time')->where('id_campeonato', $this->copa)->where('id_categoria', $this->idSub15M)
            ->where('id_time', $this->aacj15)->update(['id_campeonato_grupo' => $grupo17]);
    }

    // ---------- tela de grupos ----------

    public function test_cria_renomeia_e_remove_grupo_vazio(): void
    {
        $this->comoAdmin()->post(route('admin.campeonatos.grupos.criar', $this->copa), ['id_categoria' => $this->idSub15M, 'nome_campeonato_grupo' => ' Grupo 01 '])
            ->assertSessionHas('sucesso', 'Grupo 01 criado no Sub-15 Masculino.');
        $grupo = CampeonatoGrupo::sole();
        $this->assertSame('Grupo 01', $grupo->nome_campeonato_grupo);

        // Nome repetido na mesma categoria: não; em outra categoria: pode
        $this->comoAdmin()->post(route('admin.campeonatos.grupos.criar', $this->copa), ['id_categoria' => $this->idSub15M, 'nome_campeonato_grupo' => 'Grupo 01'])
            ->assertSessionHasErrors(['nome_campeonato_grupo' => 'Já existe um grupo com esse nome nesta categoria.']);
        $this->comoAdmin()->post(route('admin.campeonatos.grupos.criar', $this->copa), ['id_categoria' => $this->idSub17M, 'nome_campeonato_grupo' => 'Grupo 01'])
            ->assertSessionHasNoErrors();
        // Categoria que o campeonato não tem: não
        $this->comoAdmin()->post(route('admin.campeonatos.grupos.criar', $this->copa), ['id_categoria' => $this->idCategoria('Sub-13', 'M'), 'nome_campeonato_grupo' => 'X'])
            ->assertSessionHasErrors(['id_categoria' => 'Escolha uma categoria do campeonato.']);

        $this->comoAdmin()->patch(route('admin.campeonatos.grupos.renomear', [$this->copa, $grupo->id_campeonato_grupo]), ['nome_campeonato_grupo' => 'Grupo A'])
            ->assertSessionHas('sucesso', 'Grupo 01 agora se chama Grupo A.');

        // Com time, não remove; vazio, remove
        $this->comoAdmin()->put(route('admin.campeonatos.grupos.times', [$this->copa, $this->idSub15M]), ['grupos' => [$this->aacj15 => $grupo->id_campeonato_grupo]]);
        $this->comoAdmin()->delete(route('admin.campeonatos.grupos.remover', [$this->copa, $grupo->id_campeonato_grupo]))
            ->assertSessionHas('erro', 'O Grupo A tem 1 time(s). Tire os times dele antes de remover.');
        $this->comoAdmin()->put(route('admin.campeonatos.grupos.times', [$this->copa, $this->idSub15M]), ['grupos' => [$this->aacj15 => '']]);
        $this->comoAdmin()->delete(route('admin.campeonatos.grupos.remover', [$this->copa, $grupo->id_campeonato_grupo]))
            ->assertSessionHas('sucesso', 'Grupo A removido.');
        $this->assertDatabaseMissing('tbl_campeonato_grupo', ['id_campeonato_grupo' => $grupo->id_campeonato_grupo]);
    }

    public function test_times_so_em_grupo_da_mesma_categoria(): void
    {
        $grupo17 = $this->grupo('Grupo A', $this->idSub17M);

        $this->comoAdmin()->put(route('admin.campeonatos.grupos.times', [$this->copa, $this->idSub15M]), ['grupos' => [$this->aacj15 => $grupo17]])
            ->assertSessionHas('erro', 'Escolha um grupo desta categoria do campeonato.');
        $this->assertNull(DB::table('tbl_campeonato_time')->where('id_time', $this->aacj15)->value('id_campeonato_grupo'));
    }

    // ---------- classificação ----------

    public function test_classificacao_por_grupo_so_com_a_fase_de_grupos(): void
    {
        $grupo1 = $this->grupo('Grupo 01', $this->idSub15M);
        $grupo2 = $this->grupo('Grupo 02', $this->idSub15M);
        Campeonato::find($this->copa)->definirGrupos($this->idSub15M, [
            $this->aacj15 => $grupo1, $this->botafogo => $grupo1, $this->santos => $grupo2, // São Paulo sem grupo
        ]);

        $this->jogo($this->aacj15, $this->botafogo, 2, 1);             // grupo 01
        $this->jogo($this->botafogo, $this->santos, 3, 0);             // entre grupos: conta para cada um no seu
        $this->jogo($this->aacj15, $this->santos, 0, 5, 'FINAL');      // mata-mata: não conta
        $this->jogo($this->aacj15, $this->botafogo, 1, 1, 'GRUPOS', $this->idSub17M); // outra categoria: não conta

        [$sub15, $sub17] = Campeonato::find($this->copa)->classificacoes();
        $this->assertSame('Sub-15 Masculino', $sub15['categoria']->rotulo);
        $this->assertSame(['Grupo 01', 'Grupo 02', 'Sem grupo'], array_column($sub15['tabelas'], 'grupo'));

        [$g1, $g2, $semGrupo] = array_column($sub15['tabelas'], 'tabela');
        // Botafogo e AACJ com 3 pontos e 1 vitória: o Botafogo na frente pelo saldo (+2 contra +1)
        $this->assertSame([['Botafogo', 3, 2], ['AACJ Sub-15', 3, 1]], $this->resumo($g1));
        $this->assertSame([['Santos', 0, 1]], $this->resumo($g2));
        $this->assertSame([['São Paulo', 0, 0]], $this->resumo($semGrupo)); // sem jogo: com zeros

        // Grupo vazio: tabela vazia (não "todos que jogaram")
        $this->grupo('Grupo 03', $this->idSub15M);
        $tabelas = Campeonato::find($this->copa)->classificacoes()[0]['tabelas'];
        $this->assertSame(['Grupo 01', 'Grupo 02', 'Grupo 03', 'Sem grupo'], array_column($tabelas, 'grupo'));
        $this->assertSame([], $tabelas[2]['tabela']);

        // Sub-17 sem grupos: uma tabela só, com todos os participantes; o jogo do Sub-17 conta para o Botafogo
        // (participante) e não põe o AACJ Sub-15 (que não é do Sub-17) na tabela
        $this->assertSame([null], array_column($sub17['tabelas'], 'grupo'));
        $this->assertSame([['Botafogo', 1, 1], ['AACJ Sub-17', 0, 0]], $this->resumo($sub17['tabelas'][0]['tabela']));

        // A tela mostra as tabelas
        $this->comoAdmin()->get(route('admin.campeonatos.grupos', $this->copa))->assertOk()
            ->assertSeeInOrder(['Sub-15 Masculino', 'Grupo 01', 'Botafogo', 'AACJ Sub-15', 'Grupo 02', 'Santos', 'Sem grupo', 'São Paulo', 'Sub-17 Masculino']);
    }

    public function test_site_conta_so_a_fase_de_grupos(): void
    {
        $this->jogo($this->aacj15, $this->botafogo, 2, 1);
        $this->jogo($this->botafogo, $this->aacj15, 4, 0, 'SEMIFINAL');

        $linhas = Jogo::classificacao(Jogo::where('id_campeonato', $this->copa)->with(['evento', 'timeCasa', 'timeVisitante'])->get());
        $this->assertSame([['AACJ Sub-15', 3, 1], ['Botafogo', 0, 1]], $this->resumo($linhas));
    }

    // ---------- fase do jogo ----------

    public function test_jogo_de_campeonato_tem_fase_e_o_amistoso_nao(): void
    {
        $dados = [
            'id_campeonato' => $this->copa, 'id_categoria' => $this->idSub15M, 'id_time_casa' => $this->botafogo, 'id_time_visitante' => $this->santos,
            'data_evento_calendario' => now()->addWeek()->toDateString(), 'horario_inicio_evento_calendario' => '14:00',
        ];

        // Sem fase: a de grupos; com fase: a escolhida; fora da lista: recusada
        $this->comoAdmin()->post(route('admin.jogos.store'), $dados)->assertSessionHasNoErrors();
        $this->comoAdmin()->post(route('admin.jogos.store'), [...$dados, 'fase_jogo' => 'SEMIFINAL', 'horario_inicio_evento_calendario' => '16:00'])
            ->assertSessionHasNoErrors();
        $this->comoAdmin()->post(route('admin.jogos.store'), [...$dados, 'fase_jogo' => 'PRELIMINAR'])
            ->assertSessionHasErrors(['fase_jogo' => 'Escolha uma fase da lista.']);
        $this->assertSame(['GRUPOS', 'SEMIFINAL'], Jogo::orderBy('id_jogo')->pluck('fase_jogo')->all());

        $semifinal = Jogo::where('fase_jogo', 'SEMIFINAL')->sole();
        $this->assertSame('Semifinal', $semifinal->fase_label);
        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $semifinal->id_evento))->assertSee('Semifinal');

        // Amistoso: sem fase, mesmo que venha uma
        $this->comoAdmin()->post(route('admin.jogos.store'), [...$dados, 'id_campeonato' => 'AMISTOSO', 'id_categoria' => '',
            'id_time_casa' => $this->aacj15, 'fase_jogo' => 'FINAL'])->assertSessionHasNoErrors();
        $this->assertNull(Jogo::whereNull('id_campeonato')->sole()->fase_jogo);
    }

    // ---------- campeonato ----------

    public function test_tirar_a_categoria_do_campeonato_tira_os_grupos_dela(): void
    {
        $this->grupo('Grupo A', $this->idSub17M);
        $this->grupo('Grupo A', $this->idSub15M);

        Campeonato::find($this->copa)->gravarCategoriasEParticipantes([$this->idSub15M], [$this->idSub15M => [$this->aacj15]]);

        $this->assertSame([$this->idSub15M], CampeonatoGrupo::pluck('id_categoria')->map(fn ($id) => (int) $id)->all());
    }

    // ---------- apoio ----------

    private function comoAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    private function grupo(string $nome, int $idCategoria): int
    {
        return CampeonatoGrupo::create(['id_campeonato' => $this->copa, 'id_categoria' => $idCategoria, 'nome_campeonato_grupo' => $nome])
            ->id_campeonato_grupo;
    }

    // Jogo publicado e ativo, com placar, na categoria e fase informadas (padrão: Sub-15, fase de grupos)
    private function jogo(int $casa, int $visitante, int $golsCasa, int $golsVisitante, string $fase = 'GRUPOS', ?int $idCategoria = null): Jogo
    {
        $evento = EventoCalendario::criarPor($this->admin->id_usuario, [
            'titulo_evento_calendario' => Jogo::tituloPara($casa, $visitante), 'tipo_evento_calendario' => 'JOGO',
            'data_evento_calendario' => now()->subDay()->toDateString(), 'horario_inicio_evento_calendario' => '14:00:00',
            'status_evento_calendario' => 'ATIVO', 'id_categoria' => $idCategoria ?? $this->idSub15M,
        ], inscreverCategoria: false);

        return Jogo::create([
            'id_evento' => $evento->id_evento_calendario, 'id_campeonato' => $this->copa, 'fase_jogo' => $fase,
            'id_time_casa' => $casa, 'id_time_visitante' => $visitante,
            'placar_time_casa_jogos' => $golsCasa, 'placar_time_visitante_jogos' => $golsVisitante,
        ]);
    }

    // [[nome, pontos, jogos], ...] na ordem da tabela
    private function resumo(array $linhas): array
    {
        return array_map(fn ($l) => [$l['nome'], $l['pontos'], $l['pj']], $linhas);
    }

    private function time(string $nome, string $tipo): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub15M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo, 'status_time' => 'ATIVO',
        ]);
    }
}
