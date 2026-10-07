<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ListaPorMes;
use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\Notificacao;
use Illuminate\Http\Request;

/**
 * Página geral das notificações enviadas aos atletas (Fase 10, Etapa 3), só leitura e só no admin (dados
 * de menores). Inclui as AGENDA, que não têm evento. Por mês do envio, com filtros de atleta, tipo e
 * leitura do atleta na URL; 50 por página, da mais nova para a mais antiga (no empate, pelo id: a ordem
 * real, CLAUDE.md seção 1, "Relógio do WSL2").
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

        // Atleta (com o número de responsáveis), evento e quem fez a ação carregados de uma vez; as leituras
        // dos responsáveis contadas na mesma consulta das notificações
        $notificacoes = Notificacao::query()
            ->with([
                'atleta'  => fn ($q) => $q->select('id_atleta', 'nome_atleta')->withCount('responsaveis'),
                'evento'  => fn ($q) => $q->select('id_evento_calendario', 'titulo_evento_calendario'),
                'usuario' => fn ($q) => $q->select('id_usuario', 'nome_usuario'),
            ])
            ->withCount('leiturasDosResponsaveis')
            ->whereBetween('data_notificacao', [$mes->copy()->startOfMonth(), $mes->copy()->endOfMonth()])
            ->when($filtros['atleta'], fn ($q, $id) => $q->where('id_atleta', $id))
            ->when($filtros['tipo'], fn ($q, $tipo) => $q->where('tipo_notificacao', $tipo))
            ->when($filtros['leitura'] === 'lidas', fn ($q) => $q->whereNotNull('data_leitura_notificacao'))
            ->when($filtros['leitura'] === 'nao_lidas', fn ($q) => $q->naoLidas())
            ->orderByDesc('data_notificacao')
            ->orderByDesc('id_notificacao')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        // Select de atletas: só quem já recebeu alguma notificação
        $atletas = Atleta::whereIn('id_atleta', Notificacao::select('id_atleta'))
            ->orderBy('nome_atleta')
            ->get(['id_atleta', 'nome_atleta']);

        $mesesLista = $this->mesesEntre([Notificacao::min('data_notificacao'), now(), $mes]);

        return view('admin.notificacoes.index', compact('notificacoes', 'filtros', 'mes', 'mesesLista', 'atletas'));
    }

    // Filtros da URL; valor inválido vira vazio (sem filtro). O atleta só vale se existir
    private function filtrosDaLista(Request $request): array
    {
        $atleta  = (string) $request->query('atleta', '');
        $tipo    = (string) $request->query('tipo', '');
        $leitura = (string) $request->query('leitura', '');

        return [
            'atleta'  => ctype_digit($atleta) && Atleta::whereKey((int) $atleta)->exists() ? (int) $atleta : '',
            'tipo'    => array_key_exists($tipo, Notificacao::TIPOS) ? $tipo : '',
            'leitura' => array_key_exists($leitura, self::LEITURAS) ? $leitura : '',
        ];
    }
}
