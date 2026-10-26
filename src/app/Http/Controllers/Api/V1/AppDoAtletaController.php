<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\ComissaoTecnica;
use App\Models\EventoCalendario;
use App\Models\Responsavel;
use App\Services\AppDoAtleta;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

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
    // Maior período da agenda por ?de=&ate= (sem paginação): três meses cobrem a tela de mês com as semanas das pontas
    public const PERIODO_MAX_DIAS = 93;

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

    // GET /atleta/estatisticas (e /responsavel/atletas/{idAtleta}/estatisticas): partidas, convocações, cartões,
    // gols e assistências (da súmula; null sem jogo com súmula), no total, por time e na categoria atual
    public function estatisticas(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->app($request)->estatisticas()]);
    }

    // GET /campeonatos-do-atleta (e /responsavel/atletas/{idAtleta}/campeonatos-do-atleta): os campeonatos ativos e
    // não terminados de que o atleta participa (convocado ou pela categoria atual), só com as categorias dele
    public function campeonatos(Request $request)
    {
        return response()->json(['success' => true, 'data' => $this->app($request)->campeonatos()]);
    }

    // GET /agenda?page=N&tipo=JOGO,TREINO, ou GET /agenda?de=AAAA-MM-DD&ate=AAAA-MM-DD&tipo=... (período, sem página)
    public function agenda(Request $request)
    {
        $app = $this->app($request);
        $tipos = $this->tiposDaAgenda($request);

        if ($request->query('de') !== null || $request->query('ate') !== null) {
            [$de, $ate] = $this->periodoDaAgenda($request);

            return response()->json(['success' => true, 'data' => $app->agendaDoPeriodo($de, $ate, $tipos)]);
        }

        return response()->json(['success' => true, 'data' => $app->agenda($this->pagina($request), $tipos)]);
    }

    // GET /agenda/{idEvento} - o evento com o detalhe do jogo; 404 se o atleta não está inscrito, oculto ou rascunho
    public function evento(Request $request)
    {
        $evento = $this->app($request)->detalhe((int) $request->route('idEvento'));

        if (! $evento) {
            return $this->naoEncontrado('Evento não encontrado.');
        }

        return response()->json(['success' => true, 'data' => $evento]);
    }

    // GET /comissao-tecnica/{id} - perfil do profissional (Etapa C3); sem lista; 404 para desativado ou inexistente.
    // No responsável, o filho da rota é conferido antes, como nas outras rotas
    public function comissaoTecnica(Request $request)
    {
        $this->app($request);

        $profissional = ComissaoTecnica::ativos()->with(['categoriaPrincipal', 'categorias'])
            ->find((int) $request->route('id'));
        if (! $profissional) {
            return $this->naoEncontrado('Profissional não encontrado.');
        }

        return response()->json(['success' => true, 'data' => $profissional->paraOApp()]);
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

    // ?tipo=JOGO ou ?tipo=JOGO,TREINO (sem diferenciar maiúsculas); vazio = todos; tipo desconhecido: 422
    private function tiposDaAgenda(Request $request): array
    {
        $tipos = collect(explode(',', (string) $request->query('tipo', '')))
            ->map(fn ($tipo) => mb_strtoupper(trim($tipo)))->filter()->unique()->values();

        $desconhecidos = $tipos->diff(array_keys(EventoCalendario::TIPOS));
        if ($desconhecidos->isNotEmpty()) {
            throw ValidationException::withMessages(['tipo' => 'Tipo desconhecido: ' . $desconhecidos->implode(', ')
                . '. Use ' . implode(', ', array_keys(EventoCalendario::TIPOS)) . '.']);
        }

        return $tipos->all();
    }

    // ?de= e ?ate= juntos, em AAAA-MM-DD, "até" não antes de "de" e no máximo PERIODO_MAX_DIAS dias
    private function periodoDaAgenda(Request $request): array
    {
        $data = function (string $campo, string $nome) use ($request) {
            $valor = (string) $request->query($campo, '');
            $dia = preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? Carbon::createFromFormat('!Y-m-d', $valor) : null;

            if (! $dia || $dia->format('Y-m-d') !== $valor) {
                throw ValidationException::withMessages([$campo => "Informe a data \"{$nome}\" no formato AAAA-MM-DD (ex.: 2026-11-01)."]);
            }

            return $dia;
        };

        [$de, $ate] = [$data('de', 'de'), $data('ate', 'até')];

        if ($ate->lt($de)) {
            throw ValidationException::withMessages(['ate' => 'A data "até" não pode ser antes da data "de".']);
        }
        if ((int) $de->diffInDays($ate) + 1 > self::PERIODO_MAX_DIAS) {
            throw ValidationException::withMessages(['ate' => 'O período pode ter no máximo ' . self::PERIODO_MAX_DIAS . ' dias.']);
        }

        return [$de, $ate];
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
