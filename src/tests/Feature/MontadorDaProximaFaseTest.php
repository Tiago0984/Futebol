<?php

namespace Tests\Feature;

use App\Models\Jogo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase I, Etapa I3 (dono do projeto, 21/10/2026): montar a próxima fase do mata-mata pelos classificados da
 * tabela (1º × último; com grupos, o cruzamento 1º A × 2º B) ou pelos vencedores da fase anterior; jogo único ou
 * ida e volta (o mais bem colocado decide em casa); disputa de 3º lugar opcional; número de times que não fecha
 * a chave é recusado.
 */
class MontadorDaProximaFaseTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private int $idSub15M;
    private int $copa;
    private array $t = [];
    private string $sabado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->admin()->create();
        $this->idSub15M = $this->idCategoria('Sub-15', 'M');
        $this->sabado   = today()->next(\Carbon\CarbonInterface::SATURDAY)->toDateString();
        $this->copa     = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub15M, 'nome_campeonato' => 'Copa', 'tipo_campeonato' => 'COPA',
            'data_inicio_campeonato' => today()->subMonth()->toDateString(), 'data_fim_campeonato' => today()->addMonths(6)->toDateString(),
            'local_evento' => 'Arena Municipal', 'status_campeonato' => 'ATIVO',
        ]);
        foreach (['Alfa', 'Beta', 'Gama', 'Delta'] as $nome) {
            $this->t[$nome] = DB::table('tbl_time')->insertGetId([
                'id_categoria' => $this->idSub15M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => 'EXTERNO', 'status_time' => 'ATIVO',
            ]);
        }
        $this->participantes($this->copa, array_values($this->t), $this->idSub15M);
    }

    public function test_tabela_unica_semifinal_final_e_terceiro_lugar(): void
    {
        $this->classificados(4);
        // Alfa 9, Beta 6, Gama 3, Delta 0
        foreach ([['Alfa', 'Beta'], ['Alfa', 'Gama'], ['Alfa', 'Delta'], ['Beta', 'Gama'], ['Beta', 'Delta'], ['Gama', 'Delta']] as [$casa, $fora]) {
            $this->jogoComPlacar('GRUPOS', $casa, $fora, 1, 0);
        }

        $this->comoAdmin()->get(route('admin.campeonatos.mata-mata', $this->copa))->assertOk()->assertSee('Montar próxima fase');
        $this->comoAdmin()->get($this->urlPrevia())->assertOk()
            ->assertSee('Próxima fase: Semifinal')->assertSee('2</strong> confronto(s)', false)->assertDontSee('disputa de 3º lugar');

        // Semifinais: 1º × 4º e 2º × 3º, o mais bem colocado como mandante
        $this->gerar()->assertSessionHas('sucesso', 'Semifinal do Sub-15 Masculino: 2 jogo(s) em 2 confronto(s): 2 publicado(s) (sem time da AACJ).');
        $semis = $this->jogos('SEMIFINAL');
        $this->assertSame([['Alfa', 'Delta'], ['Beta', 'Gama']], $semis->map(fn ($j) => [$j->timeCasa->nome_time, $j->timeVisitante->nome_time])->all());
        $this->assertSame(['13:00:00', '14:00:00'], $semis->map(fn ($j) => $j->evento->horario_inicio_evento_calendario)->all());

        // De novo: a semifinal já existe, e os confrontos ainda não têm vencedor
        $this->gerar()->assertSessionHas('erro', fn ($e) => str_contains($e, 'Semifinal: 2 confronto(s) sem vencedor'));

        // Alfa 2 × 0 Delta; Beta 1 × 1 Gama, prorr. 0 × 0, pên. 3 × 4: Gama passa
        $semis[0]->update(['placar_time_casa_jogos' => 2, 'placar_time_visitante_jogos' => 0]);
        $semis[1]->update(['placar_time_casa_jogos' => 1, 'placar_time_visitante_jogos' => 1, 'prorrogacao_time_casa_jogos' => 0,
            'prorrogacao_time_visitante_jogos' => 0, 'penaltis_time_casa_jogos' => 3, 'penaltis_time_visitante_jogos' => 4]);

        $this->comoAdmin()->get($this->urlPrevia())->assertOk()
            ->assertSee('Próxima fase: Final')->assertSee('disputa de 3º lugar')
            ->assertSee('Alfa (Venceu Alfa x Delta)')->assertSee('Gama (Venceu Beta x Gama)');

        // Final com a disputa de 3º (os perdedores, o mais bem colocado em casa), o 3º antes da final
        $this->gerar(['terceiro' => 1])->assertSessionHasNoErrors();
        $this->assertSame([['Alfa', 'Gama']], $this->jogos('FINAL')->map(fn ($j) => [$j->timeCasa->nome_time, $j->timeVisitante->nome_time])->all());
        $terceiro = $this->jogos('TERCEIRO')->sole();
        $this->assertSame(['Beta', 'Delta', '13:00:00'], [$terceiro->timeCasa->nome_time, $terceiro->timeVisitante->nome_time, $terceiro->evento->horario_inicio_evento_calendario]);
        $this->assertSame('14:00:00', $this->jogos('FINAL')->sole()->evento->horario_inicio_evento_calendario);

        // Com a final, não há próxima fase
        $this->comoAdmin()->get($this->urlPrevia())->assertSee('O Sub-15 Masculino já tem a final.');
    }

    public function test_grupos_com_cruzamento_e_ida_e_volta(): void
    {
        $grupoA = $this->grupo('Grupo A', ['Alfa', 'Beta']);
        $grupoB = $this->grupo('Grupo B', ['Gama', 'Delta']);
        $this->classificados(2);
        $this->jogoComPlacar('GRUPOS', 'Alfa', 'Beta', 2, 0);   // 1º A Alfa, 2º A Beta
        $this->jogoComPlacar('GRUPOS', 'Gama', 'Delta', 1, 0);  // 1º B Gama, 2º B Delta

        $this->comoAdmin()->get($this->urlPrevia(['ida_e_volta' => 1]))->assertOk()
            ->assertSee('Alfa (1º Grupo A)')->assertSee('Delta (2º Grupo B)');

        $this->gerar(['ida_e_volta' => 1, 'dias_volta' => 7])->assertSessionHasNoErrors();
        $semis = $this->jogos('SEMIFINAL');
        $this->assertCount(4, $semis);

        // 1º A × 2º B e 1º B × 2º A; a ida na casa do pior, a volta (ligada à ida) na casa do mais bem colocado
        $voltas = $semis->whereNotNull('id_jogo_ida')->values();
        $this->assertSame([['Alfa', 'Delta'], ['Gama', 'Beta']], $voltas->map(fn ($j) => [$j->timeCasa->nome_time, $j->timeVisitante->nome_time])->all());
        foreach ($voltas as $volta) {
            $ida = $semis->firstWhere('id_jogo', $volta->id_jogo_ida);
            $this->assertSame([$volta->id_time_visitante, $volta->id_time_casa], [$ida->id_time_casa, $ida->id_time_visitante]);
            $this->assertSame(7, (int) $ida->evento->data_evento_calendario->diffInDays($volta->evento->data_evento_calendario));
            $this->assertSame('Semifinal · ida', $ida->fresh()->fase_completa);
        }
        $this->assertNotNull($grupoA);
        $this->assertNotNull($grupoB);
    }

    public function test_confrontos_escolhidos_na_previa(): void
    {
        $this->classificados(4);
        foreach ([['Alfa', 'Beta'], ['Alfa', 'Gama'], ['Alfa', 'Delta'], ['Beta', 'Gama'], ['Beta', 'Delta'], ['Gama', 'Delta']] as [$casa, $fora]) {
            $this->jogoComPlacar('GRUPOS', $casa, $fora, 1, 0);
        }

        // Sorteio montado à mão: Alfa × Beta e Gama × Delta
        $this->gerar(['pares' => [[$this->t['Alfa'], $this->t['Beta']], [$this->t['Gama'], $this->t['Delta']]]])->assertSessionHasNoErrors();
        $this->assertSame([['Alfa', 'Beta'], ['Gama', 'Delta']], $this->jogos('SEMIFINAL')->map(fn ($j) => [$j->timeCasa->nome_time, $j->timeVisitante->nome_time])->all());
    }

    public function test_data_padrao_depois_do_ultimo_jogo_e_aviso_de_time_com_dois_jogos_no_dia(): void
    {
        $this->classificados(4);
        foreach ([['Alfa', 'Beta'], ['Alfa', 'Gama'], ['Alfa', 'Delta'], ['Beta', 'Gama'], ['Beta', 'Delta'], ['Gama', 'Delta']] as [$casa, $fora]) {
            $this->jogoComPlacar('GRUPOS', $casa, $fora, 1, 0);
        }
        // Um jogo da categoria (sem placar) daqui a 3 semanas: a próxima fase sugere o sábado seguinte a ele
        $ultimo = today()->addWeeks(3);
        Jogo::criarComEvento($this->admin->id_usuario, [
            'id_campeonato' => $this->copa, 'fase_jogo' => 'GRUPOS', 'id_time_casa' => $this->t['Alfa'], 'id_time_visitante' => $this->t['Gama'],
        ], [
            'titulo_evento_calendario' => 'Alfa x Gama', 'tipo_evento_calendario' => 'JOGO', 'id_categoria' => $this->idSub15M,
            'data_evento_calendario' => $ultimo->toDateString(), 'horario_inicio_evento_calendario' => '10:00', 'status_evento_calendario' => 'ATIVO',
        ]);

        $this->comoAdmin()->get(route('admin.campeonatos.proxima-fase', [$this->copa, $this->idSub15M]))->assertOk()
            ->assertSee('value="' . $ultimo->copy()->next(\Carbon\CarbonInterface::SATURDAY)->toDateString() . '"', false);

        // Escolhendo o mesmo dia do jogo: aviso (não bloqueia) para o Alfa e o Gama, que já jogam nele
        $this->comoAdmin()->get($this->urlPrevia(['data' => $ultimo->toDateString()]))->assertOk()
            ->assertSee('Alfa já tem jogo em ' . $ultimo->format('d/m') . ' às 10:00 (Alfa x Gama).')
            ->assertSee('Gama já tem jogo em ' . $ultimo->format('d/m') . ' às 10:00 (Alfa x Gama).')
            ->assertSee('Criar 2 jogo(s)');
    }

    public function test_recusas(): void
    {
        // Sem classificados definidos
        $this->gerar()->assertSessionHas('erro', fn ($e) => str_contains($e, 'Defina quantos passam por grupo (chave) no Sub-15 Masculino'));

        // 3 times não fecham a chave
        $this->classificados(3);
        $this->gerar()->assertSessionHas('erro', fn ($e) => str_contains($e, '3 time(s) não fecham uma chave (2, 4, 8, 16 ou 32)'));

        // Time repetido nos confrontos escolhidos
        $this->classificados(4);
        $this->gerar(['pares' => [[$this->t['Alfa'], $this->t['Beta']], [$this->t['Alfa'], $this->t['Delta']]]])
            ->assertSessionHas('erro', fn ($e) => str_contains($e, 'Cada time que entra precisa estar em um confronto só'));

        // Ida ativa com a volta oculta: a fase não tem confronto completo
        $this->jogoComPlacar('OITAVAS', 'Alfa', 'Beta', 1, 0);
        $ida = Jogo::where('fase_jogo', 'OITAVAS')->sole();
        $this->jogoComPlacar('OITAVAS', 'Beta', 'Alfa', 0, 0);
        $volta = Jogo::with('evento')->where('fase_jogo', 'OITAVAS')->where('id_jogo', '<>', $ida->id_jogo)->sole();
        $volta->update(['id_jogo_ida' => $ida->id_jogo]);
        $volta->evento->update(['status_evento_calendario' => 'INATIVO']);
        $this->gerar()->assertSessionHas('erro', fn ($e) => str_contains($e, 'A fase "Oitavas de final" não tem confrontos completos e ativos'));
        DB::table('tbl_jogos')->update(['id_jogo_ida' => null]);
        DB::table('tbl_jogos')->delete();

        // Campeonato inativo
        DB::table('tbl_campeonato')->where('id_campeonato', $this->copa)->update(['status_campeonato' => 'INATIVO']);
        $this->gerar()->assertSessionHas('erro', fn ($e) => str_contains($e, 'está inativo'));

        $this->assertSame(0, Jogo::count());
    }

    // ---------- auxiliares ----------

    private function comoAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    private function opcoes(array $extra = []): array
    {
        return array_merge(['ida_e_volta' => 0, 'data' => $this->sabado, 'dias_volta' => 7, 'horario' => '13:00',
            'intervalo' => 60, 'duracao' => '', 'local' => '', 'terceiro' => 0], $extra);
    }

    private function urlPrevia(array $extra = []): string
    {
        return route('admin.campeonatos.proxima-fase', [$this->copa, $this->idSub15M, ...$this->opcoes($extra)]);
    }

    private function gerar(array $extra = [])
    {
        return $this->comoAdmin()->post(route('admin.campeonatos.proxima-fase.gerar', [$this->copa, $this->idSub15M]), $this->opcoes($extra));
    }

    private function classificados(int $quantos): void
    {
        DB::table('tbl_campeonato_categoria')->where('id_campeonato', $this->copa)->where('id_categoria', $this->idSub15M)
            ->update(['classificados_campeonato_categoria' => $quantos]);
    }

    private function grupo(string $nome, array $times): int
    {
        $id = DB::table('tbl_campeonato_grupo')->insertGetId(['id_campeonato' => $this->copa, 'id_categoria' => $this->idSub15M, 'nome_campeonato_grupo' => $nome]);
        DB::table('tbl_campeonato_time')->where('id_campeonato', $this->copa)->whereIn('id_time', array_map(fn ($n) => $this->t[$n], $times))
            ->update(['id_campeonato_grupo' => $id]);

        return $id;
    }

    // Jogo entre externos (nasce publicado), com placar, já jogado
    private function jogoComPlacar(string $fase, string $casa, string $fora, int $golsCasa, int $golsFora): void
    {
        Jogo::criarComEvento($this->admin->id_usuario, [
            'id_campeonato' => $this->copa, 'fase_jogo' => $fase, 'id_time_casa' => $this->t[$casa], 'id_time_visitante' => $this->t[$fora],
            'placar_time_casa_jogos' => $golsCasa, 'placar_time_visitante_jogos' => $golsFora,
        ], [
            'titulo_evento_calendario' => "{$casa} x {$fora}", 'tipo_evento_calendario' => 'JOGO', 'id_categoria' => $this->idSub15M,
            'data_evento_calendario' => today()->subDays(7)->toDateString(), 'horario_inicio_evento_calendario' => '10:00', 'status_evento_calendario' => 'ATIVO',
        ]);
    }

    // Jogos de uma fase, pela data e pelo horário
    private function jogos(string $fase)
    {
        return Jogo::with(['evento', 'timeCasa', 'timeVisitante'])->where('fase_jogo', $fase)->ordenadosPelaData()->orderBy('tbl_jogos.id_jogo')->get()->values();
    }
}
