<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\Jogo;
use App\Models\Notificacao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * O atleta chega 1 hora antes do jogo (professor, 09/10/2026; Jogo::ANTECEDENCIA_MINUTOS): a chegada vai no
 * aviso de inscrição e de publicação, no de alteração do horário de início e na tela do jogo no admin.
 * Evento que não é jogo e jogo sem horário não têm chegada.
 */
class JogoChegadaTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private string $dia;
    private int $idSub11M;
    private int $azul;
    private int $visitante;
    private int $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin     = User::factory()->admin()->create();
        $this->dia       = now()->addDays(10)->toDateString();
        $this->idSub11M  = $this->idCategoria('Sub-11', 'M');
        $time = fn (string $nome, string $tipo) => DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub11M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo,
        ]);
        $this->azul      = $time('Time Azul', 'INTERNO');
        $this->visitante = $time('Time Visitante', 'EXTERNO');

        $this->ana = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'ATIVO');
        $this->colocarNaCategoria($this->ana, $this->idSub11M);
    }

    public function test_chegada_e_uma_hora_antes_do_inicio(): void
    {
        $this->assertSame('12:00', Jogo::chegadaPara('13:00:00'));
        $this->assertSame('Chegue às 08:30 (1 hora antes).', Jogo::fraseDaChegada('09:30'));
        $this->assertNull(Jogo::chegadaPara(null));
        $this->assertSame('', Jogo::fraseDaChegada(null));
    }

    public function test_publicar_avisa_a_chegada_e_a_tela_do_jogo_mostra(): void
    {
        $jogo = $this->criarJogo('13:00');

        $this->assertStringEndsWith('. Chegue às 12:00 (1 hora antes). Você joga pelo Time Azul.', Notificacao::sole()->mensagem_notificacao);
        $this->actingAs($this->admin, 'admin')->get(route('admin.calendario.eventos.show', $jogo->id_evento))
            ->assertOk()
            ->assertSee('<span class="text-muted small d-block">Chegada</span>12:00', false);
    }

    public function test_jogo_sem_horario_nao_tem_chegada(): void
    {
        $jogo = $this->criarJogo('');

        $this->assertStringNotContainsString('Chegue', Notificacao::sole()->mensagem_notificacao);
        $this->actingAs($this->admin, 'admin')->get(route('admin.calendario.eventos.show', $jogo->id_evento))
            ->assertOk()
            ->assertDontSee('d-block">Chegada</span>', false);
    }

    public function test_alteracao_so_do_local_nao_repete_a_chegada(): void
    {
        $jogo = $this->criarJogo('13:00');
        Notificacao::query()->delete();

        $this->actingAs($this->admin, 'admin')->put(route('admin.jogos.update', $jogo->id_jogo), $this->dadosJogo('13:00', ['local_evento_calendario' => 'Campo B']))
            ->assertSessionHas('sucesso');

        $this->assertStringEndsWith('Mudanças: local Campo A → Campo B.', Notificacao::sole()->mensagem_notificacao);
    }

    public function test_evento_que_nao_e_jogo_nao_tem_chegada(): void
    {
        $treino = EventoCalendario::criarPor(null, [
            'titulo_evento_calendario' => 'Treino', 'tipo_evento_calendario' => 'TREINO', 'data_evento_calendario' => $this->dia,
            'horario_inicio_evento_calendario' => '09:00', 'status_evento_calendario' => 'ATIVO',
        ]);
        $treino->inscrever($this->ana, 'INDIVIDUAL', null);

        $this->assertStringNotContainsString('Chegue', Notificacao::sole()->mensagem_notificacao);
    }

    // ---------- apoio ----------

    private function dadosJogo(string $inicio, array $extra = []): array
    {
        return array_merge([
            'id_campeonato' => 'AMISTOSO', 'id_categoria' => $this->idSub11M,
            'id_time_casa' => $this->azul, 'id_time_visitante' => $this->visitante,
            'data_evento_calendario' => $this->dia, 'horario_inicio_evento_calendario' => $inicio,
            'local_evento_calendario' => 'Campo A',
        ], $extra);
    }

    // Cria o amistoso, escolhe a Ana (entra no Azul, Fase G, Etapa 3) e publica: a publicação manda a INSCRICAO
    private function criarJogo(string $inicio): Jogo
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.jogos.store'), $this->dadosJogo($inicio))->assertSessionHasNoErrors();
        $jogo = Jogo::sole();
        $this->actingAs($this->admin, 'admin')->post(route('admin.calendario.eventos.escalacao.escolher', $jogo->id_evento), ['atletas' => [$this->ana]])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin, 'admin')->patch(route('admin.jogos.publicar', $jogo->id_jogo))->assertSessionHas('sucesso');

        return $jogo;
    }
}
