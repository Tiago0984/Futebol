<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\EventoCalendario;
use App\Models\TipoEventoIndividual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Individuais (o item do menu em Eventos, 09/11/2026; antes, o Calendário filtrado por ?ramo=individuais): as
 * atividades em que o técnico escolhe os atletas um a um (exame, avaliação física, reunião), sem
 * categoria (EventoCalendario::scopeDoRamo). Um cartão por atividade com quem vai fazer e quantos leram o
 * aviso, em duas abas (Próximos e Já realizados), e o "Novo evento individual". Editar, inscrever e remover
 * atletas continuam na tela do evento; cancelar e ocultar, nos botões do cartão (voltam para cá).
 */
class IndividuaisController extends Controller
{
    // Atletas mostrados no cartão antes do "+N"
    public const ATLETAS_NO_CARTAO = 8;

    // Já realizados por página (os próximos vêm todos)
    public const REALIZADOS_POR_PAGINA = 15;

    // Filtro "Tipo": os tipos individuais (a opção "Peneira" saiu junto com a chave do formulário, 09/11/2026;
    // as peneiras já cadastradas continuam com o selo e entram em Avaliação)
    public const FILTROS_DE_TIPO = [
        'AVALIACAO' => 'Avaliação',
        'REUNIAO'   => 'Reunião',
        'EVENTO'    => 'Evento',
    ];

    public function index(Request $request)
    {
        // Filtro "Tipo": os três de sempre e os tipos cadastrados (inclusive os desativados, que têm eventos antigos)
        $tiposCadastrados = TipoEventoIndividual::orderBy('nome_tipo_evento_individual')->get();
        $valoresDeTipo = [...array_keys(self::FILTROS_DE_TIPO), ...$tiposCadastrados->map->valorNoSelect()->all()];

        $filtros = [
            'tipo'    => in_array((string) $request->query('tipo'), $valoresDeTipo, true) ? $request->query('tipo') : '',
            'busca'   => trim((string) $request->query('busca')),
        ];
        $aba = $request->query('aba') === 'realizados' ? 'realizados' : 'proximos';

        $idTipoCadastrado = str_starts_with($filtros['tipo'], TipoEventoIndividual::PREFIXO_NO_SELECT)
            ? (int) substr($filtros['tipo'], strlen(TipoEventoIndividual::PREFIXO_NO_SELECT))
            : null;

        $base = fn () => EventoCalendario::doRamo('individuais')
            ->when($idTipoCadastrado, fn ($q) => $q->where('id_tipo_evento_individual', $idTipoCadastrado))
            ->when($filtros['tipo'] !== '' && ! $idTipoCadastrado, fn ($q) => $q->where('tipo_evento_calendario', $filtros['tipo']))
            // Busca pelo título ou pelo nome de um atleta inscrito ("o que a Ana tem marcado?")
            ->when($filtros['busca'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('titulo_evento_calendario', 'like', '%' . $filtros['busca'] . '%')
                ->orWhereHas('inscricoes.atleta', fn ($a) => $a->where('nome_atleta', 'like', '%' . $filtros['busca'] . '%'))))
            // Ocultos nunca aparecem aqui (dono do projeto, 09/11/2026); continuam no filtro "Situação" do Calendário
            ->where('status_evento_calendario', '<>', 'INATIVO');

        $carregar = fn ($q) => $q
            ->with([
                'comissaoTecnica',
                'tipoIndividual',
                // Só os ativos aparecem (a inscrição do inativo fica, mas não conta; CLAUDE.md, seção 4)
                'inscricoes' => fn ($i) => $i->whereHas('atleta', fn ($a) => $a->where('status_atleta', 'ATIVO'))
                    ->with('atleta:id_atleta,nome_atleta'),
            ])
            ->comAlteracao();

        // Próximos: do mais perto ao mais longe; já realizados: do mais recente ao mais antigo, por página
        $proximos = $carregar($base()->naoConcluidos())
            ->orderBy('data_evento_calendario')->orderBy('horario_inicio_evento_calendario')
            ->get();
        $realizados = $carregar($base()->concluidos())
            ->orderByDesc('data_evento_calendario')->orderByDesc('horario_inicio_evento_calendario')
            ->paginate(self::REALIZADOS_POR_PAGINA, ['*'], 'pagina')
            ->appends([...array_filter($filtros), 'aba' => 'realizados']);

        $eventos = $aba === 'realizados' ? collect($realizados->items()) : $proximos;
        $leituras = $this->leituras($eventos->pluck('id_evento_calendario')->all());

        $caminho = [
            ['Eventos', route('admin.calendario.index')],
            ['Individuais', null],
        ];

        // Formulário "Novo evento individual": atletas ativos pela categoria atual
        $atletasParaIndividual = Atleta::ativosPorCategoria();

        return view('admin.individuais.index', compact(
            'filtros', 'aba', 'proximos', 'realizados', 'eventos', 'leituras', 'caminho', 'atletasParaIndividual',
            'tiposCadastrados',
        ));
    }

    /**
     * "Adicionar novo tipo…" do select Tipo do evento individual (09/11/2026): cadastra sem sair do formulário (JSON,
     * como o "Novo local") e devolve o valor do select ("tipo:ID"). Nome único (a collation ignora maiúsculas e
     * acentos) e diferente dos três de sempre. Só o nome: o tipo pode ser qualquer coisa ("Viagem"), então a base é
     * sempre EVENTO, o tipo genérico (dono do projeto, 09/11/2026: sem o "Funciona como").
     */
    public function novoTipo(Request $request)
    {
        // Em maiúsculas, para manter o padrão (dono do projeto, 09/11/2026); espaços repetidos viram um só
        $request->merge(['nome_tipo_evento_individual' => mb_strtoupper(
            preg_replace('/\s+/u', ' ', trim((string) $request->input('nome_tipo_evento_individual'))), 'UTF-8',
        )]);
        $semAcento = fn (string $texto) => mb_strtolower(Str::ascii($texto));
        $request->validate([
            'nome_tipo_evento_individual' => [
                'required', 'string', 'min:3', 'max:' . TipoEventoIndividual::NOME_MAX,
                Rule::unique('tbl_tipo_evento_individual', 'nome_tipo_evento_individual'),
                function ($campo, $valor, $falhar) use ($semAcento) {
                    $fixos = array_map($semAcento, array_intersect_key(EventoCalendario::TIPOS, array_flip(EventoCalendario::TIPOS_INDIVIDUAIS)));
                    if (in_array($semAcento((string) $valor), $fixos, true)) {
                        $falhar('Este tipo já existe na lista (Avaliação, Reunião ou Evento).');
                    }
                },
            ],
        ], [
            'nome_tipo_evento_individual.required' => 'Escreva o nome do tipo.',
            'nome_tipo_evento_individual.min'      => 'O nome do tipo precisa de pelo menos 3 letras.',
            'nome_tipo_evento_individual.max'      => 'O nome do tipo pode ter até ' . TipoEventoIndividual::NOME_MAX . ' caracteres.',
            'nome_tipo_evento_individual.unique'   => 'Já existe um tipo com este nome.',
        ]);

        $tipo = new TipoEventoIndividual([
            'nome_tipo_evento_individual' => $request->input('nome_tipo_evento_individual'),
            'base_tipo_evento_individual' => TipoEventoIndividual::BASE,
        ]);
        $tipo->id_usuario = auth('admin')->id();
        $tipo->save();

        return response()->json([
            'valor'    => $tipo->valorNoSelect(),
            'nome'     => $tipo->nome_tipo_evento_individual,
            'rotulo'   => $tipo->rotulo,
            'mensagem' => "Tipo {$tipo->nome_tipo_evento_individual} cadastrado.",
        ], 201);
    }

    /**
     * Quantos atletas foram avisados da inscrição e quantos já leram, por evento, numa consulta só:
     * [id_evento => ['avisados' => n, 'leram' => n]]. Quem foi inscrito sem aviso (evento já concluído) não conta.
     */
    private function leituras(array $idsEventos): array
    {
        if (! $idsEventos) {
            return [];
        }

        return DB::table('tbl_notificacao')
            ->whereIn('id_evento_calendario', $idsEventos)
            ->where('tipo_notificacao', 'INSCRICAO')
            ->groupBy('id_evento_calendario')
            ->selectRaw('id_evento_calendario, COUNT(DISTINCT id_atleta) AS avisados, '
                . 'COUNT(DISTINCT CASE WHEN data_leitura_notificacao IS NOT NULL THEN id_atleta END) AS leram')
            ->get()
            ->mapWithKeys(fn ($l) => [(int) $l->id_evento_calendario => ['avisados' => (int) $l->avisados, 'leram' => (int) $l->leram]])
            ->all();
    }
}
