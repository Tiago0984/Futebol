<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\Jogo;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase 6, Etapa 3: escalação por jogo em tbl_evento_atleta.id_time.
 */
class JogoEscalacaoTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private int $idSub11M;
    private int $azul;
    private int $preto;
    private int $verde;
    private int $visitante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idSub11M  = $this->idCategoria('Sub-11', 'M');
        $this->azul      = $this->time('Time Azul', 'INTERNO');
        $this->preto     = $this->time('Time Preto', 'INTERNO');
        $this->verde     = $this->time('Time Verde', 'INTERNO');
        $this->visitante = $this->time('Time Visitante', 'EXTERNO');
    }

    // ---------- escalar um inscrito ----------

    public function test_escalar_e_tirar_da_escalacao(): void
    {
        $jogo = $this->jogo($this->azul, $this->preto);
        $idAtleta = $this->atleta('Ana');
        $jogo->evento->inscrever($idAtleta, 'INDIVIDUAL', null);

        $this->escalar($jogo, $idAtleta, $this->azul)->assertSessionHas('sucesso', 'Ana escalado(a) no Time Azul.');
        $this->assertSame($this->azul, $this->timeDe($jogo, $idAtleta));

        $this->escalar($jogo, $idAtleta, null)->assertSessionHas('sucesso', 'Ana ficou sem time.');
        $this->assertNull($this->timeDe($jogo, $idAtleta));
    }

    public function test_atleta_fica_num_time_so_do_jogo(): void
    {
        // Está nos dois elencos (como o atleta 12): escalar no outro time troca, não duplica
        $jogo = $this->jogo($this->azul, $this->preto);
        $idAtleta = $this->atleta('Bia', [$this->azul, $this->preto]);
        $jogo->evento->inscrever($idAtleta, 'INDIVIDUAL', null);

        $this->escalar($jogo, $idAtleta, $this->azul);
        $this->escalar($jogo, $idAtleta, $this->preto);

        $this->assertSame(1, $jogo->evento->inscricoes()->where('id_atleta', $idAtleta)->count());
        $this->assertSame($this->preto, $this->timeDe($jogo, $idAtleta));
    }

    public function test_nao_escala_em_time_externo_nem_fora_do_jogo(): void
    {
        $jogo = $this->jogo($this->azul, $this->visitante);
        $idAtleta = $this->atleta('Caio');
        $jogo->evento->inscrever($idAtleta, 'INDIVIDUAL', null);

        $this->escalar($jogo, $idAtleta, $this->visitante)
            ->assertSessionHas('erro', 'Time externo não tem atletas da escolinha: não dá para escalar nele.');
        $this->escalar($jogo, $idAtleta, $this->verde)
            ->assertSessionHas('erro', 'Escolha o mandante ou o visitante deste jogo.');

        $this->assertNull($this->timeDe($jogo, $idAtleta));
    }

    public function test_evento_que_nao_e_jogo_nao_tem_escalacao(): void
    {
        $treino = EventoCalendario::criarPor(null, [
            'titulo_evento_calendario' => 'Treino', 'tipo_evento_calendario' => 'TREINO',
            'data_evento_calendario' => now()->addDays(5)->toDateString(), 'status_evento_calendario' => 'ATIVO',
        ]);
        $idAtleta = $this->atleta('Davi');
        $treino->inscrever($idAtleta, 'INDIVIDUAL', null);

        $this->comoAdmin()
            ->patch(route('admin.calendario.eventos.inscricoes.time', [$treino->id_evento_calendario, $idAtleta]), ['id_time' => $this->azul])
            ->assertSessionHas('erro', 'Este evento não é um jogo: não tem escalação.');

        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $treino->id_evento_calendario))
            ->assertOk()->assertDontSee('Escalação:')->assertDontSee('— Sem time —');
    }

    // ---------- inscrever já escalando ----------

    public function test_inscrever_quem_nao_esta_inscrito_ja_escalando(): void
    {
        $jogo = $this->jogo($this->azul, $this->visitante);
        $idAtleta = $this->atleta('Eva');
        $outro    = $this->atleta('Fabio');

        $this->comoAdmin()->post(route('admin.calendario.eventos.inscricoes.store', $jogo->id_evento), [
            'id_atleta' => $idAtleta, 'id_time' => $this->azul,
        ])->assertSessionHas('sucesso', 'Atleta inscrito e escalado. 1 atleta(s) notificado(s).');

        $inscricao = $jogo->evento->inscricoes()->where('id_atleta', $idAtleta)->sole();
        $this->assertSame('INDIVIDUAL', $inscricao->origem_evento_atleta);
        $this->assertSame($this->azul, (int) $inscricao->id_time);

        // Time externo: não inscreve
        $this->comoAdmin()->post(route('admin.calendario.eventos.inscricoes.store', $jogo->id_evento), [
            'id_atleta' => $outro, 'id_time' => $this->visitante,
        ])->assertSessionHas('erro');
        $this->assertFalse($jogo->evento->inscricoes()->where('id_atleta', $outro)->exists());
    }

    // ---------- preencher pelo elenco ----------

    public function test_preencher_pelo_elenco(): void
    {
        $jogo = $this->jogo($this->azul, $this->preto);
        $ev   = $jogo->evento;

        $naoInscrito = $this->atleta('Gil', [$this->azul]);                  // inscreve e escala no Azul
        $semTime     = $this->atleta('Hugo', [$this->preto]);                // já inscrito: escala no Preto
        $comTime     = $this->atleta('Iara', [$this->azul]);                 // já tem time (Preto): não muda
        $nosDois     = $this->atleta('Joao', [$this->azul, $this->preto]);   // fica para o admin
        $inativo     = $this->atleta('Lia', [$this->azul]);
        $foraDoJogo  = $this->atleta('Mel', [$this->verde]);                 // elenco de time fora do jogo
        DB::table('tbl_atletas')->where('id_atleta', $inativo)->update(['status_atleta' => 'INATIVO']);

        $ev->inscrever($semTime, 'CATEGORIA', null);
        $ev->inscrever($comTime, 'CATEGORIA', null, $this->preto);
        $ev->inscrever($nosDois, 'CATEGORIA', null);

        $this->comoAdmin()->post(route('admin.calendario.eventos.escalacao.elenco', $jogo->id_evento))
            ->assertSessionHas('sucesso', 'Escalação pelo elenco: 1 inscrito(s) escalado(s), 1 atleta(s) inscrito(s) e escalado(s).'
                . ' 1 atleta(s) estão nos elencos dos dois times: escolha o time de cada um na lista. 1 atleta(s) notificado(s).');

        $this->assertSame($this->azul, $this->timeDe($jogo, $naoInscrito));
        $this->assertSame('ELENCO', $ev->inscricoes()->where('id_atleta', $naoInscrito)->value('origem_evento_atleta'));
        $this->assertSame($this->preto, $this->timeDe($jogo, $semTime));
        $this->assertSame($this->preto, $this->timeDe($jogo, $comTime));
        $this->assertNull($this->timeDe($jogo, $nosDois));
        $this->assertFalse($ev->inscricoes()->where('id_atleta', $inativo)->exists());
        $this->assertFalse($ev->inscricoes()->where('id_atleta', $foraDoJogo)->exists());

        // Notificação só para quem foi inscrito agora (Gil); escalar quem já estava inscrito não avisa
        $peloElenco = DB::table('tbl_notificacao')->whereNotNull('id_usuario')->pluck('id_atleta')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([$naoInscrito], $peloElenco);
        $this->assertSame(1, DB::table('tbl_notificacao')->where('id_atleta', $semTime)->count()); // a da inscrição inicial
    }

    public function test_nos_dois_elencos_e_nao_inscrito_entra_sem_time_e_aparece_na_lista(): void
    {
        // Como o atleta 12: estava nos dois elencos e não inscrito, era pulado e sumia da tela
        $jogo = $this->jogo($this->azul, $this->preto);
        $nosDois = $this->atleta('Joao', [$this->azul, $this->preto]);

        $this->comoAdmin()->post(route('admin.calendario.eventos.escalacao.elenco', $jogo->id_evento))
            ->assertSessionHas('sucesso', 'Escalação pelo elenco: 0 inscrito(s) escalado(s), 0 atleta(s) inscrito(s) e escalado(s).'
                . ' 1 atleta(s) estão nos elencos dos dois times (1 inscrito(s) agora, sem time): escolha o time de cada um na lista. 1 atleta(s) notificado(s).');

        $inscricao = $jogo->evento->inscricoes()->where('id_atleta', $nosDois)->sole();
        $this->assertSame('ELENCO', $inscricao->origem_evento_atleta);
        $this->assertNull($inscricao->id_time);

        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $jogo->id_evento))
            ->assertSee('aria-label="Time de Joao"', false)
            ->assertSee('Elenco: Time Azul, Time Preto');

        // Rodar de novo não duplica nem escala
        $this->comoAdmin()->post(route('admin.calendario.eventos.escalacao.elenco', $jogo->id_evento));
        $this->assertSame(1, $jogo->evento->inscricoes()->count());
        $this->assertNull($this->timeDe($jogo, $nosDois));
    }

    public function test_nos_dois_elencos_tambem_passa_pelo_alerta_de_conflito(): void
    {
        $jogo = $this->jogo($this->azul, $this->preto);
        $nosDois = $this->atleta('Kai', [$this->azul, $this->preto]);
        $this->treinoNoHorarioDoJogo($jogo)->inscrever($nosDois, 'INDIVIDUAL', null);

        $this->comoAdmin()->post(route('admin.calendario.eventos.escalacao.elenco', $jogo->id_evento))
            ->assertSessionHas('conflitos_pendentes', fn ($p) => count($p['fortes']) === 1);

        $this->assertFalse($jogo->evento->inscricoes()->where('id_atleta', $nosDois)->exists());
    }

    // ---------- aviso de categoria/sexo (não bloqueia) ----------

    public function test_escalar_fora_da_categoria_do_jogo_avisa_sem_bloquear(): void
    {
        $jogo = $this->jogo($this->azul, $this->preto, categoria: $this->idSub11M);
        $fulana = $this->atleta('Fulana', [], $this->idCategoria('Sub-15', 'F'), 'F');
        $daCategoria = $this->atleta('Ciclano');
        $jogo->evento->inscrever($fulana, 'INDIVIDUAL', null);
        $jogo->evento->inscrever($daCategoria, 'CATEGORIA', null);

        $this->escalar($jogo, $fulana, $this->azul)
            ->assertSessionHas('sucesso')
            ->assertSessionHas('avisos_categoria', ['Fulana é Sub-15 Feminino; o jogo é Sub-11 Masculino.']);
        $this->assertSame($this->azul, $this->timeDe($jogo, $fulana)); // escalou mesmo assim

        $this->escalar($jogo, $daCategoria, $this->azul)->assertSessionMissing('avisos_categoria');
        $this->escalar($jogo, $fulana, null)->assertSessionMissing('avisos_categoria'); // tirar do time não avisa

        // A tela mostra o aviso
        $this->escalar($jogo, $fulana, $this->preto);
        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $jogo->id_evento))
            ->assertSee('Fora da categoria do jogo')
            ->assertSee('Fulana é Sub-15 Feminino; o jogo é Sub-11 Masculino.');
    }

    public function test_inscrever_no_jogo_fora_da_categoria_avisa(): void
    {
        $jogo = $this->jogo($this->azul, $this->preto, categoria: $this->idSub11M);
        $semCategoria = $this->criarAtleta($this->nascidoComIdade(11), 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $semCategoria)->update(['nome_atleta' => 'Beltrano']);

        $this->comoAdmin()->post(route('admin.calendario.eventos.inscricoes.store', $jogo->id_evento), [
            'id_atleta' => $semCategoria, 'id_time' => $this->azul,
        ])->assertSessionHas('avisos_categoria', ['Beltrano está sem categoria; o jogo é Sub-11 Masculino.']);

        $this->assertSame($this->azul, $this->timeDe($jogo, $semCategoria));
    }

    public function test_preencher_pelo_elenco_avisa_quem_e_de_outra_categoria(): void
    {
        $jogo = $this->jogo($this->azul, $this->preto, categoria: $this->idSub11M);
        $this->atleta('Ana', [$this->azul]);
        $this->atleta('Zoe', [$this->preto], $this->idCategoria('Sub-13', 'M'));
        $this->atleta('Ivo', [$this->azul, $this->preto], $this->idCategoria('Sub-11', 'F'), 'F');

        $this->comoAdmin()->post(route('admin.calendario.eventos.escalacao.elenco', $jogo->id_evento))
            ->assertSessionHas('avisos_categoria', [
                'Ivo é Sub-11 Feminino; o jogo é Sub-11 Masculino.',
                'Zoe é Sub-13 Masculino; o jogo é Sub-11 Masculino.',
            ]);
    }

    public function test_jogo_sem_categoria_nao_avisa(): void
    {
        $jogo = $this->jogo($this->azul, $this->preto); // amistoso sem categoria
        $fulana = $this->atleta('Fulana', [], $this->idCategoria('Sub-15', 'F'), 'F');
        $jogo->evento->inscrever($fulana, 'INDIVIDUAL', null);

        $this->escalar($jogo, $fulana, $this->azul)->assertSessionMissing('avisos_categoria');
    }

    public function test_preencher_pelo_elenco_com_conflito_pede_confirmacao(): void
    {
        $jogo = $this->jogo($this->azul, $this->preto);
        $idAtleta = $this->atleta('Nina', [$this->azul]);
        $this->treinoNoHorarioDoJogo($jogo)->inscrever($idAtleta, 'INDIVIDUAL', null);

        $this->comoAdmin()->post(route('admin.calendario.eventos.escalacao.elenco', $jogo->id_evento))
            ->assertSessionHas('conflitos_pendentes', fn ($p) => count($p['fortes']) === 1);
        $this->assertFalse($jogo->evento->inscricoes()->where('id_atleta', $idAtleta)->exists());

        $this->comoAdmin()->post(route('admin.calendario.eventos.escalacao.elenco', $jogo->id_evento), ['confirmar_conflito' => 1]);
        $this->assertSame($this->azul, $this->timeDe($jogo, $idAtleta));
    }

    // ---------- troca de time do jogo ----------

    public function test_trocar_time_do_jogo_tira_da_escalacao_e_mantem_inscricao(): void
    {
        $campeonato = DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idSub11M, 'logo_evento' => 'l.png', 'banner_evento' => 'b.png', 'nome_campeonato' => 'Copa',
            'organizador_campeonato' => 'Escola', 'tipo_campeonato' => 'MATA-MATA', 'data_inicio_campeonato' => now()->toDateString(),
            'data_fim_campeonato' => now()->addMonth()->toDateString(), 'local_evento' => 'Quadra A',
        ]);
        $jogo = $this->jogo($this->azul, $this->preto, $campeonato);
        $noAzul  = $this->atleta('Otto');
        $noPreto = $this->atleta('Pia');
        $jogo->evento->inscrever($noAzul, 'INDIVIDUAL', null, $this->azul);
        $jogo->evento->inscrever($noPreto, 'INDIVIDUAL', null, $this->preto);

        $this->comoAdmin()->put(route('admin.jogos.update', $jogo->id_jogo), [
            'id_campeonato' => $campeonato, 'id_time_casa' => $this->azul, 'id_time_visitante' => $this->verde,
            'data_evento_calendario' => $jogo->evento->data_evento_calendario->toDateString(),
            'horario_inicio_evento_calendario' => '19:00',
        ])->assertSessionHas('sucesso', fn ($msg) => str_contains($msg, '1 atleta(s) saíram da escalação (o time deixou o jogo) e continuam inscritos.'));

        $this->assertSame($this->azul, $this->timeDe($jogo, $noAzul));
        $this->assertNull($this->timeDe($jogo, $noPreto));
        $this->assertTrue($jogo->evento->inscricoes()->where('id_atleta', $noPreto)->exists());
    }

    // ---------- tela ----------

    public function test_tela_do_jogo_mostra_escalacao_times_internos_e_elenco(): void
    {
        $jogo = $this->jogo($this->azul, $this->visitante);
        $idAtleta = $this->atleta('Rui', [$this->azul]);
        $jogo->evento->inscrever($idAtleta, 'INDIVIDUAL', null, $this->azul);
        $jogo->evento->inscrever($this->atleta('Sol'), 'INDIVIDUAL', null);

        $resposta = $this->comoAdmin()->get(route('admin.calendario.eventos.show', $jogo->id_evento))
            ->assertOk()
            ->assertSee('Escalação:')
            ->assertSee('Preencher pelo elenco')
            ->assertSeeInOrder(['Time Azul', '<strong>1</strong> atleta(s)', 'Sem time', '<strong>1</strong> inscrito(s)'], false)
            ->assertSee('<option value="' . $this->azul . '" selected>Time Azul</option>', false)
            ->assertSee('Elenco: Time Azul');

        // Selects de escalação (name="id_time"): o externo não aparece. O formulário de edição do jogo, na
        // mesma tela, lista todos os times (mandante/visitante), por isso a conferência é só nesses selects
        preg_match_all('#<select name="id_time".*?</select>#s', $resposta->getContent(), $selects);
        $this->assertNotEmpty($selects[0]);
        foreach ($selects[0] as $select) {
            $this->assertStringNotContainsString('<option value="' . $this->visitante . '"', $select);
        }
    }

    // ---------- ajudantes ----------

    public function test_fora_do_elenco_compara_com_o_time_escalado(): void
    {
        $jogo = $this->jogo($this->azul, $this->preto);
        $doAzul     = $this->atleta('Ana Do Azul', [$this->azul]);
        $doPreto    = $this->atleta('Bia Do Preto', [$this->preto]);   // escalada no adversário do time dela
        $semElenco  = $this->atleta('Caio Sem Elenco');
        $semTime    = $this->atleta('Davi Sem Time', [$this->preto]);
        $nosDois    = $this->atleta('Edu Nos Dois', [$this->azul, $this->preto]);
        foreach ([[$doAzul, $this->azul], [$doPreto, $this->azul], [$semElenco, $this->azul], [$semTime, null], [$nosDois, $this->preto]] as [$id, $time]) {
            $jogo->evento->inscrever($id, 'INDIVIDUAL', null, $time, notificar: false);
        }

        $html = $this->comoAdmin()->get(route('admin.calendario.eventos.show', $jogo->id_evento))->assertOk()->getContent();
        $aviso = function (string $nome) use ($html) {
            preg_match('#aria-label="Time de ' . $nome . '".*?<small[^>]*>(.*?)</small>#s', $html, $m);

            return trim($m[1] ?? '');
        };

        $this->assertSame('Elenco: Time Azul', $aviso('Ana Do Azul'));
        $this->assertSame('Fora do elenco · é do Time Preto', $aviso('Bia Do Preto'));
        $this->assertSame('Fora do elenco', $aviso('Caio Sem Elenco'));
        $this->assertSame('Elenco: Time Preto', $aviso('Davi Sem Time'));       // sem time: só informa
        $this->assertSame('Elenco: Time Azul, Time Preto', $aviso('Edu Nos Dois'));
        $this->assertStringContainsString('<small class="text-warning fw-semibold">Fora do elenco · é do Time Preto</small>', $html);
    }

    public function test_inscritos_do_jogo_em_blocos_por_time(): void
    {
        $jogo = $this->jogo($this->azul, $this->preto);
        $ana  = $this->atleta('Ana Azul', [$this->azul]);
        $bia  = $this->atleta('Bia Azul', [$this->azul]);
        $caio = $this->atleta('Caio Sem Time');
        $jogo->evento->inscrever($ana, 'INDIVIDUAL', null, $this->azul, notificar: false);
        $jogo->evento->inscrever($bia, 'INDIVIDUAL', null, $this->azul, notificar: false);
        $jogo->evento->inscrever($caio, 'INDIVIDUAL', null, null, notificar: false);
        $tela = fn () => preg_replace('/\s+/', ' ', $this->comoAdmin()->get(route('admin.calendario.eventos.show', $jogo->id_evento))->assertOk()->getContent());

        // Mandante, visitante (vazio, com o aviso) e "Sem time" no fim, cada um com os seus atletas
        $html = $tela();
        $this->assertSame(3, substr_count($html, 'class="js-bloco-time"'));
        $this->assertMatchesRegularExpression('#Time Azul <span class="fw-normal text-muted small">· mandante \(2\)</span>.*Ana Azul.*Bia Azul'
            . '.*Time Preto <span class="fw-normal text-muted small">· visitante \(0\)</span>.*Ninguém escalado neste time\.'
            . '.*Sem time <span class="fw-normal text-muted small">· falta escolher o time \(1\)</span>.*Caio Sem Time#', $html);

        // Escalando o último, o bloco "Sem time" some
        $this->escalar($jogo, $caio, $this->preto);
        $html = $tela();
        $this->assertSame(2, substr_count($html, 'class="js-bloco-time"'));
        $this->assertStringContainsString('· visitante (1)', $html);
        $this->assertStringNotContainsString('falta escolher o time', $html);
    }

    public function test_selo_da_origem_explica_ao_passar_o_mouse(): void
    {
        $jogo = $this->jogo($this->azul, $this->preto);
        $jogo->evento->inscrever($this->atleta('Ana', [$this->azul]), 'ELENCO', null, $this->azul, notificar: false);
        $jogo->evento->inscrever($this->atleta('Bia'), 'INDIVIDUAL', null, $this->azul, notificar: false);

        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $jogo->id_evento))->assertOk()
            ->assertSee('title="Entrou automaticamente pelo elenco do time. Sai do jogo se o time dele sair do jogo. Inscrito por —', false)
            ->assertSee('title="Escolhido à mão pelo admin. Nenhuma troca de categoria ou de time tira o atleta; só a remoção. Inscrito por —', false);

        // Evento comum (lista única): a mesma dica, também na origem pela categoria
        $treino = $this->treinoNoHorarioDoJogo($jogo);
        $treino->inscrever($this->atleta('Caio'), 'CATEGORIA', null, notificar: false);
        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $treino->id_evento_calendario))
            ->assertSee('title="Entrou automaticamente por ser da categoria do evento. Sai se o evento mudar de categoria. Inscrito por —', false);
    }

    public function test_evento_que_nao_e_jogo_lista_os_inscritos_sem_blocos(): void
    {
        $treino = $this->treinoNoHorarioDoJogo($this->jogo($this->azul, $this->preto));
        $treino->inscrever($this->atleta('Rui'), 'INDIVIDUAL', null, notificar: false);

        $html = $this->comoAdmin()->get(route('admin.calendario.eventos.show', $treino->id_evento_calendario))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'class="js-bloco-time"'));
        $this->assertStringNotContainsString('mandante (', $html);
        $this->assertStringContainsString('Rui', $html);
    }

    private function jogo(int $casa, int $visitante, ?int $idCampeonato = null, ?int $categoria = null): Jogo
    {
        $evento = EventoCalendario::criarPor(null, [
            'titulo_evento_calendario'         => Jogo::tituloPara($casa, $visitante),
            'tipo_evento_calendario'           => 'JOGO',
            'id_categoria'                     => $categoria,
            'data_evento_calendario'           => now()->addDays(7)->toDateString(),
            'horario_inicio_evento_calendario' => '19:00',
            'status_evento_calendario'         => 'ATIVO',
        ]);

        return Jogo::create([
            'id_evento' => $evento->id_evento_calendario, 'id_campeonato' => $idCampeonato,
            'id_time_casa' => $casa, 'id_time_visitante' => $visitante,
        ])->load('evento');
    }

    // Treino no mesmo horário do jogo (19:30 às 20:30), para provocar conflito
    private function treinoNoHorarioDoJogo(Jogo $jogo): EventoCalendario
    {
        return EventoCalendario::criarPor(null, [
            'titulo_evento_calendario' => 'Treino', 'tipo_evento_calendario' => 'TREINO',
            'data_evento_calendario' => $jogo->evento->data_evento_calendario->toDateString(),
            'horario_inicio_evento_calendario' => '19:30', 'horario_fim_evento_calendario' => '20:30',
            'status_evento_calendario' => 'ATIVO',
        ]);
    }

    // Atleta ativo (padrão: Sub-11 M), no elenco (tbl_atleta_time) dos times indicados
    private function atleta(string $nome, array $elencos = [], ?int $idCategoria = null, string $sexo = 'M'): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(11), $sexo, 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
        $this->colocarNaCategoria($id, $idCategoria ?? $this->idSub11M);

        foreach ($elencos as $idTime) {
            DB::table('tbl_atleta_time')->insert([
                'id_time' => $idTime, 'id_atleta' => $id, 'camisa_atleta_time' => 10, 'posicao_atleta_time' => '',
            ]);
        }

        return $id;
    }

    private function escalar(Jogo $jogo, int $idAtleta, ?int $idTime)
    {
        return $this->comoAdmin()->patch(
            route('admin.calendario.eventos.inscricoes.time', [$jogo->id_evento, $idAtleta]),
            ['id_time' => $idTime ?? '']
        );
    }

    private function timeDe(Jogo $jogo, int $idAtleta): ?int
    {
        $id = DB::table('tbl_evento_atleta')->where('id_evento_calendario', $jogo->id_evento)
            ->where('id_atleta', $idAtleta)->value('id_time');

        return $id === null ? null : (int) $id;
    }

    private function time(string $nome, string $tipo): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idSub11M, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo,
        ]);
    }
}
