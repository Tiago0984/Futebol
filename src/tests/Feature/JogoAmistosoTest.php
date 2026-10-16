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
 * Fase G, Etapa 3: amistoso é um time da AACJ contra um adversário externo, sem lista automática (nem elenco
 * nem convocação): o admin escolhe os atletas no próprio jogo ("Escolher atletas"), e todos jogam pelo time da
 * AACJ do jogo. Sem limite de idade e sem camisa.
 */
class JogoAmistosoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private User $admin;
    private string $dia;
    private int $idSub13M;
    private int $azul;
    private int $preto;
    private int $visitante;
    private int $rival;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin     = User::factory()->admin()->create();
        $this->dia       = now()->addDays(10)->toDateString();
        $this->idSub13M  = $this->idCategoria('Sub-13', 'M');
        $this->azul      = $this->time('AACJ Sub-13', 'INTERNO');
        $this->preto     = $this->time('AACJ Sub-15', 'INTERNO');
        $this->visitante = $this->time('Rival FC', 'EXTERNO');
        $this->rival     = $this->time('Outro Rival', 'EXTERNO');
    }

    // ---------- criar ----------

    public function test_amistoso_nasce_rascunho_sem_inscritos_e_a_tela_mostra_escolher_atletas(): void
    {
        $this->atleta('Ana'); // atleta ativo da categoria: não entra sozinho (escolhido no jogo)

        $this->postJogo()
            ->assertSessionHas('sucesso', 'Jogo registrado como rascunho. Escolha os atletas do AACJ Sub-13 em "Escolher atletas", nesta tela.'
                . ' Os atletas serão avisados ao publicar.')
            ->assertSessionMissing('aviso');

        $jogo = Jogo::sole();
        $this->assertSame(0, $jogo->evento->inscricoes()->count());
        $this->assertFalse($jogo->evento->estaPublicado());

        $this->tela($jogo)
            ->assertSee('Escolher atletas')
            ->assertSee('· jogam pelo AACJ Sub-13', false)
            ->assertSee('Amistoso: escolha quem vai jogar em')
            ->assertSee(route('admin.calendario.eventos.escalacao.escolher', $jogo->id_evento), false)
            ->assertDontSee('Preencher pela')
            ->assertDontSee('Adicionar todos de uma categoria')
            ->assertDontSee('Inscrever atleta');
    }

    public function test_amistoso_so_entre_um_time_da_aacj_e_um_externo(): void
    {
        $this->postJogo(['id_time_visitante' => $this->preto])
            ->assertSessionHasErrors(['id_time_visitante' => 'Amistoso é entre um time da AACJ e um adversário externo: AACJ contra AACJ é treino (crie pelo Calendário).']);
        $this->postJogo(['id_time_casa' => $this->rival])
            ->assertSessionHasErrors(['id_time_visitante' => 'Amistoso precisa de um time da AACJ (interno): dois times externos não têm atletas da escolinha.']);
        $this->assertSame(0, Jogo::count());

        // A AACJ pode ser a visitante
        $this->postJogo(['id_time_casa' => $this->visitante, 'id_time_visitante' => $this->azul])->assertSessionHasNoErrors();
        $this->assertSame(1, Jogo::count());
    }

    public function test_editar_amistoso_antigo_de_aacj_contra_aacj_pede_um_externo(): void
    {
        // Antes da regra (dados de teste antigos): criado direto, sem a tela
        $jogo = $this->jogoDireto($this->azul, $this->preto);

        $this->putJogo($jogo, ['horario_inicio_evento_calendario' => '20:00'])
            ->assertSessionHasErrors('id_time_visitante');
        $this->putJogo($jogo, ['id_time_visitante' => $this->visitante])->assertSessionHasNoErrors();
    }

    // ---------- escolher atletas ----------

    public function test_escolher_inscreve_ja_escalado_no_time_da_aacj_e_ignora_quem_ja_esta(): void
    {
        $ana  = $this->atleta('Ana');
        $bia  = $this->atleta('Bia');
        $caio = $this->atleta('Caio', 16, $this->idCategoria('Sub-17', 'M')); // amistoso: sem limite de idade
        $jogo = $this->criarAmistoso();

        $this->escolher($jogo, [$ana, $bia])
            ->assertSessionHas('sucesso', '2 atleta(s) inscrito(s) e escalado(s) no AACJ Sub-13. 0 atleta(s) notificado(s).');
        $this->escolher($jogo, [$ana, $caio])
            ->assertSessionHas('sucesso', '1 atleta(s) inscrito(s) e escalado(s) no AACJ Sub-13. 0 atleta(s) notificado(s).');
        $this->escolher($jogo, [$ana])->assertSessionHas('sucesso', 'Os atletas escolhidos já estavam inscritos.');

        $this->assertSame([
            $ana  => ['INDIVIDUAL', $this->azul],
            $bia  => ['INDIVIDUAL', $this->azul],
            $caio => ['INDIVIDUAL', $this->azul],
        ], $this->inscricoes($jogo));
        $this->assertSame(0, Notificacao::count()); // rascunho
    }

    public function test_escolher_valida_os_atletas(): void
    {
        $inativo = $this->atleta('Ivo', status: 'INATIVO');
        $jogo = $this->criarAmistoso();

        $this->escolher($jogo, [])->assertSessionHasErrors(['atletas' => 'Escolha pelo menos um atleta.']);
        $this->escolher($jogo, [$inativo])->assertSessionHasErrors('atletas.0');
        $this->assertSame(0, $jogo->evento->inscricoes()->count());
    }

    public function test_escolher_com_conflito_pede_confirmacao(): void
    {
        $ana  = $this->atleta('Ana');
        $jogo = $this->criarAmistoso();
        EventoCalendario::criarPor(null, [
            'titulo_evento_calendario' => 'Treino', 'tipo_evento_calendario' => 'TREINO', 'data_evento_calendario' => $this->dia,
            'horario_inicio_evento_calendario' => '19:30', 'horario_fim_evento_calendario' => '20:30', 'status_evento_calendario' => 'ATIVO',
        ])->inscrever($ana, 'INDIVIDUAL', null, notificar: false);

        $this->escolher($jogo, [$ana])->assertSessionHas('conflitos_pendentes', fn ($p) => count($p['fortes']) === 1);
        $this->assertSame(0, $jogo->evento->inscricoes()->count());

        $this->escolher($jogo, [$ana], ['confirmar_conflito' => 1])->assertSessionHas('sucesso');
        $this->assertSame([$ana => ['INDIVIDUAL', $this->azul]], $this->inscricoes($jogo));
    }

    public function test_aacj_visitante_escala_no_time_dela(): void
    {
        $ana = $this->atleta('Ana');
        $this->postJogo(['id_time_casa' => $this->visitante, 'id_time_visitante' => $this->azul]);
        $jogo = Jogo::sole();

        $this->escolher($jogo, [$ana]);
        $this->assertSame([$ana => ['INDIVIDUAL', $this->azul]], $this->inscricoes($jogo));
    }

    public function test_inscrever_um_atleta_avulso_tambem_escala_no_time_da_aacj(): void
    {
        $ana  = $this->atleta('Ana');
        $jogo = $this->criarAmistoso();

        $this->actingAs($this->admin, 'admin')->post(route('admin.calendario.eventos.inscricoes.store', $jogo->id_evento), ['id_atleta' => $ana])
            ->assertSessionHas('sucesso', 'Atleta inscrito e escalado. 0 atleta(s) notificado(s).');
        $this->assertSame([$ana => ['INDIVIDUAL', $this->azul]], $this->inscricoes($jogo));
    }

    public function test_preencher_e_recusado_no_amistoso(): void
    {
        $jogo = $this->criarAmistoso();

        $this->actingAs($this->admin, 'admin')->post(route('admin.calendario.eventos.escalacao.elenco', $jogo->id_evento))
            ->assertSessionHas('erro', 'No amistoso, escolha os atletas em "Escolher atletas", nesta tela.');
    }

    // ---------- trocar time e publicar ----------

    public function test_trocar_o_time_da_aacj_passa_os_escolhidos_para_ele(): void
    {
        $ana  = $this->atleta('Ana');
        $bia  = $this->atleta('Bia');
        $jogo = $this->criarAmistoso();
        $this->escolher($jogo, [$ana, $bia]);

        $this->putJogo($jogo, ['id_time_casa' => $this->preto])
            ->assertSessionHas('sucesso', 'Jogo atualizado. 2 atleta(s) passaram para o AACJ Sub-15.');
        $this->assertSame([$ana => ['INDIVIDUAL', $this->preto], $bia => ['INDIVIDUAL', $this->preto]], $this->inscricoes($jogo));

        // Trocar só o adversário não mexe em ninguém
        $this->putJogo($jogo->fresh(), ['id_time_casa' => $this->preto, 'id_time_visitante' => $this->rival])
            ->assertSessionHas('sucesso', 'Jogo atualizado.');
        $this->assertSame(2, $jogo->evento->inscricoes()->where('id_time', $this->preto)->count());
    }

    public function test_publicar_avisa_os_escolhidos_com_o_time(): void
    {
        $ana  = $this->atleta('Ana');
        $jogo = $this->criarAmistoso();
        $this->escolher($jogo, [$ana]);

        $this->actingAs($this->admin, 'admin')->patch(route('admin.jogos.publicar', $jogo->id_jogo))
            ->assertSessionHas('sucesso', 'Jogo publicado. 1 atleta(s) notificado(s).');
        $this->assertStringEndsWith('. Você joga pelo AACJ Sub-13.', Notificacao::sole()->mensagem_notificacao);

        // Publicado: escolher de novo avisa na hora
        $bia = $this->atleta('Bia');
        $this->escolher($jogo, [$bia])->assertSessionHas('sucesso', '1 atleta(s) inscrito(s) e escalado(s) no AACJ Sub-13. 1 atleta(s) notificado(s).');
    }

    // ---------- ajudantes ----------

    private function atleta(string $nome, int $idade = 13, ?int $idCategoria = null, string $status = 'ATIVO'): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade($idade), 'M', $status);
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
        $this->colocarNaCategoria($id, $idCategoria ?? $this->idSub13M);

        return $id;
    }

    private function dadosJogo(array $extra = []): array
    {
        return array_merge([
            'id_campeonato'                    => 'AMISTOSO',
            'id_categoria'                     => $this->idSub13M,
            'id_time_casa'                     => $this->azul,
            'id_time_visitante'                => $this->visitante,
            'data_evento_calendario'           => $this->dia,
            'horario_inicio_evento_calendario' => '19:00',
            'local_evento_calendario'          => 'Campo A',
        ], $extra);
    }

    private function postJogo(array $extra = [])
    {
        return $this->actingAs($this->admin, 'admin')->post(route('admin.jogos.store'), $this->dadosJogo($extra));
    }

    private function criarAmistoso(): Jogo
    {
        $this->postJogo()->assertSessionHasNoErrors();

        return Jogo::latest('id_jogo')->first()->load('evento');
    }

    // Amistoso criado direto pelo model (sem a validação da tela), como os dados antigos
    private function jogoDireto(int $casa, int $visitante): Jogo
    {
        $evento = EventoCalendario::criarPor(null, [
            'titulo_evento_calendario' => Jogo::tituloPara($casa, $visitante), 'tipo_evento_calendario' => 'JOGO',
            'data_evento_calendario' => $this->dia, 'horario_inicio_evento_calendario' => '19:00',
            'local_evento_calendario' => 'Campo A', 'status_evento_calendario' => 'ATIVO',
        ]);

        return Jogo::create(['id_evento' => $evento->id_evento_calendario, 'id_time_casa' => $casa, 'id_time_visitante' => $visitante])->load('evento');
    }

    private function putJogo(Jogo $jogo, array $extra)
    {
        return $this->actingAs($this->admin, 'admin')->put(route('admin.jogos.update', $jogo->id_jogo),
            $this->dadosJogo(['id_time_casa' => $jogo->id_time_casa, 'id_time_visitante' => $jogo->id_time_visitante, ...$extra]));
    }

    private function escolher(Jogo $jogo, array $atletas, array $extra = [])
    {
        return $this->actingAs($this->admin, 'admin')
            ->post(route('admin.calendario.eventos.escalacao.escolher', $jogo->id_evento), ['atletas' => $atletas, ...$extra]);
    }

    private function tela(Jogo $jogo)
    {
        return $this->actingAs($this->admin, 'admin')->get(route('admin.calendario.eventos.show', $jogo->id_evento))->assertOk();
    }

    // Inscrições do jogo: [id_atleta => [origem, id_time]]
    private function inscricoes(Jogo $jogo): array
    {
        return DB::table('tbl_evento_atleta')->where('id_evento_calendario', $jogo->id_evento)->orderBy('id_atleta')->get()
            ->mapWithKeys(fn ($i) => [(int) $i->id_atleta => [$i->origem_evento_atleta, $i->id_time === null ? null : (int) $i->id_time]])
            ->all();
    }

    private function time(string $nome, string $tipo): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub13M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo,
        ]);
    }
}
