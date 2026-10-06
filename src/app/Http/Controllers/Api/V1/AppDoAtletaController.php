<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\Responsavel;
use App\Services\AppDoAtleta;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * Agenda, avisos e dados do atleta no app (Fase 9), para os dois perfis com as mesmas regras
 * (App\Services\AppDoAtleta):
 * - atleta: /v1/agenda, /v1/notificacoes... (o próprio atleta, pelo token);
 * - responsável: /v1/responsavel/atletas/{idAtleta}/agenda... (o filho, em modo leitura).
 * No responsável, o atleta precisa ser filho dele e estar ATIVO, senão 404; o aviso precisa ser daquele
 * atleta, senão 404. Os parâmetros vêm de $request->route(), porque as duas rotas têm formas diferentes.
 */
class AppDoAtletaController extends Controller
{
    // GET /responsavel/atletas - os filhos ATIVO, cada um com os avisos não lidos pelo responsável
    public function filhos(Request $request)
    {
        $responsavel = $request->user();

        $filhos = collect(ResponsavelController::atletasDoResponsavel($responsavel))
            ->map(fn ($filho) => $filho + [
                'notificacoes_nao_lidas' => (new AppDoAtleta(Atleta::find($filho['id_atleta']), $responsavel))->naoLidos(),
            ]);

        return response()->json(['success' => true, 'data' => $filhos->values()->all()]);
    }

    // GET /responsavel/atletas/{idAtleta} - os mesmos dados de GET /atleta, sem edição
    public function dados(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->app($request)->dados()]);
    }

    // GET /agenda?page=N
    public function agenda(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->app($request)->agenda($this->pagina($request))]);
    }

    // GET /notificacoes?page=N
    public function notificacoes(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->app($request)->avisos($this->pagina($request))]);
    }

    // GET /notificacoes/nao-lidas
    public function naoLidas(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['nao_lidas' => $this->app($request)->naoLidos()]]);
    }

    // PATCH /notificacoes/{idNotificacao}/lida
    public function marcarLida(Request $request)
    {
        $aviso = $this->app($request)->marcarLido((int) $request->route('idNotificacao'));

        if (! $aviso) {
            return $this->naoEncontrado('Notificação não encontrada.');
        }

        return response()->json(['success' => true, 'data' => $aviso]);
    }

    // PATCH /notificacoes/lidas
    public function marcarTodasLidas(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['marcadas' => $this->app($request)->marcarTodosLidos()]]);
    }

    /**
     * Atleta logado: ele mesmo. Responsável logado: o filho da rota, só se for dele e estiver ATIVO
     * (atleta de outra família, inativo ou inexistente dão o mesmo 404).
     */
    private function app(Request $request): AppDoAtleta
    {
        $usuario = $request->user();

        if ($usuario instanceof Atleta) {
            return new AppDoAtleta($usuario);
        }

        // Busca pelo Atleta (não pela relação do responsável, que traria o pivô no JSON dos dados)
        /** @var Responsavel $usuario */
        $filho = Atleta::where('id_atleta', (int) $request->route('idAtleta'))
            ->where('status_atleta', 'ATIVO')
            ->whereHas('responsaveis', fn ($q) => $q->where('tbl_responsavel.id_responsavel', $usuario->id_responsavel))
            ->first();

        if (! $filho) {
            throw new HttpResponseException($this->naoEncontrado('Atleta não encontrado.'));
        }

        return new AppDoAtleta($filho, $usuario);
    }

    private function pagina(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }

    private function naoEncontrado(string $mensagem)
    {
        return response()->json(['success' => false, 'message' => $mensagem], 404);
    }
}
