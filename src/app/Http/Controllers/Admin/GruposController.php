<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campeonato;
use App\Models\CampeonatoGrupo;
use App\Models\EventoCalendario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Grupos e classificação de um campeonato (Fase H, Etapa H4), aberta pelo botão da tela "Jogos · Campeonato":
 * um bloco por categoria com os grupos (criar, renomear, remover o vazio), o grupo de cada participante e a
 * classificação de cada grupo (só a fase de grupos), para o admin conferir. Grupos são opcionais: categoria sem
 * grupo tem uma tabela só.
 */
class GruposController extends Controller
{
    public function show($id)
    {
        $campeonato     = Campeonato::with('categorias')->findOrFail($id);
        $participantes  = $campeonato->participantesComGrupo()->groupBy('id_categoria');
        $grupos         = $campeonato->grupos()->get()->groupBy('id_categoria');
        $classificacoes = collect($campeonato->classificacoes())->keyBy(fn ($c) => $c['categoria']->id_categoria);
        $timesPorGrupo  = DB::table('tbl_campeonato_time')->where('id_campeonato', $campeonato->id_campeonato)
            ->whereNotNull('id_campeonato_grupo')->groupBy('id_campeonato_grupo')
            ->selectRaw('id_campeonato_grupo, COUNT(*) AS total')->pluck('total', 'id_campeonato_grupo');

        $caminho = [
            ['Eventos', route('admin.calendario.index')],
            ['Campeonatos', EventoCalendario::urlDoRamo('campeonatos')],
            [$campeonato->nome_campeonato, route('admin.campeonatos.times', $campeonato->id_campeonato)],
            ['Grupos (chaves) e classificação', null],
        ];

        return view('admin.campeonatos.grupos', compact('campeonato', 'participantes', 'grupos', 'classificacoes', 'timesPorGrupo', 'caminho'));
    }

    // Cria um grupo numa categoria do campeonato (nome sem repetir na categoria)
    public function criar(Request $request, $id)
    {
        $campeonato = Campeonato::findOrFail($id);
        $idsCategorias = $campeonato->categorias()->pluck('tbl_categoria.id_categoria')->map(fn ($c) => (int) $c)->all();
        $request->merge(['nome_campeonato_grupo' => trim((string) $request->input('nome_campeonato_grupo'))]);

        $dados = $request->validate([
            'id_categoria'          => ['required', 'integer', Rule::in($idsCategorias)],
            'nome_campeonato_grupo' => ['required', 'string', 'max:' . CampeonatoGrupo::NOME_MAX,
                Rule::unique('tbl_campeonato_grupo')->where('id_campeonato', $campeonato->id_campeonato)
                    ->where('id_categoria', (int) $request->input('id_categoria'))],
        ], $this->mensagens());

        $grupo = CampeonatoGrupo::create([...$dados, 'id_campeonato' => $campeonato->id_campeonato]);

        return $this->voltar($campeonato, $grupo->id_categoria)
            ->with('sucesso', "{$grupo->nome_campeonato_grupo} criado no {$grupo->categoria->rotulo}.");
    }

    public function renomear(Request $request, $id, $grupoId)
    {
        [$campeonato, $grupo] = $this->campeonatoEGrupo($id, $grupoId);
        $request->merge(['nome_campeonato_grupo' => trim((string) $request->input('nome_campeonato_grupo'))]);

        $request->validate([
            'nome_campeonato_grupo' => ['required', 'string', 'max:' . CampeonatoGrupo::NOME_MAX,
                Rule::unique('tbl_campeonato_grupo')->where('id_campeonato', $campeonato->id_campeonato)
                    ->where('id_categoria', $grupo->id_categoria)->ignore($grupo->id_campeonato_grupo, 'id_campeonato_grupo')],
        ], $this->mensagens());

        $antes = $grupo->nome_campeonato_grupo;
        $grupo->update(['nome_campeonato_grupo' => $request->input('nome_campeonato_grupo')]);

        return $this->voltar($campeonato, $grupo->id_categoria)->with('sucesso', "{$antes} agora se chama {$grupo->nome_campeonato_grupo}.");
    }

    // Remove o grupo vazio (com times, tire os times dele antes)
    public function remover($id, $grupoId)
    {
        [$campeonato, $grupo] = $this->campeonatoEGrupo($id, $grupoId);

        if ($total = $grupo->totalDeTimes()) {
            return $this->voltar($campeonato, $grupo->id_categoria)
                ->with('erro', "O {$grupo->nome_campeonato_grupo} tem {$total} time(s). Tire os times dele antes de remover.");
        }

        $grupo->delete();

        return $this->voltar($campeonato, $grupo->id_categoria)->with('sucesso', "{$grupo->nome_campeonato_grupo} removido.");
    }

    // Grupo de cada participante da categoria (grupos[id_time] = id do grupo, vazio = sem grupo)
    public function times(Request $request, $id, $categoriaId)
    {
        $campeonato = Campeonato::findOrFail($id);
        $grupoPorTime = collect((array) $request->input('grupos', []))
            ->mapWithKeys(fn ($idGrupo, $idTime) => [(int) $idTime => filled($idGrupo) ? (int) $idGrupo : null])
            ->all();

        if ($erro = $campeonato->definirGrupos((int) $categoriaId, $grupoPorTime)) {
            return $this->voltar($campeonato, (int) $categoriaId)->with('erro', $erro);
        }

        return $this->voltar($campeonato, (int) $categoriaId)->with('sucesso', 'Grupos dos times salvos.');
    }

    // Quantos passam de cada grupo (chave) na categoria (vazio = não definido: a tabela não destaca ninguém)
    public function classificados(Request $request, $id, $categoriaId)
    {
        $campeonato = Campeonato::findOrFail($id);
        abort_unless($campeonato->categorias()->where('tbl_categoria.id_categoria', (int) $categoriaId)->exists(), 404);

        $request->validate([
            'classificados' => 'nullable|integer|min:1|max:' . Campeonato::CLASSIFICADOS_MAX,
        ], [
            'classificados.*' => 'Informe de 1 a ' . Campeonato::CLASSIFICADOS_MAX . ' classificados por grupo (chave), ou deixe vazio.',
        ]);

        $classificados = $request->filled('classificados') ? (int) $request->input('classificados') : null;
        $campeonato->definirClassificados((int) $categoriaId, $classificados);

        return $this->voltar($campeonato, (int) $categoriaId)->with('sucesso', $classificados
            ? "{$classificados} classificado(s) por grupo (chave)."
            : 'Classificados por grupo (chave) sem definição.');
    }

    private function campeonatoEGrupo($id, $grupoId): array
    {
        $campeonato = Campeonato::findOrFail($id);

        return [$campeonato, $campeonato->grupos()->with('categoria')->whereKey((int) $grupoId)->firstOrFail()];
    }

    // Volta à tela, no bloco da categoria
    private function voltar(Campeonato $campeonato, int $idCategoria)
    {
        return redirect()->to(route('admin.campeonatos.grupos', $campeonato->id_campeonato) . "#categoria-{$idCategoria}");
    }

    private function mensagens(): array
    {
        return [
            'nome_campeonato_grupo.required' => 'Informe o nome do grupo (ex.: Grupo 01).',
            'nome_campeonato_grupo.max'      => 'O nome do grupo tem até ' . CampeonatoGrupo::NOME_MAX . ' caracteres.',
            'nome_campeonato_grupo.unique'   => 'Já existe um grupo com esse nome nesta categoria.',
            'id_categoria.in'                => 'Escolha uma categoria do campeonato.',
        ];
    }
}
