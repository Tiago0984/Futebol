<?php

namespace Tests\Feature;

use App\Models\Jogo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Campeonato é por faixa de idade (professor, 09/10/2026): o mais novo pode jogar numa categoria acima
 * (Sub-13 no Sub-15), o mais velho nunca joga abaixo. Idade pelo ano do jogo (Categoria::idadeNoAno).
 * Amistoso não tem limite (CLAUDE.md, seção 8).
 */
class JogoIdadeCampeonatoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const FORA_DA_LIGA = ' 1 atleta(s) da convocação ficaram de fora por passar da idade do campeonato'
        . ' (Sub-15 Masculino, até 15 anos): Caio (16 anos).';

    private User $admin;
    private string $dia;
    private int $idSub13M;
    private int $idSub15M;
    private int $idSub17M;
    private int $liga;
    private int $azul;
    private int $verde;
    private int $visitante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->dia      = now()->addDays(10)->toDateString();
        $this->idSub13M = $this->idCategoria('Sub-13', 'M');
        $this->idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->idSub17M = $this->idCategoria('Sub-17', 'M');
        $this->liga = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub15M, 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png',
            'nome_campeonato' => 'Liga', 'organizador_campeonato' => 'Liga', 'tipo_campeonato' => 'MATA-MATA',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'local_evento' => 'Campo A',
        ]);
        $this->azul      = $this->time('Time Azul', 'INTERNO');
        $this->verde     = $this->time('Time Verde', 'INTERNO');
        $this->visitante = $this->time('Time Visitante', 'EXTERNO');
        // Participantes da Liga (a convocação exige o time participante: Fase H, Etapa H2)
        $this->participantes($this->liga, [$this->azul, $this->verde, $this->visitante]);
    }

    // ---------- criar e preencher pela convocação ----------

    public function test_criar_jogo_do_campeonato_deixa_de_fora_quem_passa_da_idade_e_nao_avisa_o_mais_novo(): void
    {
        $ana  = $this->atleta('Ana', 13, $this->idSub13M, [$this->azul]); // mais novo: joga acima
        $bia  = $this->atleta('Bia', 15, $this->idSub15M, [$this->azul]); // da idade
        $this->atleta('Caio', 16, $this->idSub17M, [$this->azul]);        // mais velho: fica de fora

        $this->postJogo()
            ->assertSessionHas('sucesso', 'Jogo registrado como rascunho. 2 atleta(s) da convocação inscrito(s).'
                . self::FORA_DA_LIGA . ' Os atletas serão avisados ao publicar.')
            ->assertSessionMissing('avisos_categoria');

        $this->assertSame([$ana => $this->azul, $bia => $this->azul], $this->inscritos(Jogo::sole()));
    }

    public function test_idade_conta_pelo_ano_do_jogo(): void
    {
        // 15 anos neste ano, 16 no ano que vem: joga a Liga Sub-15 deste ano, não a do ano que vem
        $davi = $this->atleta('Davi', 15, $this->idSub15M, [$this->azul]);

        $this->postJogo()->assertSessionHas('sucesso');
        $this->assertSame([$davi => $this->azul], $this->inscritos(Jogo::sole()));

        $this->postJogo(['data_evento_calendario' => now()->addYear()->startOfYear()->addDays(10)->toDateString()])
            ->assertSessionHas('sucesso', fn ($msg) => str_contains($msg, 'Davi (16 anos)'));
        $this->assertSame([], $this->inscritos(Jogo::latest('id_jogo')->first()));
    }

    public function test_amistoso_nao_tem_limite_de_idade(): void
    {
        $caio = $this->atleta('Caio', 16, $this->idSub17M);
        $this->postJogo(['id_campeonato' => 'AMISTOSO', 'id_categoria' => $this->idSub15M])->assertSessionHasNoErrors();
        $jogo = Jogo::sole();

        // Escolhido no amistoso (Fase G, Etapa 3): entra mesmo passando da idade, só com o aviso de categoria
        $this->actingAs($this->admin, 'admin')->post(route('admin.calendario.eventos.escalacao.escolher', $jogo->id_evento), ['atletas' => [$caio]])
            ->assertSessionHas('sucesso', '1 atleta(s) inscrito(s) e escalado(s) no Time Azul. 0 atleta(s) notificado(s).')
            ->assertSessionHas('avisos_categoria', ['Caio é Sub-17 Masculino; o jogo é Sub-15 Masculino.']); // como antes

        $this->assertSame([$caio => $this->azul], $this->inscritos($jogo));
    }

    public function test_preencher_pelo_elenco_nao_inscreve_quem_passa_da_idade(): void
    {
        $jogo = $this->criarJogo();
        $ana  = $this->atleta('Ana', 13, $this->idSub13M, [$this->azul]); // convocados depois
        $this->atleta('Caio', 16, $this->idSub17M, [$this->azul]);

        // O contador do lado do botão não conta quem não pode entrar
        $this->tela($jogo)->assertSee('1</strong> convocado(s) ainda não está inscrito', false);

        $this->actingAs($this->admin, 'admin')->post(route('admin.calendario.eventos.escalacao.elenco', $jogo->id_evento))
            ->assertSessionHas('sucesso', fn ($msg) => str_ends_with($msg, self::FORA_DA_LIGA))
            ->assertSessionMissing('avisos_categoria');

        $this->assertSame([$ana => $this->azul], $this->inscritos($jogo));
        $this->tela($jogo)->assertSee('Todos os convocados estão inscritos');
    }

    public function test_trocar_time_nao_inscreve_quem_passa_da_idade(): void
    {
        $jogo = $this->criarJogo();
        $bia  = $this->atleta('Bia', 15, $this->idSub15M, [$this->verde]);
        $this->atleta('Caio', 16, $this->idSub17M, [$this->verde]);

        $this->actingAs($this->admin, 'admin')->put(route('admin.jogos.update', $jogo->id_jogo), $this->dadosJogo(['id_time_casa' => $this->verde]))
            ->assertSessionHas('sucesso', fn ($msg) => str_contains($msg, self::FORA_DA_LIGA));

        $this->assertSame([$bia => $this->verde], $this->inscritos($jogo));
    }

    // ---------- inscrição individual, categoria e escalação ----------

    public function test_inscrever_quem_passa_da_idade_e_bloqueado_e_o_mais_novo_entra(): void
    {
        $jogo = $this->criarJogo();
        $ana  = $this->atleta('Ana', 13, $this->idSub13M);
        $caio = $this->atleta('Caio', 16, $this->idSub17M);

        // No select, o mais velho aparece desabilitado
        $this->tela($jogo)->assertSee('<option value="' . $caio . '" disabled>Caio (16 anos) · acima da idade</option>', false);

        $this->inscrever($jogo, $caio, $this->azul)
            ->assertSessionHas('erro', 'Caio (16 anos) passa da idade do campeonato (Sub-15 Masculino, até 15 anos):'
                . ' atleta mais velho não joga em categoria abaixo da idade dele.');
        $this->inscrever($jogo, $ana, $this->azul)->assertSessionHas('sucesso')->assertSessionMissing('avisos_categoria');

        $this->assertSame([$ana => $this->azul], $this->inscritos($jogo));
    }

    public function test_adicionar_categoria_no_jogo_deixa_de_fora_quem_passa_da_idade(): void
    {
        $jogo = $this->criarJogo();
        $this->atleta('Caio', 16, $this->idSub17M);
        $edu = $this->atleta('Edu', 15, $this->idSub17M, [], 'Sub-17 acima da idade'); // na Sub-17 com 15 anos

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.calendario.eventos.inscricoes.categoria', $jogo->id_evento), ['id_categoria' => $this->idSub17M])
            ->assertSessionHas('sucesso', 'Sub-17 Masculino: 1 atleta(s) inscrito(s). 1 atleta(s) notificado(s).'
                . ' 1 atleta(s) da categoria ficaram de fora por passar da idade do campeonato (Sub-15 Masculino, até 15 anos): Caio (16 anos).');

        $this->assertSame([$edu => null], $this->inscritos($jogo));
    }

    public function test_inscrito_antigo_acima_da_idade_aparece_na_tela_nao_e_escalado_e_trava_a_publicacao(): void
    {
        $this->postJogo()->assertSessionHas('sucesso');
        $jogo = Jogo::sole()->load('evento');
        $caio = $this->atleta('Caio', 16, $this->idSub17M);
        $jogo->evento->inscrever($caio, 'INDIVIDUAL', null); // inscrito antes da regra

        $this->tela($jogo)
            ->assertSee('Acima da idade do campeonato (Sub-15 Masculino, até 15 anos)')
            ->assertSee('Caio (16 anos).')
            // O botão já mostra o bloqueio (o servidor confere de qualquer jeito, abaixo)
            ->assertSee('Para publicar, remova antes o atleta acima da idade do campeonato.')
            ->assertSee('id="btnPublicar" disabled', false);

        // Escalar não pode; tirar do time pode
        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.calendario.eventos.inscricoes.time', [$jogo->id_evento, $caio]), ['id_time' => $this->azul])
            ->assertSessionHas('erro', fn ($msg) => str_starts_with($msg, 'Caio (16 anos) passa da idade do campeonato'));
        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.calendario.eventos.inscricoes.time', [$jogo->id_evento, $caio]), ['id_time' => ''])
            ->assertSessionHas('sucesso');

        $this->publicar($jogo)->assertSessionHas('erro', 'Jogo não publicado: Caio (16 anos) passa da idade do campeonato'
            . ' (Sub-15 Masculino, até 15 anos). Remova a inscrição antes de publicar.');
        $this->assertFalse($jogo->evento->fresh()->estaPublicado());

        $jogo->evento->removerInscricao($caio);
        $this->tela($jogo)->assertDontSee('id="btnPublicar" disabled', false)->assertDontSee('Para publicar, remova antes');
        $this->publicar($jogo)->assertSessionHas('sucesso');
        $this->assertTrue($jogo->evento->fresh()->estaPublicado());
    }

    // ---------- apoio ----------

    // Atleta ATIVO na categoria, com a idade no ano do jogo, e no elenco dos times informados
    private function atleta(string $nome, int $idade, int $idCategoria, array $elencos = [], ?string $motivo = null): int
    {
        $ano = (int) substr($this->dia, 0, 4);
        $id  = $this->criarAtleta(($ano - $idade) . '-06-15', 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
        $this->colocarNaCategoria($id, $idCategoria, $motivo);

        foreach ($elencos as $idTime) {
            DB::table('tbl_atleta_time')->insert([
                'id_time' => $idTime, 'id_atleta' => $id, 'camisa_atleta_time' => 10, 'posicao_atleta_time' => '',
            ]);
        }

        // Jogo da Liga usa a convocação (Fase G, Etapa 2): convocado pelo primeiro time, gravado direto (o
        // jogo confere a idade pelo ano do jogo; a tela de Convocados, pelo de início do campeonato)
        if ($elencos) {
            $this->convocacaoDireta($this->liga, $elencos[0], $id);
        }

        return $id;
    }

    private function dadosJogo(array $extra = []): array
    {
        return array_merge([
            'id_campeonato'                    => $this->liga,
            'id_time_casa'                     => $this->azul,
            'id_time_visitante'                => $this->visitante,
            'data_evento_calendario'           => $this->dia,
            'horario_inicio_evento_calendario' => '13:00',
            'local_evento_calendario'          => 'Campo A',
        ], $extra);
    }

    private function postJogo(array $extra = [])
    {
        return $this->actingAs($this->admin, 'admin')->post(route('admin.jogos.store'), $this->dadosJogo($extra));
    }

    // Cria o jogo da Liga (Azul x Visitante) e publica
    private function criarJogo(): Jogo
    {
        $this->postJogo()->assertSessionHasNoErrors();
        $jogo = Jogo::latest('id_jogo')->first();
        $this->publicar($jogo)->assertSessionHas('sucesso');

        return $jogo->load('evento');
    }

    private function publicar(Jogo $jogo)
    {
        return $this->actingAs($this->admin, 'admin')->patch(route('admin.jogos.publicar', $jogo->id_jogo));
    }

    private function inscrever(Jogo $jogo, int $idAtleta, ?int $idTime = null)
    {
        return $this->actingAs($this->admin, 'admin')->post(route('admin.calendario.eventos.inscricoes.store', $jogo->id_evento),
            ['id_atleta' => $idAtleta, 'id_time' => $idTime]);
    }

    private function tela(Jogo $jogo)
    {
        return $this->actingAs($this->admin, 'admin')->get(route('admin.calendario.eventos.show', $jogo->id_evento))->assertOk();
    }

    // Inscrições do jogo: [id_atleta => id_time]
    private function inscritos(Jogo $jogo): array
    {
        return DB::table('tbl_evento_atleta')->where('id_evento_calendario', $jogo->id_evento)->orderBy('id_atleta')
            ->pluck('id_time', 'id_atleta')->map(fn ($t) => $t === null ? null : (int) $t)->all();
    }

    private function time(string $nome, string $tipo): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub15M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo,
        ]);
    }
}
