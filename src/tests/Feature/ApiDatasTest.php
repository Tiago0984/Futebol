<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\Jogo;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Datas no JSON da API (/api/v1): ISO 8601 com o deslocamento de Brasília e sem milissegundos
 * (SerializaDatasComFuso). Antes saíam em UTC: "2099-05-01T22:00:00.000000Z".
 */
class ApiDatasTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private const ISO_COM_FUSO = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}-0[23]:00$/';

    public function test_data_de_nascimento_do_atleta_logado(): void
    {
        // 2099: sem horário de verão, -03:00 (datas antigas em horário de verão saem -02:00)
        $idAtleta = $this->criarAtleta('2099-03-10', 'M', 'ATIVO');
        Sanctum::actingAs(Atleta::find($idAtleta));

        $this->getJson('/api/v1/atleta')
            ->assertOk()
            ->assertJsonPath('data.data_nasc_atleta', '2099-03-10T00:00:00-03:00');
    }

    public function test_data_de_nascimento_no_retorno_da_atualizacao(): void
    {
        $idAtleta = $this->criarAtleta('2099-03-10', 'M', 'ATIVO');
        Sanctum::actingAs(Atleta::find($idAtleta));

        $this->patchJson('/api/v1/atleta', ['escola_atleta' => 'Escola Nova'])
            ->assertOk()
            ->assertJsonPath('data.data_nasc_atleta', '2099-03-10T00:00:00-03:00');
    }

    public function test_datas_do_campeonato_na_lista_e_no_detalhe(): void
    {
        $id = $this->campeonato('2099-01-05 00:00:00', '2099-02-10 18:30:00');

        $this->getJson('/api/v1/campeonatos')
            ->assertOk()
            ->assertJsonPath('data.0.data_inicio_campeonato', '2099-01-05T00:00:00-03:00')
            ->assertJsonPath('data.0.data_fim_campeonato', '2099-02-10T18:30:00-03:00');

        $this->getJson("/api/v1/campeonatos/{$id}")
            ->assertOk()
            ->assertJsonPath('data.data_inicio_campeonato', '2099-01-05T00:00:00-03:00')
            ->assertJsonPath('data.data_fim_campeonato', '2099-02-10T18:30:00-03:00');
    }

    public function test_data_do_jogo_no_detalhe_do_campeonato(): void
    {
        $id = $this->campeonato('2099-01-05 00:00:00', '2099-02-10 00:00:00');
        $idEvento = DB::table('tbl_evento_calendario')->insertGetId([
            'titulo_evento_calendario' => 'Casa x Visitante', 'tipo_evento_calendario' => 'JOGO',
            'data_evento_calendario' => '2099-01-20', 'horario_inicio_evento_calendario' => '19:00:00',
            'status_evento_calendario' => 'ATIVO',
        ]);
        $time = fn (string $nome) => DB::table('tbl_time')->insertGetId([
            'id_categoria' => $this->idCategoria('Sub-11', 'M'), 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => 'INTERNO',
        ]);
        Jogo::create(['id_evento' => $idEvento, 'id_campeonato' => $id, 'id_time_casa' => $time('Casa'), 'id_time_visitante' => $time('Visitante')]);

        $this->getJson("/api/v1/campeonatos/{$id}")
            ->assertOk()
            ->assertJsonPath('data.jogos.0.data_jogo', '2099-01-20T19:00:00-03:00');
    }

    public function test_data_da_noticia_na_lista_e_no_detalhe(): void
    {
        $id = DB::table('tbl_noticias')->insertGetId([
            'titulo_noticia' => 'Notícia', 'conteudo_noticia' => 'Texto', 'status_noticia' => 'ATIVO',
            'data_publicacao_noticia' => '2099-05-15 16:00:00',
        ]);

        $this->getJson('/api/v1/noticias')
            ->assertOk()
            ->assertJsonPath('data.0.data_publicacao_noticia', '2099-05-15T16:00:00-03:00');

        $this->getJson("/api/v1/noticias/{$id}")
            ->assertOk()
            ->assertJsonPath('data.data_publicacao_noticia', '2099-05-15T16:00:00-03:00');
    }

    public function test_formato_sem_milissegundos_e_sem_z(): void
    {
        $id = $this->campeonato('2099-01-05 00:00:00', '2099-02-10 00:00:00');

        $data = $this->getJson("/api/v1/campeonatos/{$id}")->assertOk()->json('data.data_inicio_campeonato');

        $this->assertMatchesRegularExpression(self::ISO_COM_FUSO, $data);
    }

    // As telas continuam recebendo Carbon (só o JSON muda)
    public function test_no_codigo_o_campo_continua_sendo_data(): void
    {
        $idAtleta = $this->criarAtleta('2099-03-10', 'M', 'ATIVO');

        $this->assertInstanceOf(\Carbon\CarbonInterface::class, Atleta::find($idAtleta)->data_nasc_atleta);
    }

    private function campeonato(string $inicio, string $fim): int
    {
        return DB::table('tbl_campeonato')->insertGetId([
            'id_categoria' => $this->idCategoria('Sub-11', 'M'), 'logo_evento' => 'logo.png', 'banner_evento' => 'banner.png', 'nome_campeonato' => 'Copa',
            'organizador_campeonato' => 'Escola', 'tipo_campeonato' => 'PONTOS CORRIDOS', 'local_evento' => 'Quadra A',
            'data_inicio_campeonato' => $inicio, 'data_fim_campeonato' => $fim, 'status_campeonato' => 'ATIVO',
        ]);
    }
}
