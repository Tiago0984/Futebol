<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\Convocacao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase G, Etapa 1: convocação por campeonato (tbl_convocacao) e a tela "Convocados" de cada time interno.
 * Um time por atleta em cada campeonato, camisa obrigatória e sem repetir no time, idade do campeonato pelo
 * ano de início (o mais novo sobe, o mais velho nunca desce). Ainda não mexe nos jogos (Etapa 2).
 */
class ConvocacaoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $idSub13M;
    private int $idSub15M;
    private int $idSub17M;
    private int $idSub15F;
    private int $liga;
    private int $azul;
    private int $verde;
    private int $preto;
    private int $visitante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->idSub13M = $this->idCategoria('Sub-13', 'M');
        $this->idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->idSub17M = $this->idCategoria('Sub-17', 'M');
        $this->idSub15F = $this->idCategoria('Sub-15', 'F');
        $this->liga     = $this->campeonato('Liga', now()->toDateString());

        $this->azul      = $this->time('AACJ Sub-15', 'INTERNO');
        $this->verde     = $this->time('AACJ Sub-15 B', 'INTERNO');
        $this->preto     = $this->time('Time Preto', 'INTERNO'); // não participa da Liga
        $this->visitante = $this->time('Time Visitante', 'EXTERNO');
        $this->participantes($this->liga, [$this->azul, $this->verde, $this->visitante]);
    }

    // ---------- tabela ----------

    public function test_tabela_com_os_tipos_das_chaves_e_os_dois_unicos(): void
    {
        $tipos = collect(DB::select("SELECT COLUMN_NAME c, COLUMN_TYPE t, IS_NULLABLE n FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_convocacao'"))->keyBy('c');

        $this->assertSame('int unsigned', $tipos['id_convocacao']->t);
        $this->assertSame('int', $tipos['id_campeonato']->t);
        $this->assertSame('int', $tipos['id_time']->t);
        $this->assertSame('int', $tipos['id_atleta']->t);
        $this->assertSame('tinyint unsigned', $tipos['camisa_convocacao']->t);
        $this->assertSame('NO', $tipos['camisa_convocacao']->n);
        $this->assertSame('bigint unsigned', $tipos['id_usuario']->t);

        $unicos = collect(DB::select("SELECT INDEX_NAME i, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) c FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_convocacao' AND NON_UNIQUE = 0 GROUP BY INDEX_NAME"))->pluck('c', 'i');
        $this->assertSame('id_campeonato,id_atleta', $unicos['convocacao_campeonato_atleta_unique']);
        $this->assertSame('id_campeonato,id_time,camisa_convocacao', $unicos['convocacao_camisa_unique']);

        $fks = collect(DB::select("SELECT CONSTRAINT_NAME n, DELETE_RULE d FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_convocacao'"))->pluck('d', 'n');
        $this->assertEqualsCanonicalizing(
            ['fk_convocacao_campeonato', 'fk_convocacao_time', 'fk_convocacao_atleta', 'fk_convocacao_usuario'], $fks->keys()->all());
        $this->assertSame(['NO ACTION'], $fks->unique()->values()->all());
    }

    // ---------- convocar ----------

    public function test_convoca_com_a_camisa_quem_e_quando(): void
    {
        $ana = $this->atleta('Ana', 15, $this->idSub15M);
        $bia = $this->atleta('Bia', 14, $this->idSub15M);

        $this->convocar($this->azul, [$ana => 10, $bia => 7])
            ->assertRedirect(route('admin.campeonatos.convocados', [$this->liga, $this->azul]))
            ->assertSessionHas('sucesso', '2 atleta(s) convocado(s) pelo AACJ Sub-15.');

        $this->assertSame([$ana => 10, $bia => 7], $this->camisas($this->azul));
        $convocacao = Convocacao::where('id_atleta', $ana)->sole();
        $this->assertSame($this->admin->id_usuario, (int) $convocacao->id_usuario);
        $this->assertNotNull($convocacao->data_convocacao);

        // A tela lista os dois, pela camisa, e não oferece mais os dois na caixa
        $this->comoAdminLogado()->get(route('admin.campeonatos.convocados', [$this->liga, $this->azul]))
            ->assertOk()
            ->assertSeeInOrder(['Bia', 'Ana'])
            ->assertSee('2 convocado(s)')
            ->assertDontSee('id="conv_atleta_' . $ana . '"', false);
    }

    public function test_camisa_e_obrigatoria_de_1_a_99(): void
    {
        $ana = $this->atleta('Ana', 15, $this->idSub15M);

        $this->convocar($this->azul, [$ana => ''])->assertSessionHasErrors(['camisas.' . $ana => 'Informe o número da camisa de cada atleta convocado.']);
        $this->convocar($this->azul, [$ana => 100])->assertSessionHasErrors(['camisas.' . $ana => 'A camisa vai de 1 a 99.']);
        $this->convocar($this->azul, [$ana => 0])->assertSessionHasErrors(['camisas.' . $ana => 'A camisa vai de 1 a 99.']);
        $this->assertSame(0, Convocacao::count());
    }

    public function test_camisa_nao_repete_no_time_mas_pode_em_outro_time(): void
    {
        $ana  = $this->atleta('Ana', 15, $this->idSub15M);
        $bia  = $this->atleta('Bia', 15, $this->idSub15M);
        $caio = $this->atleta('Caio', 15, $this->idSub15M);
        $davi = $this->atleta('Davi', 15, $this->idSub15M);

        // No mesmo pedido: tudo ou nada
        $this->convocar($this->azul, [$ana => 10, $bia => 10])
            ->assertSessionHasErrors(['convocacao' => 'A camisa 10 foi escolhida para mais de um atleta.']);
        $this->assertSame(0, Convocacao::count());

        $this->convocar($this->azul, [$ana => 10])->assertSessionHasNoErrors();
        $this->convocar($this->azul, [$bia => 10, $caio => 11])
            ->assertSessionHasErrors(['convocacao' => 'A camisa 10 do AACJ Sub-15 já é de Ana.']);
        $this->assertSame([$ana => 10], $this->camisas($this->azul));

        // A camisa 10 do outro time é outra camisa
        $this->convocar($this->verde, [$davi => 10])->assertSessionHasNoErrors();
        $this->assertSame([$davi => 10], $this->camisas($this->verde));
    }

    public function test_um_time_por_atleta_em_cada_campeonato(): void
    {
        $ana = $this->atleta('Ana', 15, $this->idSub15M);
        $this->convocar($this->azul, [$ana => 10])->assertSessionHasNoErrors();

        $this->convocar($this->verde, [$ana => 5])->assertSessionHasErrors(['convocacao' =>
            'Ana já foi convocado pelo AACJ Sub-15 neste campeonato: é um time por atleta em cada campeonato.']);
        $this->convocar($this->azul, [$ana => 5])->assertSessionHasErrors(['convocacao' => 'Ana já está convocado pelo AACJ Sub-15.']);

        // Na caixa do outro time, aparece desabilitado com o motivo
        $this->comoAdminLogado()->get(route('admin.campeonatos.convocados', [$this->liga, $this->verde]))
            ->assertOk()
            ->assertSee('· convocado pelo AACJ Sub-15', false);

        // Em outro campeonato, pode ser convocado de novo (e com outra camisa)
        $copa = $this->campeonato('Copa', now()->toDateString());
        $this->participantes($copa, [$this->verde]);
        $this->convocar($this->verde, [$ana => 5], $copa)->assertSessionHasNoErrors();
        $this->assertSame(2, Convocacao::where('id_atleta', $ana)->count());
    }

    public function test_mais_velho_nao_e_convocado_e_o_mais_novo_pode_subir(): void
    {
        $caio = $this->atleta('Caio', 16, $this->idSub17M);
        $ana  = $this->atleta('Ana', 13, $this->idSub13M);

        $this->convocar($this->azul, [$caio => 9, $ana => 10])->assertSessionHasErrors(['convocacao' =>
            'Caio (16 anos) passa(m) da idade do campeonato (Sub-15 Masculino, até 15 anos): atleta mais velho não joga em categoria abaixo da idade dele.']);
        $this->assertSame(0, Convocacao::count());

        $this->convocar($this->azul, [$ana => 10])->assertSessionHasNoErrors();

        $this->comoAdminLogado()->get(route('admin.campeonatos.convocados', [$this->liga, $this->azul]))
            ->assertSee('Caio <span class="text-muted small">· acima da idade (16 anos)</span>', false);
    }

    public function test_idade_conta_pelo_ano_de_inicio_do_campeonato(): void
    {
        // 15 anos neste ano, 16 no ano que vem: entra na Liga Sub-15 deste ano, não na que começa no ano que vem
        $davi = $this->atleta('Davi', 15, $this->idSub15M);
        $proxima = $this->campeonato('Liga do ano que vem', now()->addYear()->startOfYear()->addDays(10)->toDateString());
        $this->participantes($proxima, [$this->azul]);

        $this->convocar($this->azul, [$davi => 10], $proxima)->assertSessionHasErrors('convocacao');
        $this->assertStringContainsString('Davi (16 anos)', session('errors')->first('convocacao'));
        $this->convocar($this->azul, [$davi => 10])->assertSessionHasNoErrors();
    }

    public function test_sexo_diferente_so_avisa(): void
    {
        $lia = $this->atleta('Lia', 15, $this->idSub15F, 'F');

        $this->convocar($this->azul, [$lia => 8])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('aviso', 'Lia é do sexo feminino; o campeonato é Sub-15 Masculino.');
        $this->assertSame([$lia => 8], $this->camisas($this->azul));
    }

    public function test_so_atleta_ativo_e_convocado(): void
    {
        $ana = $this->atleta('Ana', 15, $this->idSub15M);
        DB::table('tbl_atletas')->where('id_atleta', $ana)->update(['status_atleta' => 'INATIVO']);

        $this->convocar($this->azul, [$ana => 10])->assertSessionHasErrors(['convocacao' => 'Escolha só atletas ativos.']);
    }

    public function test_so_time_interno_e_participante_convoca(): void
    {
        $ana = $this->atleta('Ana', 15, $this->idSub15M);

        $this->comoAdminLogado()->get(route('admin.campeonatos.convocados', [$this->liga, $this->visitante]))
            ->assertRedirect(route('admin.campeonatos.times', $this->liga))
            ->assertSessionHas('erro', 'Times externos não possuem atletas da associação: não há convocação.');
        $this->convocar($this->preto, [$ana => 10])
            ->assertSessionHas('erro', 'O Time Preto não participa do campeonato Liga.');
        $this->assertSame(0, Convocacao::count());
    }

    // ---------- camisa e remoção ----------

    public function test_troca_a_camisa_sem_repetir_no_time(): void
    {
        $ana = $this->atleta('Ana', 15, $this->idSub15M);
        $bia = $this->atleta('Bia', 15, $this->idSub15M);
        $this->convocar($this->azul, [$ana => 10, $bia => 7]);

        $this->trocarCamisa($ana, 9)->assertSessionHas('sucesso', 'Camisa de Ana atualizada para 9.');
        $this->trocarCamisa($ana, 7)->assertSessionHasErrors(['convocacao' => 'A camisa 7 do AACJ Sub-15 já é de Bia.']);
        $this->trocarCamisa($ana, 9)->assertSessionHasNoErrors(); // a própria camisa não conta como repetida
        $this->assertSame([$ana => 9, $bia => 7], $this->camisas($this->azul));

        // Atleta que não está convocado por este time: 404
        $this->comoAdminLogado()->patch(route('admin.campeonatos.convocados.camisa', [$this->liga, $this->verde, $ana]), ['camisa_convocacao' => 3])
            ->assertNotFound();
    }

    public function test_remove_da_convocacao(): void
    {
        $ana = $this->atleta('Ana', 15, $this->idSub15M);
        $this->convocar($this->azul, [$ana => 10]);

        $this->comoAdminLogado()->delete(route('admin.campeonatos.convocados.remover', [$this->liga, $this->azul, $ana]))
            ->assertSessionHas('sucesso', 'Ana saiu da convocação do AACJ Sub-15.');
        $this->assertSame(0, Convocacao::count());

        // Depois de sair, pode ser convocado pelo outro time
        $this->convocar($this->verde, [$ana => 10])->assertSessionHasNoErrors();
    }

    // ---------- atleta inativado e exclusão ----------

    public function test_convocado_inativado_fica_na_lista_sem_contar(): void
    {
        $ana = $this->atleta('Ana', 15, $this->idSub15M);
        $bia = $this->atleta('Bia', 15, $this->idSub15M);
        $this->convocar($this->azul, [$ana => 10, $bia => 7]);
        DB::table('tbl_atletas')->where('id_atleta', $ana)->update(['status_atleta' => 'INATIVO']);

        $this->comoAdminLogado()->get(route('admin.campeonatos.convocados', [$this->liga, $this->azul]))
            ->assertSee('1 convocado(s) (1 inativo(s), não contam)')
            ->assertSee('Inativo');

        // O inativo guarda a camisa: ninguém mais pega o 10 enquanto ele estiver na convocação
        $caio = $this->atleta('Caio', 15, $this->idSub15M);
        $this->convocar($this->azul, [$caio => 10])->assertSessionHasErrors(['convocacao' => 'A camisa 10 do AACJ Sub-15 já é de Ana.']);

        // Bloco "Convocação" da tela de jogos do campeonato conta só os ativos
        $this->comoAdminLogado()->get(route('admin.campeonatos.times', $this->liga))
            ->assertOk()
            ->assertSee('id="blocoConvocacao"', false)
            ->assertSeeInOrder(['AACJ Sub-15', '1 convocado(s)', 'AACJ Sub-15 B', 'Sem convocados']);
    }

    public function test_exclusao_definitiva_do_atleta_apaga_a_convocacao(): void
    {
        $ana = $this->atleta('Ana', 15, $this->idSub15M);
        $this->convocar($this->azul, [$ana => 10]);

        $this->assertTrue(Atleta::findOrFail($ana)->excluirComDependencias());
        $this->assertSame(0, Convocacao::count());
    }

    public function test_time_e_campeonato_com_convocados_nao_sao_excluidos(): void
    {
        $copa = $this->campeonato('Copa', now()->toDateString());
        $novo = $this->time('AACJ Sub-13', 'INTERNO');
        $this->participantes($copa, [$novo]);
        $ana = $this->atleta('Ana', 15, $this->idSub15M);
        $this->convocar($novo, [$ana => 10], $copa);

        $this->comoAdminLogado()->delete(route('admin.times.destroy', $novo))
            ->assertSessionHas('erro', 'O time AACJ Sub-13 tem 1 atleta(s) convocado(s) em campeonatos. Remova a convocação antes de excluir.');
        $this->comoAdminLogado()->delete(route('admin.campeonatos.destroy', $copa))
            ->assertSessionHas('erro', 'O campeonato Copa tem 1 atleta(s) convocado(s). Remova a convocação antes de excluir.');

        $this->assertDatabaseHas('tbl_time', ['id_time' => $novo]);
        $this->assertDatabaseHas('tbl_campeonato', ['id_campeonato' => $copa]);
    }

    public function test_time_com_convocados_nao_sai_dos_participantes(): void
    {
        $ana = $this->atleta('Ana', 15, $this->idSub15M);
        $this->convocar($this->azul, [$ana => 10]);

        $dados = [
            'nome_campeonato' => 'Liga', 'tipo_campeonato' => 'MATA-MATA', 'id_categoria' => $this->idSub15M,
            'data_inicio_campeonato' => now()->toDateString(), 'data_fim_campeonato' => now()->addMonth()->toDateString(),
        ];

        $this->comoAdminLogado()->put(route('admin.campeonatos.update', $this->liga), [...$dados, 'times' => [$this->verde, $this->visitante]])
            ->assertSessionHasErrors(['times' => 'Não é possível tirar do campeonato time com atletas convocados: AACJ Sub-15 (1 convocado(s)). Remova a convocação do time antes.']);
        $this->assertDatabaseHas('tbl_campeonato_time', ['id_campeonato' => $this->liga, 'id_time' => $this->azul]);

        // Tirar o time sem convocados continua valendo
        $this->comoAdminLogado()->put(route('admin.campeonatos.update', $this->liga), [...$dados, 'times' => [$this->azul, $this->visitante]])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('tbl_campeonato_time', ['id_campeonato' => $this->liga, 'id_time' => $this->verde]);
    }

    // ---------- ajudantes ----------

    private function comoAdminLogado(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    // POST da caixa "Convocar atletas": [id_atleta => camisa]
    private function convocar(int $idTime, array $camisas, ?int $idCampeonato = null)
    {
        return $this->comoAdminLogado()->post(route('admin.campeonatos.convocados.convocar', [$idCampeonato ?? $this->liga, $idTime]), [
            'atletas' => array_keys($camisas),
            'camisas' => $camisas,
        ]);
    }

    private function trocarCamisa(int $idAtleta, int $camisa)
    {
        return $this->comoAdminLogado()->patch(route('admin.campeonatos.convocados.camisa', [$this->liga, $this->azul, $idAtleta]), [
            'camisa_convocacao' => $camisa,
        ]);
    }

    // Convocados do time na Liga: [id_atleta => camisa], pelo id do atleta
    private function camisas(int $idTime): array
    {
        return Convocacao::where('id_campeonato', $this->liga)->where('id_time', $idTime)->orderBy('id_atleta')
            ->pluck('camisa_convocacao', 'id_atleta')->map(fn ($c) => (int) $c)->all();
    }

    private function atleta(string $nome, int $idade, int $idCategoria, string $sexo = 'M'): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade($idade), $sexo, 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
        $this->colocarNaCategoria($id, $idCategoria);

        return $id;
    }

    private function campeonato(string $nome, string $inicio): int
    {
        return DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub15M, 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png',
            'nome_campeonato' => $nome, 'organizador_campeonato' => 'Liga', 'tipo_campeonato' => 'MATA-MATA',
            'data_inicio_campeonato' => $inicio, 'data_fim_campeonato' => now()->parse($inicio)->addMonth()->toDateString(),
            'local_evento' => 'Campo A',
        ]);
    }

    private function participantes(int $idCampeonato, array $idsTimes): void
    {
        foreach ($idsTimes as $idTime) {
            DB::table('tbl_campeonato_time')->insert(['id_campeonato' => $idCampeonato, 'id_time' => $idTime]);
        }
    }

    private function time(string $nome, string $tipo): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub15M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo,
            'status_time' => 'ATIVO',
        ]);
    }
}
