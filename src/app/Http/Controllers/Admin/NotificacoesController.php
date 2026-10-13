<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ListaPorMes;
use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\EventoCalendario;
use App\Models\Notificacao;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Página geral das notificações enviadas aos atletas (Fase 10, Etapa 3), só leitura e só no admin (dados
 * de menores). Inclui as AGENDA e os AVISO, que não têm evento. Uma linha por envio (a mesma notificação
 * para vários atletas, Notificacao::COLUNAS_DO_ENVIO), com os atletas e a leitura de cada um dentro. Por mês
 * do envio, com filtros de atleta, tipo e leitura do atleta na URL (valem por atleta, antes de juntar);
 * 50 envios por página, do mais novo para o mais antigo (no empate, pelo id: a ordem real, CLAUDE.md seção 1,
 * "Relógio do WSL2"). Filtro "evento" (o "ver todas" da tela do evento): só as daquele evento, de qualquer
 * mês de envio (o mês deixa de valer).
 * O botão "Novo aviso" (store) é o único envio feito por aqui: o recado livre do admin (tipo AVISO).
 */
class NotificacoesController extends Controller
{
    use ListaPorMes;

    public const POR_PAGINA = 50;

    // Filtro de leitura do atleta: valor da URL => rótulo
    public const LEITURAS = [
        'lidas'     => 'Lidas',
        'nao_lidas' => 'Não lidas',
    ];

    public function index(Request $request)
    {
        $mes     = $this->mesDaLista($request->query('mes'));
        $filtros = $this->filtrosDaLista($request);

        // Evento filtrado (aviso no topo): só as dele, de qualquer mês. Evento inexistente: sem filtro
        $eventoFiltrado = $filtros['evento']
            ? EventoCalendario::find($filtros['evento'], ['id_evento_calendario', 'titulo_evento_calendario', 'data_evento_calendario'])
            : null;
        $filtros['evento'] = $eventoFiltrado?->id_evento_calendario ?? '';

        // Notificações do mês (ou do evento) com os filtros; cada filtro vale por atleta, antes de juntar
        $filtradas = Notificacao::query()
            ->when($eventoFiltrado,
                fn ($q) => $q->where('id_evento_calendario', $eventoFiltrado->id_evento_calendario),
                fn ($q) => $q->whereBetween('data_notificacao', [$mes->copy()->startOfMonth(), $mes->copy()->endOfMonth()]))
            ->when($filtros['atleta'], fn ($q, $id) => $q->where('id_atleta', $id))
            ->when($filtros['tipo'], fn ($q, $tipo) => $q->where('tipo_notificacao', $tipo))
            ->when($filtros['leitura'] === 'lidas', fn ($q) => $q->whereNotNull('data_leitura_notificacao'))
            ->when($filtros['leitura'] === 'nao_lidas', fn ($q) => $q->naoLidas());

        // Uma linha por envio (Notificacao::COLUNAS_DO_ENVIO), com quantos atletas, quantos leram e a leitura dos
        // responsáveis somadas na mesma consulta; evento e quem fez a ação carregados de uma vez
        $notificacoes = (clone $filtradas)
            ->select(Notificacao::COLUNAS_DO_ENVIO)
            ->selectRaw('MAX(id_notificacao) AS id_notificacao, COUNT(*) AS total_atletas, COUNT(data_leitura_notificacao) AS total_lidas')
            ->selectRaw('SUM((SELECT COUNT(*) FROM tbl_notificacao_leitura nl WHERE nl.id_notificacao = tbl_notificacao.id_notificacao)) AS leituras_responsaveis')
            ->selectRaw('SUM((SELECT COUNT(*) FROM tbl_atleta_responsavel ar WHERE ar.id_atleta = tbl_notificacao.id_atleta)) AS total_responsaveis')
            ->groupBy(Notificacao::COLUNAS_DO_ENVIO)
            ->with([
                'evento'  => fn ($q) => $q->select('id_evento_calendario', 'titulo_evento_calendario'),
                'usuario' => fn ($q) => $q->select('id_usuario', 'nome_usuario'),
            ])
            ->orderByDesc('data_notificacao')
            ->orderByRaw('MAX(id_notificacao) DESC')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        // Os atletas de cada envio da página (com a leitura de cada um), numa consulta só, pelos horários dos envios
        $porEnvio = $notificacoes->isEmpty() ? collect() : (clone $filtradas)
            ->whereIn('data_notificacao', $notificacoes->getCollection()->map(fn ($n) => $n->data_notificacao->format('Y-m-d H:i:s'))->unique()->values())
            ->with(['atleta' => fn ($q) => $q->select('id_atleta', 'nome_atleta')])
            ->get([...Notificacao::COLUNAS_DO_ENVIO, 'id_notificacao', 'id_atleta', 'data_leitura_notificacao'])
            ->sortBy(fn ($n) => mb_strtolower($n->atleta?->nome_atleta ?? ''))
            ->groupBy(fn ($n) => Notificacao::chaveDoEnvio($n));
        $notificacoes->getCollection()->each(fn ($envio) => $envio->setRelation('destinatarios', $porEnvio[Notificacao::chaveDoEnvio($envio)] ?? collect()));

        $totalNotificacoes = (clone $filtradas)->count();

        // Select de atletas: só quem já recebeu alguma notificação
        $atletas = Atleta::whereIn('id_atleta', Notificacao::select('id_atleta'))
            ->orderBy('nome_atleta')
            ->get(['id_atleta', 'nome_atleta']);

        $mesesLista = $this->mesesEntre([Notificacao::min('data_notificacao'), now(), $mes]);

        // "Novo aviso": atletas ativos pela categoria atual, para escolher quem recebe
        $atletasParaAviso = Atleta::ativosPorCategoria();

        return view('admin.notificacoes.index', compact(
            'notificacoes', 'totalNotificacoes', 'filtros', 'mes', 'mesesLista', 'atletas', 'eventoFiltrado', 'atletasParaAviso',
        ));
    }

    /**
     * Aviso livre (tipo AVISO, sem evento): o admin escreve a mensagem (o título é sempre
     * Notificacao::TITULO_AVISO) e escolhe os atletas. Cada atleta ATIVO escolhido recebe uma notificação no
     * app (e os responsáveis dele a veem, como as outras). Volta para a lista do mês atual, com o aviso no topo.
     */
    public function store(Request $request)
    {
        $dados = $request->validate([
            'mensagem_notificacao' => ['required', 'string', 'max:' . Notificacao::MENSAGEM_MAX],
            'atletas'              => ['required', 'array'],
            'atletas.*'            => ['integer', Rule::exists('tbl_atletas', 'id_atleta')->where('status_atleta', 'ATIVO')],
        ], [
            'atletas.required'  => 'Escolha pelo menos um atleta.',
            'atletas.*.exists'  => 'Algum atleta escolhido não está mais ativo. Confira a lista e envie de novo.',
        ]);

        $notificados = Notificacao::aviso(
            array_map('intval', $dados['atletas']), $dados['mensagem_notificacao'], auth('admin')->id(),
        );

        return redirect()->route('admin.notificacoes.index', ['mes' => now()->format('Y-m')])
            ->with('sucesso', 'Aviso enviado.' . Notificacao::textoNotificados($notificados));
    }

    // Filtros da URL; valor inválido vira vazio (sem filtro). O atleta só vale se existir; o evento, no index
    private function filtrosDaLista(Request $request): array
    {
        $evento  = (string) $request->query('evento', '');
        $atleta  = (string) $request->query('atleta', '');
        $tipo    = (string) $request->query('tipo', '');
        $leitura = (string) $request->query('leitura', '');

        return [
            'evento'  => ctype_digit($evento) ? (int) $evento : '',
            'atleta'  => ctype_digit($atleta) && Atleta::whereKey((int) $atleta)->exists() ? (int) $atleta : '',
            'tipo'    => array_key_exists($tipo, Notificacao::TIPOS) ? $tipo : '',
            'leitura' => array_key_exists($leitura, self::LEITURAS) ? $leitura : '',
        ];
    }
}
