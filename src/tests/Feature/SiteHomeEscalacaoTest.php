<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Services\EscalacaoDaCategoria;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * "Nossas categorias" da home (03/11/2026): a escalação de cada categoria num campo, pelas posições do cadastro
 * (EscalacaoDaCategoria). A formação é a que põe mais atletas na posição deles; o resto vai para o banco.
 */
class SiteHomeEscalacaoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private int $idSub13M;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idSub13M = $this->idCategoria('Sub-13', 'M');
    }

    private function atleta(string $nome, array $posicoes, ?int $idCategoria = null, string $status = 'ATIVO'): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(13), 'M', $status);
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
        $this->colocarNaCategoria($id, $idCategoria ?? $this->idSub13M);
        Atleta::find($id)->definirPosicoes($posicoes);

        return $id;
    }

    private function escalacao(): array
    {
        $atletas = Atleta::with(['posicoes', 'convocacoes'])->where('status_atleta', 'ATIVO')->orderBy('nome_atleta')->get();

        return EscalacaoDaCategoria::montar($atletas, $this->idSub13M);
    }

    private function quemJogaEm(array $escalacao, string $sigla): array
    {
        return collect($escalacao['titulares'])->where('sigla', $sigla)->map(fn ($v) => $v['atleta']?->nome_atleta)->values()->all();
    }

    public function test_escolhe_a_formacao_pelas_posicoes_e_manda_o_resto_para_o_banco(): void
    {
        $this->atleta('Ana Goleiro', ['GOLEIRO']);
        $this->atleta('Beto Goleiro', ['GOLEIRO']);
        $this->atleta('Caio Lateral', ['LATERAL_ESQUERDO']);
        $this->atleta('Davi Zagueiro', ['ZAGUEIRO']);
        $this->atleta('Edu Zagueiro', ['ZAGUEIRO']);
        $this->atleta('Fabio Lateral', ['LATERAL_DIREITO']);
        $this->atleta('Gil Meia', ['MEIA_ESQUERDA']);
        $this->atleta('Hugo Volante', ['VOLANTE']);
        $this->atleta('Ivo Meia', ['MEIA_CENTRAL']);
        $this->atleta('Jair Meia', ['MEIA_DIREITA']);
        $this->atleta('Kai Centroavante', ['CENTROAVANTE']);
        $this->atleta('Leo Atacante', ['SEGUNDO_ATACANTE', 'CENTROAVANTE']);

        $escalacao = $this->escalacao();

        // Com meias pelos lados e dois atacantes, o 4-4-2 põe todos na posição principal
        $this->assertSame('4-4-2', $escalacao['formacao']);
        $this->assertCount(11, $escalacao['titulares']);
        $this->assertSame(['Ana Goleiro'], $this->quemJogaEm($escalacao, 'GOL'));
        $this->assertSame(['Davi Zagueiro', 'Edu Zagueiro'], $this->quemJogaEm($escalacao, 'ZAG'));
        $this->assertSame(['Gil Meia'], $this->quemJogaEm($escalacao, 'ME'));
        $this->assertSame(['Leo Atacante'], $this->quemJogaEm($escalacao, 'SA'));

        // O outro goleiro fica no banco, com a sigla da principal
        $this->assertSame(['Beto Goleiro'], collect($escalacao['reservas'])->map(fn ($r) => $r['atleta']->nome_atleta)->all());
        $this->assertSame('GOL', $escalacao['reservas'][0]['sigla']);
    }

    public function test_sem_goleiro_o_gol_fica_vazio_e_quem_sobra_completa_a_linha(): void
    {
        $this->atleta('Ana Zagueira', ['ZAGUEIRO']);
        $this->atleta('Bia Sem Posicao', []);

        $escalacao = $this->escalacao();

        $this->assertSame([null], $this->quemJogaEm($escalacao, 'GOL'));
        $titulares = collect($escalacao['titulares'])->pluck('atleta')->filter()->map->nome_atleta->sort()->values()->all();
        $this->assertSame(['Ana Zagueira', 'Bia Sem Posicao'], $titulares);
        $this->assertSame([], $escalacao['reservas']);
    }

    public function test_camisa_da_convocacao_mais_recente_na_categoria(): void
    {
        $id   = $this->atleta('Ana Goleiro', ['GOLEIRO']);
        $time = DB::table('tbl_time')->insertGetId(['logo_time' => 'aacj.png', 'nome_time' => 'AACJ', 'tipo_time' => 'INTERNO', 'status_time' => 'ATIVO']);
        $copa = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub13M, 'nome_campeonato' => 'Copa', 'tipo_campeonato' => 'TORNEIO',
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
            'status_campeonato' => 'ATIVO',
        ]);
        $this->convocacaoDireta($copa, $time, $id, 12);

        $this->assertSame(12, $this->escalacao()['titulares'][0]['camisa']);
    }

    public function test_nome_curto(): void
    {
        $this->assertSame('Ana Silva', EscalacaoDaCategoria::nomeCurto('Ana Maria Gomes da Silva'));
        $this->assertSame('Caio', EscalacaoDaCategoria::nomeCurto(' Caio '));
    }

    public function test_home_mostra_a_escalacao_de_cada_categoria(): void
    {
        $idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->atleta('Ana Maria Goleira', ['GOLEIRO']);
        $this->atleta('Otavio Centroavante', ['CENTROAVANTE'], $idSub15M);
        $this->atleta('Rui Inativo', ['ZAGUEIRO'], null, 'INATIVO');

        $this->get('/')->assertOk()
            ->assertSee('NOSSAS CATEGORIAS')
            ->assertSeeInOrder(['data-escalacao="' . $this->idSub13M . '"', 'SUB-13 MASCULINO', '1 atleta'], false)
            ->assertSeeInOrder(['data-escalacao-aba="' . $this->idSub13M . '"', 'Ana Goleira', 'data-escalacao-aba="' . $idSub15M . '"', 'Otavio Centroavante'], false)
            ->assertSee('title="Ana Maria Goleira"', false)
            ->assertSee(e(route('jogadores.vitrine', ['categoria' => $this->idSub13M])), false)
            ->assertDontSee('Rui Inativo');
    }
}
