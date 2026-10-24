<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Detalhe do evento da agenda (GET /agenda/{idEvento}, pedido do app, 24/10/2026): os campos da lista e, no jogo,
 * o adversário, a fase, a rodada, a perna, a prorrogação, os pênaltis e o confronto. Sem colegas, escalação,
 * posição e titular. 404 para evento sem inscrição do atleta, oculto ou em rascunho.
 */
class AppAgendaDetalheTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const SENHA = 'teste1234';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-10 12:00:00');
    }

    public function test_detalhe_do_treino_e_o_mesmo_da_lista_e_404_fora_da_agenda(): void
    {
        $atleta = $this->atleta('ana@exemplo.com');
        $outro = $this->atleta('bia@exemplo.com');
        $treino = $this->evento('Treino', '2026-10-13', '18:00:00', [$atleta]);
        $deOutro = $this->evento('Treino de outro', '2026-10-13', '18:00:00', [$outro]);
        $oculto = $this->evento('Oculto', '2026-10-14', '18:00:00', [$atleta], status: 'INATIVO');
        $rascunho = $this->evento('Rascunho', '2026-10-15', '18:00:00', [$atleta], publicado: false);
        $token = $this->login('ana@exemplo.com');

        $lista = $this->comToken($token)->getJson('/api/v1/agenda')->json('data.proximos.0');
        $this->comToken($token)->getJson("/api/v1/agenda/{$treino}")->assertOk()
            ->assertJsonPath('success', true)->assertExactJson(['success' => true, 'data' => $lista]);

        foreach ([$deOutro, $oculto, $rascunho, 999999] as $id) {
            $this->comToken($token)->getJson("/api/v1/agenda/{$id}")
                ->assertNotFound()->assertExactJson(['success' => false, 'message' => 'Evento não encontrado.']);
        }
    }

    public function test_jogo_de_volta_traz_adversario_fase_perna_penaltis_e_confronto_sem_os_colegas(): void
    {
        $atleta = $this->atleta('ana@exemplo.com');
        $colega = $this->atleta('bia@exemplo.com');
        $aacj = $this->time('AACJ Sub-13', 'INTERNO');
        $rival = $this->time('Rival FC', 'EXTERNO');
        $campeonato = $this->campeonato();

        $ida = $this->evento('Rival FC x AACJ Sub-13', '2026-10-03', '13:00:00', [$atleta, $colega], tipo: 'JOGO');
        $idIda = DB::table('tbl_jogos')->insertGetId([
            'id_evento' => $ida, 'id_campeonato' => $campeonato, 'fase_jogo' => 'SEMIFINAL', 'id_time_casa' => $rival, 'id_time_visitante' => $aacj,
            'placar_time_casa_jogos' => 2, 'placar_time_visitante_jogos' => 1,
        ]);
        $volta = $this->evento('AACJ Sub-13 x Rival FC', '2026-10-17', '13:00:00', [$atleta, $colega], tipo: 'JOGO');
        DB::table('tbl_jogos')->insert([
            'id_evento' => $volta, 'id_campeonato' => $campeonato, 'fase_jogo' => 'SEMIFINAL', 'id_jogo_ida' => $idIda,
            'id_time_casa' => $aacj, 'id_time_visitante' => $rival, 'placar_time_casa_jogos' => 2, 'placar_time_visitante_jogos' => 1,
            'penaltis_time_casa_jogos' => 4, 'penaltis_time_visitante_jogos' => 3,
        ]);
        DB::table('tbl_evento_atleta')->where('id_evento_calendario', $volta)
            ->update(['id_time' => $aacj, 'posicao_evento_atleta' => 'GOLEIRO', 'titular_evento_atleta' => 'TITULAR']);
        $token = $this->login('ana@exemplo.com');

        $jogo = $this->comToken($token)->getJson("/api/v1/agenda/{$volta}")->assertOk()->json('data.jogo');
        $this->assertSame('Rival FC', $jogo['adversario']['nome_time']);
        $this->assertSame('AACJ Sub-13', $jogo['time_do_atleta']['nome_time']);
        $this->assertSame(['SEMIFINAL', 'Semifinal', 'Semifinal · volta', null, 'VOLTA'],
            [$jogo['fase_jogo'], $jogo['fase'], $jogo['fase_completa'], $jogo['rodada'], $jogo['perna']]);
        $this->assertNull($jogo['prorrogacao']);
        $this->assertSame(['casa' => 4, 'visitante' => 3], $jogo['penaltis']);
        $this->assertSame(['id_time' => $aacj, 'como' => 'pênaltis', 'agregado' => [3, 3],
            'texto' => 'AACJ Sub-13 passa nos pênaltis (4 × 3), depois do agregado 3 × 3'], $jogo['confronto']);

        // Nada dos colegas nem da escalação de cada um (posição e titular ficam só no admin)
        $json = json_encode($this->comToken($token)->getJson("/api/v1/agenda/{$volta}")->json());
        foreach (['inscritos', 'escalacao', 'GOLEIRO', 'TITULAR', 'posicao', 'titular'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $json);
        }

        // Ida (o atleta sem time nela): adversário pelo único time da AACJ; o confronto decide na volta
        $jogoIda = $this->comToken($token)->getJson("/api/v1/agenda/{$ida}")->assertOk()->json('data.jogo');
        $this->assertNull($jogoIda['time_do_atleta']);
        $this->assertSame('Rival FC', $jogoIda['adversario']['nome_time']);
        $this->assertSame(['IDA', 'Semifinal · ida', null], [$jogoIda['perna'], $jogoIda['fase_completa'], $jogoIda['confronto']]);
    }

    public function test_fase_de_grupos_com_rodada_e_jogo_entre_dois_times_da_aacj(): void
    {
        $atleta = $this->atleta('ana@exemplo.com');
        $azul = $this->time('AACJ Sub-13 A', 'INTERNO');
        $verde = $this->time('AACJ Sub-13 B', 'INTERNO');
        $rival = $this->time('Rival FC', 'EXTERNO');

        $grupos = $this->evento('AACJ Sub-13 A x AACJ Sub-13 B', '2026-10-17', '13:00:00', [$atleta], tipo: 'JOGO');
        DB::table('tbl_jogos')->insert([
            'id_evento' => $grupos, 'id_campeonato' => $this->campeonato(), 'fase_jogo' => 'GRUPOS', 'rodada_jogo' => 2,
            'id_time_casa' => $azul, 'id_time_visitante' => $verde,
        ]);
        $amistoso = $this->evento('Rival FC x AACJ Sub-13 A', '2026-10-24', '13:00:00', [$atleta], tipo: 'JOGO');
        DB::table('tbl_jogos')->insert(['id_evento' => $amistoso, 'id_time_casa' => $rival, 'id_time_visitante' => $azul]);
        $token = $this->login('ana@exemplo.com');

        // Os dois times são da AACJ e o atleta está sem time: sem adversário
        $jogo = $this->comToken($token)->getJson("/api/v1/agenda/{$grupos}")->json('data.jogo');
        $this->assertSame([null, 'Fase de grupos', 'Fase de grupos · Rodada 2', 2, null, null, null, null],
            [$jogo['adversario'], $jogo['fase'], $jogo['fase_completa'], $jogo['rodada'], $jogo['perna'], $jogo['prorrogacao'], $jogo['penaltis'], $jogo['confronto']]);

        DB::table('tbl_evento_atleta')->where('id_evento_calendario', $grupos)->update(['id_time' => $verde]);
        $this->assertSame('AACJ Sub-13 A', $this->comToken($token)->getJson("/api/v1/agenda/{$grupos}")->json('data.jogo.adversario.nome_time'));

        $doAmistoso = $this->comToken($token)->getJson("/api/v1/agenda/{$amistoso}")->json('data.jogo');
        $this->assertSame(['Rival FC', null, null, null], [$doAmistoso['adversario']['nome_time'], $doAmistoso['fase'], $doAmistoso['fase_completa'], $doAmistoso['perna']]);
    }

    public function test_responsavel_ve_o_mesmo_detalhe_e_so_dos_proprios_filhos(): void
    {
        $filho = $this->atleta('ana@exemplo.com');
        $deOutraFamilia = $this->atleta('bia@exemplo.com');
        $responsavel = $this->responsavel('mae@exemplo.com');
        DB::table('tbl_atleta_responsavel')->insert(['id_atleta' => $filho, 'id_responsavel' => $responsavel, 'grau_parentesco_responsavel' => 'Mãe']);
        $treino = $this->evento('Treino', '2026-10-13', '18:00:00', [$filho, $deOutraFamilia]);

        $doAtleta = $this->comToken($this->login('ana@exemplo.com'))->getJson("/api/v1/agenda/{$treino}")->json();
        $tokenResponsavel = $this->login('mae@exemplo.com', 'responsavel');

        $this->comToken($tokenResponsavel)->getJson("/api/v1/responsavel/atletas/{$filho}/agenda/{$treino}")
            ->assertOk()->assertExactJson($doAtleta);
        $this->comToken($tokenResponsavel)->getJson("/api/v1/responsavel/atletas/{$deOutraFamilia}/agenda/{$treino}")
            ->assertNotFound()->assertJsonPath('message', 'Atleta não encontrado.');
        // O token do atleta não entra na área do responsável
        $this->comToken($this->login('ana@exemplo.com'))->getJson("/api/v1/responsavel/atletas/{$filho}/agenda/{$treino}")->assertForbidden();
    }

    // ---------- apoio ----------

    private function atleta(string $email): int
    {
        $id = $this->criarAtleta($this->nascidoComIdade(13), 'M', 'ATIVO');
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
            'id_endereco' => $idEndereco, 'nome_responsavel' => 'Responsável',
            'cpf_responsavel' => $this->cpfDeTeste(400000001), 'rg_responsavel' => '2', 'whatsapp_responsavel' => '(11) 9',
            'email_responsavel' => $email, 'senha_responsavel' => Hash::make(self::SENHA),
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
            'id_categoria' => $this->idCategoria('Sub-13', 'M'), 'nome_campeonato' => 'Copa AACJ', 'tipo_campeonato' => 'PONTOS CORRIDOS',
            'data_inicio_campeonato' => '2026-10-01', 'data_fim_campeonato' => '2026-12-01', 'status_campeonato' => 'ATIVO',
        ]);
    }

    private function evento(string $titulo, string $data, ?string $inicio, array $inscritos, string $status = 'ATIVO', string $tipo = 'TREINO', bool $publicado = true): int
    {
        $id = DB::table('tbl_evento_calendario')->insertGetId([
            'titulo_evento_calendario' => $titulo, 'tipo_evento_calendario' => $tipo, 'data_evento_calendario' => $data,
            'horario_inicio_evento_calendario' => $inicio, 'local_evento_calendario' => 'Campo A', 'status_evento_calendario' => $status,
            ...($publicado ? [] : ['data_publicacao_evento_calendario' => null]),
        ]);

        foreach ($inscritos as $idAtleta) {
            DB::table('tbl_evento_atleta')->insert([
                'id_evento_calendario' => $id, 'id_atleta' => $idAtleta, 'origem_evento_atleta' => 'INDIVIDUAL', 'data_evento_atleta' => now(),
            ]);
        }

        return $id;
    }

    private function login(string $email, string $perfil = 'atleta'): string
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'senha' => self::SENHA, 'perfil' => $perfil])->assertOk()->json('data.token');
    }

    private function comToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
