<?php

namespace Tests\Feature;

use App\Models\Atleta;
use App\Models\Dispositivo;
use App\Models\EventoCalendario;
use App\Models\Notificacao;
use App\Models\Responsavel;
use App\Services\ClienteExpo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

use function Illuminate\Support\defer;

class NotificacaoPushTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    private mixed $respostaExpo;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->respostaExpo = Http::response(['data' => [['status' => 'ok']]]);
        Http::fake([ClienteExpo::URL => fn () => $this->respostaExpo]);
        config(['services.expo.access_token' => null]);
    }

    private function atleta(string $nome = 'Joana Silva'): Atleta
    {
        $conta = Atleta::findOrFail($this->criarAtleta($this->nascidoComIdade(13), 'F', 'ATIVO'));
        $conta->update(['nome_atleta' => $nome]);

        return $conta;
    }

    private function aparelho(Atleta|Responsavel $conta, string $token = 'ExpoPushToken[teste]', bool $ativo = true): void
    {
        Dispositivo::registrar($conta, ['token' => $token, 'plataforma' => 'android', 'notificacoes_ativas' => $ativo]);
    }

    public function test_aviso_envia_payload_exato_so_depois_do_commit_e_da_resposta(): void
    {
        $conta = $this->atleta();
        $this->aparelho($conta);
        Route::post('/teste-push', function () use ($conta) {
            DB::transaction(function () use ($conta) {
                Notificacao::aviso([$conta->getKey()], 'Venha ao treino.', null);
                Http::assertNothingSent();
                $this->assertCount(0, defer());
            });
            Http::assertNothingSent();
            $this->assertCount(1, defer());

            return response()->json(['success' => true]);
        });
        $this->postJson('/teste-push')->assertOk(); // Kernel::terminate executa defer() após criar a resposta.
        $aviso = Notificacao::sole();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->url() === ClienteExpo::URL && $r->data() === [[
            'to' => 'ExpoPushToken[teste]', 'title' => 'Aviso da escolinha', 'body' => 'Venha ao treino.',
            'sound' => 'default', 'channelId' => 'default', 'priority' => 'high',
            'data' => ['id_notificacao' => $aviso->getKey(), 'id_atleta' => $conta->getKey(), 'tipo' => 'AVISO'],
        ]]);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_inscricao_inclui_id_evento_inteiro(): void
    {
        $conta = $this->atleta();
        $this->aparelho($conta);
        $evento = EventoCalendario::criarPor(null, [
            'titulo_evento_calendario' => 'Treino teste', 'tipo_evento_calendario' => 'TREINO',
            'data_evento_calendario' => now()->addWeek()->toDateString(), 'status_evento_calendario' => 'ATIVO',
        ]);
        $evento->inscrever($conta->getKey(), 'INDIVIDUAL', null);
        defer()->invoke();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r[0]['data']['id_evento'] === $evento->getKey()
            && $r[0]['data']['tipo'] === 'INSCRICAO' && $r[0]['title'] === 'Nova atividade na sua agenda');
    }

    public function test_responsaveis_vinculados_recebem_e_multiplos_filhos_prefixam_primeiro_nome(): void
    {
        $conta = $this->atleta();
        $irma = $this->atleta('Maria Silva');
        $responsavel = Responsavel::findOrFail($this->criarResponsavel($conta->getKey()));
        $outroResponsavel = Responsavel::findOrFail($this->criarResponsavel($irma->getKey()));
        DB::table('tbl_atleta_responsavel')->insert([
            'id_atleta' => $irma->getKey(), 'id_responsavel' => $responsavel->getKey(), 'grau_parentesco_responsavel' => 'Mãe',
        ]);
        $this->aparelho($conta, 'ExpoPushToken[filha]');
        $this->aparelho($responsavel, 'ExpoPushToken[mae]');
        $this->aparelho($outroResponsavel, 'ExpoPushToken[outro]');
        $this->respostaExpo = Http::response(['data' => array_fill(0, 2, ['status' => 'ok'])]);
        Notificacao::aviso([$conta->getKey()], 'Recado da secretaria.', null);
        defer()->invoke();
        Http::assertSentCount(1);
        Http::assertSent(function ($r) use ($conta) {
            $mensagens = collect($r->data())->keyBy('to');

            return $mensagens->count() === 2 && ! $mensagens->has('ExpoPushToken[outro]')
                && $mensagens['ExpoPushToken[mae]']['title'] === 'Joana · Aviso da escolinha'
                && $mensagens['ExpoPushToken[mae]']['data']['id_atleta'] === $conta->getKey()
                && $mensagens['ExpoPushToken[filha]']['title'] === 'Aviso da escolinha';
        });
    }

    public function test_responsavel_com_um_filho_recebe_sem_prefixo_e_sem_aparelho_do_atleta(): void
    {
        $conta = $this->atleta();
        $responsavel = Responsavel::findOrFail($this->criarResponsavel($conta->getKey()));
        $this->aparelho($responsavel);
        // O legado permite vínculo repetido: não deve duplicar push nem contar como dois filhos.
        DB::table('tbl_atleta_responsavel')->insert([
            'id_atleta' => $conta->getKey(), 'id_responsavel' => $responsavel->getKey(), 'grau_parentesco_responsavel' => 'Mãe',
        ]);
        Notificacao::aviso([$conta->getKey()], 'Aviso só para o pai.', null);
        defer()->invoke();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => count($r->data()) === 1);
        Http::assertSent(fn ($r) => $r[0]['data']['id_atleta'] === $conta->getKey() && $r[0]['title'] === Notificacao::TITULO_AVISO);
    }

    public function test_sem_aparelho_ou_com_aparelhos_desligados_preserva_aviso_sem_http(): void
    {
        $conta = $this->atleta();
        $responsavel = Responsavel::findOrFail($this->criarResponsavel($conta->getKey()));
        Notificacao::aviso([$conta->getKey()], 'Primeiro recado.', null);
        defer()->invoke();
        $this->aparelho($conta, 'ExpoPushToken[filha]', false);
        $this->aparelho($responsavel, 'ExpoPushToken[mae]', false);
        Notificacao::aviso([$conta->getKey()], 'Segundo recado.', null);
        defer()->invoke();
        Http::assertNothingSent();
        $this->assertDatabaseCount('tbl_notificacao', 2);
    }

    public function test_rollback_externo_descarta_todos_os_disparos_inclusive_commits_internos(): void
    {
        $conta = $this->atleta();
        $this->aparelho($conta);
        DB::beginTransaction();
        DB::transaction(fn () => Notificacao::aviso([$conta->getKey()], 'Não publicar.', null));
        $this->assertCount(0, defer());
        DB::rollBack();
        defer()->invoke();
        Http::assertNothingSent();
        $this->assertDatabaseCount('tbl_notificacao', 0);
    }

    public function test_device_not_registered_remove_token_mas_nao_o_aviso(): void
    {
        $conta = $this->atleta();
        $this->aparelho($conta);
        $this->respostaExpo = Http::response(['data' => [['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']]]]);
        Notificacao::aviso([$conta->getKey()], 'Recado.', null);
        defer()->invoke();
        $this->assertDatabaseCount('tbl_dispositivo', 0);
        $this->assertDatabaseCount('tbl_notificacao', 1);
    }

    public static function falhas(): iterable
    {
        yield 'HTTP 500' => [false];
        yield 'timeout' => [true];
    }

    #[DataProvider('falhas')]
    public function test_expo_fora_do_ar_nao_quebra_acao_do_painel(bool $timeout): void
    {
        Log::spy();
        $conta = $this->atleta();
        $this->aparelho($conta);
        $this->respostaExpo = $timeout ? Http::failedConnection() : Http::response([], 500);
        $this->comoAdmin()->post(route('admin.notificacoes.store'), [
            'mensagem_notificacao' => 'Recado.', 'atletas' => [$conta->getKey()],
        ])->assertRedirect()->assertSessionHas('sucesso', 'Aviso enviado. 1 atleta(s) notificado(s).');
        $this->assertDatabaseCount('tbl_notificacao', 1);
        $this->assertDatabaseCount('tbl_dispositivo', 1);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_inativacao_do_atleta_antes_do_envio_impede_push_para_ele_e_responsavel(): void
    {
        $conta = $this->atleta();
        $responsavel = Responsavel::findOrFail($this->criarResponsavel($conta->getKey()));
        $this->aparelho($conta, 'ExpoPushToken[filha]');
        $this->aparelho($responsavel, 'ExpoPushToken[mae]');
        Notificacao::aviso([$conta->getKey()], 'Recado.', null);
        $conta->update(['status_atleta' => 'INATIVO']);
        defer()->invoke();
        Http::assertNothingSent();
    }

    public function test_token_transferido_ou_desligado_antes_do_envio_nao_recebe_aviso_da_conta_antiga(): void
    {
        $conta = $this->atleta();
        $outra = $this->atleta('Outra pessoa');
        $this->aparelho($conta, 'ExpoPushToken[celular]');
        $this->aparelho($conta, 'ExpoPushToken[outro-celular]');
        Notificacao::aviso([$conta->getKey()], 'Recado.', null);
        $this->aparelho($outra, 'ExpoPushToken[celular]');
        $this->aparelho($conta, 'ExpoPushToken[outro-celular]', false);
        defer()->invoke();
        Http::assertNothingSent();
    }

    public function test_lote_com_varios_avisos_preserva_id_e_destinatario_de_cada_um(): void
    {
        $primeira = $this->atleta();
        $segunda = $this->atleta('Maria Silva');
        $this->aparelho($primeira, 'ExpoPushToken[joana]');
        $this->aparelho($segunda, 'ExpoPushToken[maria]');
        $this->respostaExpo = Http::response(['data' => array_fill(0, 2, ['status' => 'ok'])]);
        Notificacao::aviso([$segunda->getKey(), $primeira->getKey(), $primeira->getKey()], 'Recado.', null);
        defer()->invoke();
        $avisos = Notificacao::pluck('id_notificacao', 'id_atleta');
        Http::assertSentCount(1);
        Http::assertSent(function ($r) use ($avisos) {
            foreach ($r->data() as $mensagem) {
                if ($mensagem['data']['id_notificacao'] !== $avisos[$mensagem['data']['id_atleta']]) {
                    return false;
                }
            }

            return count($r->data()) === 2;
        });
    }
}
