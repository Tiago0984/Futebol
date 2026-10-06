<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\Notificacao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Fase 9, agenda e avisos do app para os dois perfis (App\Services\AppDoAtleta): o atleta vê os dele;
 * o responsável vê os de cada filho ATIVO, em modo leitura, com a própria marca de leitura
 * (tbl_notificacao_leitura). Também: datas do pivô de categorias com fuso e "quantos responsáveis
 * leram" na tela do evento.
 */
class AppAgendaEAvisosTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const SENHA = 'teste1234';
    private const HOJE = '2026-10-10 12:00:00'; // sábado, meio-dia

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(self::HOJE);
    }

    // ---------- agenda do atleta ----------

    public function test_agenda_so_com_inscritos_sem_ocultos_e_sem_alterado(): void
    {
        $atleta = $this->atleta('ana@exemplo.com');
        $outro = $this->atleta('bia@exemplo.com');

        $treino = $this->evento('Treino', '2026-10-12', '18:00:00', '19:30:00', inscritos: [$atleta]);
        $cancelado = $this->evento('Cancelado', '2026-10-11', '09:00:00', null, inscritos: [$atleta], status: 'CANCELADO');
        $this->evento('Oculto', '2026-10-13', '09:00:00', null, inscritos: [$atleta], status: 'INATIVO');
        $this->evento('De outro atleta', '2026-10-12', '08:00:00', null, inscritos: [$outro]);
        $semHorario = $this->evento('Sem horário', '2026-10-12', null, null, inscritos: [$atleta]);

        // Alterado no admin: o app continua mostrando só CONFIRMADO (a alteração chega pelo aviso)
        EventoCalendario::find($treino)->atualizarComHistorico(['local_evento_calendario' => 'Campo B'], null);

        $agenda = $this->comToken($this->login('ana@exemplo.com'))->getJson('/api/v1/agenda')->assertOk()->json('data');

        $this->assertSame([$cancelado, $treino, $semHorario], array_column($agenda['proximos'], 'id_evento')); // sem horário: fim do dia
        $this->assertSame(['CANCELADO', 'CONFIRMADO', 'CONFIRMADO'], array_column($agenda['proximos'], 'situacao'));
        $this->assertSame([], $agenda['passados']);

        $this->assertSame([
            'id_evento' => $treino, 'titulo' => 'Treino', 'tipo' => 'TREINO', 'tipo_label' => 'TREINO', 'subtipo' => null,
            'descricao' => null, 'categoria' => null, 'data' => '2026-10-12T00:00:00-03:00',
            'horario_inicio' => '18:00', 'horario_fim' => '19:30',
            'inicio' => '2026-10-12T18:00:00-03:00', 'fim' => '2026-10-12T19:30:00-03:00',
            'local' => 'Campo B', 'situacao' => 'CONFIRMADO', 'jogo' => null,
        ], $agenda['proximos'][1]);
        $this->assertSame([null, null, null, null], [
            $agenda['proximos'][2]['horario_inicio'], $agenda['proximos'][2]['horario_fim'], $agenda['proximos'][2]['inicio'], $agenda['proximos'][2]['fim'],
        ]);
    }

    public function test_passados_sao_os_3_ultimos_concluidos_e_nao_cancelados_e_hoje_conta_pelo_horario(): void
    {
        $atleta = $this->atleta('ana@exemplo.com');

        $this->evento('Velho', '2026-10-01', '18:00:00', null, inscritos: [$atleta]);
        $ontem1 = $this->evento('Ontem cedo', '2026-10-09', '08:00:00', null, inscritos: [$atleta]);
        $ontem2 = $this->evento('Ontem tarde', '2026-10-09', '18:00:00', null, inscritos: [$atleta]);
        $this->evento('Ontem cancelado', '2026-10-09', '20:00:00', null, inscritos: [$atleta], status: 'CANCELADO');
        $hojeConcluido = $this->evento('Hoje de manhã', '2026-10-10', '08:00:00', '10:00:00', inscritos: [$atleta]);
        $hojeEmAndamento = $this->evento('Hoje até as 13h', '2026-10-10', '11:00:00', '13:00:00', inscritos: [$atleta]);
        $hojeSemHorario = $this->evento('Hoje sem horário', '2026-10-10', null, null, inscritos: [$atleta]);

        $agenda = $this->comToken($this->login('ana@exemplo.com'))->getJson('/api/v1/agenda')->json('data');

        $this->assertSame([$hojeConcluido, $ontem2, $ontem1], array_column($agenda['passados'], 'id_evento'));
        $this->assertSame([$hojeEmAndamento, $hojeSemHorario], array_column($agenda['proximos'], 'id_evento'));
    }

    public function test_jogo_traz_campeonato_times_placar_e_o_time_do_atleta(): void
    {
        $atleta = $this->atleta('ana@exemplo.com');
        $casa = $this->time('AACJ Sub-13', 'INTERNO');
        $visitante = $this->time('Rival FC', 'EXTERNO');

        $jogo = $this->evento('AACJ Sub-13 x Rival FC', '2026-10-15', '10:00:00', null, inscritos: [$atleta], tipo: 'JOGO');
        $idJogo = DB::table('tbl_jogos')->insertGetId([
            'id_evento' => $jogo, 'id_campeonato' => $this->campeonato(), 'id_time_casa' => $casa, 'id_time_visitante' => $visitante,
        ]);
        DB::table('tbl_evento_atleta')->where('id_evento_calendario', $jogo)->update(['id_time' => $casa]);

        $amistoso = $this->evento('Amistoso', '2026-10-03', '10:00:00', null, inscritos: [$atleta], tipo: 'JOGO');
        DB::table('tbl_jogos')->insert([
            'id_evento' => $amistoso, 'id_time_casa' => $visitante, 'id_time_visitante' => $casa,
            'placar_time_casa_jogos' => 1, 'placar_time_visitante_jogos' => 3,
        ]);

        $agenda = $this->comToken($this->login('ana@exemplo.com'))->getJson('/api/v1/agenda')->json('data');

        $this->assertSame([
            'id_jogo' => $idJogo, 'amistoso' => false,
            'campeonato' => ['id_campeonato' => DB::table('tbl_campeonato')->value('id_campeonato'), 'nome_campeonato' => 'Copa AACJ'],
            'time_casa' => ['id_time' => $casa, 'nome_time' => 'AACJ Sub-13', 'logo_time' => 'time.png'],
            'time_visitante' => ['id_time' => $visitante, 'nome_time' => 'Rival FC', 'logo_time' => 'time.png'],
            'placar' => null,
            'time_do_atleta' => ['id_time' => $casa, 'nome_time' => 'AACJ Sub-13', 'logo_time' => 'time.png'],
        ], $agenda['proximos'][0]['jogo']);

        $passado = $agenda['passados'][0]['jogo'];
        $this->assertTrue($passado['amistoso']);
        $this->assertNull($passado['campeonato']);
        $this->assertSame(['casa' => 1, 'visitante' => 3], $passado['placar']);
        $this->assertNull($passado['time_do_atleta']); // inscrito sem time
    }

    public function test_proximos_paginados_e_passados_iguais_em_todas_as_paginas(): void
    {
        $atleta = $this->atleta('ana@exemplo.com');
        foreach (range(1, 25) as $dia) {
            $this->evento("Treino {$dia}", '2026-11-' . str_pad((string) $dia, 2, '0', STR_PAD_LEFT), '18:00:00', null, inscritos: [$atleta]);
        }
        $passado = $this->evento('Passado', '2026-10-01', '18:00:00', null, inscritos: [$atleta]);
        $token = $this->login('ana@exemplo.com');

        $pagina1 = $this->comToken($token)->getJson('/api/v1/agenda')->json('data');
        $pagina2 = $this->comToken($token)->getJson('/api/v1/agenda?page=2')->json('data');

        $this->assertCount(20, $pagina1['proximos']);
        $this->assertCount(5, $pagina2['proximos']);
        $this->assertSame('Treino 21', $pagina2['proximos'][0]['titulo']);
        $this->assertSame(['pagina_atual' => 1, 'ultima_pagina' => 2, 'por_pagina' => 20, 'total' => 25], $pagina1['paginacao']);
        $this->assertSame(2, $pagina2['paginacao']['pagina_atual']);
        $this->assertSame([$passado], array_column($pagina1['passados'], 'id_evento'));
        $this->assertSame($pagina1['passados'], $pagina2['passados']);
    }

    // ---------- avisos do atleta ----------

    public function test_avisos_paginados_do_mais_novo_e_so_do_proprio_atleta(): void
    {
        $atleta = $this->atleta('ana@exemplo.com');
        $outro = $this->atleta('bia@exemplo.com');
        foreach (range(1, 22) as $i) {
            $this->aviso($atleta, '2026-10-0' . (1 + intdiv($i, 3)) . ' 09:00:00', "Aviso {$i}");
        }
        $primeiro = $this->aviso($atleta, '2026-10-09 09:00:00', 'Mesma hora, id menor');
        $empate = $this->aviso($atleta, '2026-10-09 09:00:00', 'Mesma hora, id maior'); // empate: o id decide
        $this->aviso($outro, '2026-10-10 09:00:00', 'De outro atleta');
        $token = $this->login('ana@exemplo.com');

        $pagina1 = $this->comToken($token)->getJson('/api/v1/notificacoes')->assertOk()->json('data');
        $pagina2 = $this->comToken($token)->getJson('/api/v1/notificacoes?page=2')->json('data');

        $this->assertSame(['pagina_atual' => 1, 'ultima_pagina' => 2, 'por_pagina' => 20, 'total' => 24], $pagina1['paginacao']);
        $this->assertCount(4, $pagina2['notificacoes']);
        $this->assertSame([$empate, $primeiro], array_column(array_slice($pagina1['notificacoes'], 0, 2), 'id_notificacao'));
        $this->assertNotContains('De outro atleta', array_column([...$pagina1['notificacoes'], ...$pagina2['notificacoes']], 'titulo'));
        $this->assertSame([
            'id_notificacao' => $empate, 'tipo' => 'AGENDA', 'tipo_label' => 'Agenda', 'titulo' => 'Mesma hora, id maior',
            'mensagem' => 'Texto', 'id_evento' => null, 'data' => '2026-10-09T09:00:00-03:00', 'lida' => false, 'data_leitura' => null,
        ], $pagina1['notificacoes'][0]);
    }

    public function test_atleta_conta_e_marca_os_proprios_avisos_e_os_de_outro_dao_404(): void
    {
        $atleta = $this->atleta('ana@exemplo.com');
        $outro = $this->atleta('bia@exemplo.com');
        $a = $this->aviso($atleta);
        $this->aviso($atleta);
        $this->aviso($atleta);
        $deOutro = $this->aviso($outro);
        $token = $this->login('ana@exemplo.com');

        $this->comToken($token)->getJson('/api/v1/notificacoes/nao-lidas')->assertExactJson(['success' => true, 'data' => ['nao_lidas' => 3]]);

        $this->comToken($token)->patchJson("/api/v1/notificacoes/{$a}/lida")->assertOk()
            ->assertJsonPath('data.lida', true)->assertJsonPath('data.data_leitura', '2026-10-10T12:00:00-03:00');
        $this->travel(5)->minutes();
        $this->comToken($token)->patchJson("/api/v1/notificacoes/{$a}/lida")
            ->assertJsonPath('data.data_leitura', '2026-10-10T12:00:00-03:00'); // mantém a primeira leitura

        foreach ([$deOutro, 999999] as $id) {
            $this->comToken($token)->patchJson("/api/v1/notificacoes/{$id}/lida")
                ->assertNotFound()->assertExactJson(['success' => false, 'message' => 'Notificação não encontrada.']);
        }
        $this->assertNull(Notificacao::find($deOutro)->data_leitura_notificacao);

        $this->comToken($token)->patchJson('/api/v1/notificacoes/lidas')->assertExactJson(['success' => true, 'data' => ['marcadas' => 2]]);
        $this->comToken($token)->getJson('/api/v1/notificacoes/nao-lidas')->assertJsonPath('data.nao_lidas', 0);
        $this->assertNull(Notificacao::find($deOutro)->data_leitura_notificacao);
    }

    // ---------- responsável ----------

    public function test_responsavel_lista_os_filhos_ativos_com_os_nao_lidos_de_cada_um(): void
    {
        [$responsavel, $filho1] = $this->responsavelComFilho('mae@exemplo.com', 'ana@exemplo.com');
        $filho2 = $this->atleta(null);
        $inativo = $this->atleta(null, 'INATIVO');
        $this->vincular($filho2, $responsavel);
        $this->vincular($inativo, $responsavel);
        $this->aviso($filho1);
        $this->aviso($filho1);
        $lido = $this->aviso($filho2);
        DB::table('tbl_notificacao_leitura')->insert(['id_notificacao' => $lido, 'id_responsavel' => $responsavel]);

        $filhos = $this->comToken($this->login('mae@exemplo.com', 'responsavel'))->getJson('/api/v1/responsavel/atletas')->assertOk()->json('data');

        $contagem = array_column($filhos, 'notificacoes_nao_lidas', 'id_atleta');
        ksort($contagem);
        $this->assertSame([$filho1 => 2, $filho2 => 0], $contagem); // o inativo não aparece
        $this->assertSame(['id_atleta', 'nome_atleta', 'numero_matricula_atleta', 'foto_atleta', 'grau_parentesco_responsavel', 'notificacoes_nao_lidas'],
            array_keys($filhos[0]));
    }

    public function test_responsavel_ve_dados_agenda_e_avisos_do_filho_iguais_aos_do_atleta(): void
    {
        [, $filho] = $this->responsavelComFilho('mae@exemplo.com', 'ana@exemplo.com');
        $this->colocarNaCategoria($filho, $this->idCategoria('Sub-13', 'M'));
        $this->evento('Treino', '2026-10-12', '18:00:00', null, inscritos: [$filho]);
        $this->evento('Passado', '2026-10-01', '18:00:00', null, inscritos: [$filho]);
        $this->aviso($filho);

        $tokenAtleta = $this->login('ana@exemplo.com');
        $tokenResponsavel = $this->login('mae@exemplo.com', 'responsavel');

        foreach (['' => "/responsavel/atletas/{$filho}", '/agenda' => "/responsavel/atletas/{$filho}/agenda",
                  '/notificacoes' => "/responsavel/atletas/{$filho}/notificacoes"] as $doAtleta => $doResponsavel) {
            $rotaAtleta = $doAtleta === '' ? '/api/v1/atleta' : "/api/v1{$doAtleta}";
            $this->assertSame(
                $this->comToken($tokenAtleta)->getJson($rotaAtleta)->assertOk()->json(),
                $this->comToken($tokenResponsavel)->getJson("/api/v1{$doResponsavel}")->assertOk()->json(),
                $doResponsavel
            );
        }

        // Sem edição: o responsável não tem PUT/PATCH nos dados do filho
        $this->comToken($tokenResponsavel)->patchJson("/api/v1/responsavel/atletas/{$filho}", ['escola_atleta' => 'X'])->assertStatus(405);
    }

    public function test_responsavel_pedindo_atleta_que_nao_e_filho_ou_filho_inativado_recebe_404(): void
    {
        [$responsavel, $filho] = $this->responsavelComFilho('mae@exemplo.com', 'ana@exemplo.com');
        $irmao = $this->atleta(null);
        $this->vincular($irmao, $responsavel);
        [, $deOutraFamilia] = $this->responsavelComFilho('pai@exemplo.com', 'bia@exemplo.com');
        $avisoDeOutraFamilia = $this->aviso($deOutraFamilia);
        $avisoDoIrmao = $this->aviso($irmao);
        $token = $this->login('mae@exemplo.com', 'responsavel');

        DB::table('tbl_atletas')->where('id_atleta', $irmao)->update(['status_atleta' => 'INATIVO']);

        foreach ([$deOutraFamilia, $irmao, 999999] as $id) {
            foreach (['GET' => '', 'GET ' => '/agenda', 'GET  ' => '/notificacoes', 'GET   ' => '/notificacoes/nao-lidas',
                      'PATCH' => '/notificacoes/lidas', 'PATCH ' => "/notificacoes/{$avisoDeOutraFamilia}/lida"] as $metodo => $rota) {
                $this->comToken($token)->json(trim($metodo), "/api/v1/responsavel/atletas/{$id}{$rota}")
                    ->assertNotFound()->assertExactJson(['success' => false, 'message' => 'Atleta não encontrado.']);
            }
        }

        // O filho ativo continua acessível; o aviso de outro atleta, pela rota dele, é 404
        $this->comToken($token)->getJson("/api/v1/responsavel/atletas/{$filho}/agenda")->assertOk();
        $this->comToken($token)->patchJson("/api/v1/responsavel/atletas/{$filho}/notificacoes/{$avisoDoIrmao}/lida")
            ->assertNotFound()->assertJsonPath('message', 'Notificação não encontrada.');
        $this->assertSame(0, DB::table('tbl_notificacao_leitura')->count());
        $this->assertSame([$filho], array_column($this->comToken($token)->getJson('/api/v1/responsavel/atletas')->json('data'), 'id_atleta'));
    }

    public function test_leitura_independente_entre_o_atleta_e_dois_responsaveis(): void
    {
        [$mae, $filho] = $this->responsavelComFilho('mae@exemplo.com', 'ana@exemplo.com');
        $pai = $this->responsavel('pai@exemplo.com');
        $this->vincular($filho, $pai);
        [$a, $b, $c] = [$this->aviso($filho), $this->aviso($filho), $this->aviso($filho)];

        $atleta = $this->login('ana@exemplo.com');
        $tokenMae = $this->login('mae@exemplo.com', 'responsavel');
        $tokenPai = $this->login('pai@exemplo.com', 'responsavel');
        $naoLidas = fn ($token, $rota) => $this->comToken($token)->getJson($rota)->json('data.nao_lidas');
        $doFilho = "/api/v1/responsavel/atletas/{$filho}";

        // O atleta lê "a": os responsáveis continuam com 3
        $this->comToken($atleta)->patchJson("/api/v1/notificacoes/{$a}/lida")->assertOk();
        $this->assertSame([2, 3, 3], [
            $naoLidas($atleta, '/api/v1/notificacoes/nao-lidas'), $naoLidas($tokenMae, "{$doFilho}/notificacoes/nao-lidas"), $naoLidas($tokenPai, "{$doFilho}/notificacoes/nao-lidas"),
        ]);

        // A mãe lê "b": não mexe na leitura do atleta nem na do pai
        $this->comToken($tokenMae)->patchJson("{$doFilho}/notificacoes/{$b}/lida")->assertOk()->assertJsonPath('data.lida', true);
        $this->assertNull(Notificacao::find($b)->data_leitura_notificacao);
        $this->assertSame([2, 2, 3], [
            $naoLidas($atleta, '/api/v1/notificacoes/nao-lidas'), $naoLidas($tokenMae, "{$doFilho}/notificacoes/nao-lidas"), $naoLidas($tokenPai, "{$doFilho}/notificacoes/nao-lidas"),
        ]);

        // "a" aparece lida para o atleta e não lida para a mãe
        $paraMae = collect($this->comToken($tokenMae)->getJson("{$doFilho}/notificacoes")->json('data.notificacoes'))->keyBy('id_notificacao');
        $this->assertFalse($paraMae[$a]['lida']);
        $this->assertTrue($paraMae[$b]['lida']);
        $this->assertSame('2026-10-10T12:00:00-03:00', $paraMae[$b]['data_leitura']);

        // O pai marca todas: só as dele (3), uma vez só
        $this->comToken($tokenPai)->patchJson("{$doFilho}/notificacoes/lidas")->assertJsonPath('data.marcadas', 3);
        $this->comToken($tokenPai)->patchJson("{$doFilho}/notificacoes/lidas")->assertJsonPath('data.marcadas', 0);
        $this->assertSame([2, 2, 0], [
            $naoLidas($atleta, '/api/v1/notificacoes/nao-lidas'), $naoLidas($tokenMae, "{$doFilho}/notificacoes/nao-lidas"), $naoLidas($tokenPai, "{$doFilho}/notificacoes/nao-lidas"),
        ]);
        $this->assertSame(1, DB::table('tbl_notificacao_leitura')->where('id_responsavel', $mae)->count());
        $this->assertSame(3, DB::table('tbl_notificacao_leitura')->where('id_responsavel', $pai)->count());
        $this->assertNull(Notificacao::find($c)->data_leitura_notificacao);
    }

    // ---------- datas do pivô de categorias (GET /atleta) ----------

    public function test_datas_do_pivo_de_categorias_com_fuso_e_as_mesmas_chaves(): void
    {
        $atleta = $this->atleta('ana@exemplo.com');
        DB::table('tbl_categoria_atleta')->insert([
            'id_categoria' => $this->idCategoria('Sub-13', 'M'), 'id_atleta' => $atleta, 'status_categoria_atleta' => 'ATIVO',
            'data_inicio_categoria_atleta' => '2026-02-01 09:30:00', 'data_atualizacao_categoria_atleta' => '2026-03-01 10:00:00',
        ]);

        $categoria = $this->comToken($this->login('ana@exemplo.com'))->getJson('/api/v1/atleta')->json('data.categorias.0');

        $this->assertSame(['id_categoria', 'nome_categoria', 'pivot'], array_keys($categoria));
        $this->assertSame('2026-02-01T09:30:00-03:00', $categoria['pivot']['data_inicio_categoria_atleta']);
        $this->assertNull($categoria['pivot']['data_fim_categoria_atleta']);
        $this->assertSame('2026-03-01T10:00:00-03:00', $categoria['pivot']['data_atualizacao_categoria_atleta']);
        $this->assertSame('ATIVO', $categoria['pivot']['status_categoria_atleta']);
    }

    // ---------- admin: quantos responsáveis leram ----------

    public function test_tela_do_evento_mostra_quantos_responsaveis_leram(): void
    {
        [$mae, $filho] = $this->responsavelComFilho('mae@exemplo.com', 'ana@exemplo.com');
        $this->vincular($filho, $this->responsavel('pai@exemplo.com'));
        $semResponsavel = $this->atleta('bia@exemplo.com');
        $evento = $this->evento('Treino', '2026-10-12', '18:00:00', null, inscritos: [$filho, $semResponsavel]);
        $lido = $this->aviso($filho, evento: $evento);
        $this->aviso($semResponsavel, evento: $evento);
        DB::table('tbl_notificacao_leitura')->insert(['id_notificacao' => $lido, 'id_responsavel' => $mae]);

        $this->comoAdmin()->get(route('admin.calendario.eventos.show', $evento))
            ->assertOk()
            ->assertSee('Responsáveis')
            ->assertSee('1 de 2 leram');
    }

    // ---------- helpers ----------

    private function atleta(?string $email, string $status = 'ATIVO'): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(13), 'M', $status);
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['email_atleta' => $email, 'password' => Hash::make(self::SENHA)]);

        return $id;
    }

    private function responsavel(string $email): int
    {
        $idEndereco = DB::table('tbl_endereco')->insertGetId([
            'rua_endereco' => 'Rua', 'numero_endereco' => '1', 'bairro_endereco' => 'Centro',
            'cep_endereco' => '01000-000', 'cidade_endereco' => 'São Paulo', 'estado_endereco' => 'SP',
        ]);

        return DB::table('tbl_responsavel')->insertGetId([
            'id_endereco' => $idEndereco, 'nome_responsavel' => 'Responsável', 'cpf_responsavel' => '222.222.222-22',
            'rg_responsavel' => '2', 'whatsapp_responsavel' => '(11) 9', 'email_responsavel' => $email,
            'senha_responsavel' => Hash::make(self::SENHA),
        ]);
    }

    // [id_responsavel, id_filho ATIVO]
    private function responsavelComFilho(string $emailResponsavel, string $emailFilho): array
    {
        $filho = $this->atleta($emailFilho);
        $responsavel = $this->responsavel($emailResponsavel);
        $this->vincular($filho, $responsavel);

        return [$responsavel, $filho];
    }

    private function vincular(int $idAtleta, int $idResponsavel): void
    {
        DB::table('tbl_atleta_responsavel')->insert(['id_atleta' => $idAtleta, 'id_responsavel' => $idResponsavel, 'grau_parentesco_responsavel' => 'Mãe']);
    }

    private function evento(string $titulo, string $data, ?string $inicio, ?string $fim, array $inscritos = [], string $status = 'ATIVO', string $tipo = 'TREINO'): int
    {
        $id = DB::table('tbl_evento_calendario')->insertGetId([
            'titulo_evento_calendario' => $titulo, 'tipo_evento_calendario' => $tipo, 'data_evento_calendario' => $data,
            'horario_inicio_evento_calendario' => $inicio, 'horario_fim_evento_calendario' => $fim,
            'local_evento_calendario' => 'Campo A', 'status_evento_calendario' => $status,
        ]);

        foreach ($inscritos as $idAtleta) {
            DB::table('tbl_evento_atleta')->insert([
                'id_evento_calendario' => $id, 'id_atleta' => $idAtleta, 'origem_evento_atleta' => 'INDIVIDUAL', 'data_evento_atleta' => now(),
            ]);
        }

        return $id;
    }

    private function aviso(int $idAtleta, ?string $data = null, string $titulo = 'Aviso', ?int $evento = null): int
    {
        return DB::table('tbl_notificacao')->insertGetId([
            'id_atleta' => $idAtleta, 'id_evento_calendario' => $evento, 'tipo_notificacao' => 'AGENDA',
            'titulo_notificacao' => $titulo, 'mensagem_notificacao' => 'Texto', 'data_notificacao' => $data ?? now(),
        ]);
    }

    private function time(string $nome, string $tipo): int
    {
        return DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idCategoria('Sub-13', 'M'), 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo,
        ]);
    }

    private function campeonato(): int
    {
        return DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idCategoria('Sub-13', 'M'), 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png', 'nome_campeonato' => 'Copa AACJ',
            'organizador_campeonato' => 'Escola', 'tipo_campeonato' => 'PONTOS CORRIDOS', 'local_evento' => 'Quadra A',
            'data_inicio_campeonato' => '2026-10-01', 'data_fim_campeonato' => '2026-12-01', 'status_campeonato' => 'ATIVO',
        ]);
    }

    private function login(string $email, string $perfil = 'atleta'): string
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'senha' => self::SENHA, 'perfil' => $perfil])->assertOk()->json('data.token');
    }

    private function comToken(string $token): static
    {
        // Cada requisição resolve o token de novo (sem reaproveitar o usuário da anterior)
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
