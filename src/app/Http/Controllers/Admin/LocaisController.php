<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Local;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Cadastro de locais (Etapa L1, 30/10/2026): criar, editar, ativar e desativar, no molde da comissão técnica. Sem
 * exclusão: o local fica nos eventos em que já está (desativado, não aparece para novas escolhas). A lista mostra
 * também os textos de local já usados que ainda não têm cadastro, com "Cadastrar" e o nome preenchido.
 */
class LocaisController extends Controller
{
    public function index()
    {
        $locais = Local::orderByRaw("status_local = 'ATIVO' DESC")->orderBy('nome_local')->get();
        $semCadastro = Local::textosSemCadastro();

        return view('admin.locais.index', compact('locais', 'semCadastro'));
    }

    // ?nome= vem do "Cadastrar" de um texto já usado
    public function create(Request $request)
    {
        $nome = mb_substr(trim((string) $request->query('nome')), 0, 100);

        return view('admin.locais.form', ['local' => new Local(['nome_local' => $nome]), 'textoOrigem' => $nome]);
    }

    // Cadastrado a partir de um texto já usado (texto_origem): abre o Editar com o "Ligar os usos" desse texto
    public function store(Request $request)
    {
        $local = new Local($this->dados($request));
        $local->status_local = 'ATIVO';
        $local->save();

        $origem = trim((string) $request->input('texto_origem'));
        if ($origem !== '' && array_sum(Local::usosDoTexto($origem)) > 0) {
            return redirect()->route('admin.locais.edit', ['id' => $local->id_local, 'texto' => $origem])
                ->with('sucesso', "Local {$local->nome_local} cadastrado. Agora você pode ligar a ele os usos de \"{$origem}\".");
        }

        return redirect()->route('admin.locais.index')->with('sucesso', "Local {$local->nome_local} cadastrado.");
    }

    // "Novo local" dentro dos formulários (L2): o mesmo cadastro, em JSON, para o select já escolher o local novo
    public function rapido(Request $request)
    {
        $local = new Local($this->dados($request));
        $local->status_local = 'ATIVO';
        $local->save();

        return response()->json([
            'id_local' => $local->id_local,
            'nome'     => $local->nome_local,
            'endereco' => $local->enderecoEmUmaLinha(),
            'mensagem' => "Local {$local->nome_local} cadastrado.",
        ], 201);
    }

    // ?texto= escolhe o texto do "Ligar os usos" (padrão: o próprio nome do local, se tiver usos soltos)
    public function edit(Request $request, $id)
    {
        $local = Local::findOrFail($id);
        $soltos = Local::textosSoltos();
        $texto = trim((string) $request->query('texto'));
        $texto = $soltos->first(fn ($u) => mb_strtolower($u->texto) === mb_strtolower($texto))?->texto
            ?? $soltos->first(fn ($u) => mb_strtolower($u->texto) === mb_strtolower($local->nome_local))?->texto;

        return view('admin.locais.form', [
            'local'       => $local,
            'soltos'      => $soltos,
            'textoLigar'  => $texto,
            'usosDoTexto' => $texto ? Local::usosDoTexto($texto) : null,
        ]);
    }

    public function update(Request $request, $id)
    {
        $local = Local::findOrFail($id);
        $nomeAntes = $local->nome_local;

        // Renomear leva o nome novo para os eventos, linhas e campeonatos ligados (Local::booted), junto
        DB::transaction(fn () => $local->update($this->dados($request, $local)));

        $mensagem = "Dados do local {$local->nome_local} atualizados.";
        if ($nomeAntes !== $local->nome_local) {
            $mensagem .= ' O nome novo vale em todos os eventos, horários da grade e campeonatos que usam este local.';
        }

        return redirect()->route('admin.locais.index')->with('sucesso', $mensagem);
    }

    // "Ligar os N usos": os eventos, linhas da grade e campeonatos com o texto (e sem local) passam a usar este local
    public function ligarUsos(Request $request, $id)
    {
        $local = Local::findOrFail($id);
        $texto = trim((string) $request->input('texto'));

        if ($texto === '' || array_sum(Local::usosDoTexto($texto)) === 0) {
            return back()->with('erro', 'Nada para ligar: nenhum evento, horário da grade ou campeonato usa esse texto sem local.');
        }

        $ligados = $local->ligarUsos($texto, auth('admin')->id());
        $partes = array_filter([
            $ligados['tbl_evento_calendario'] ? "{$ligados['tbl_evento_calendario']} evento(s)" : null,
            $ligados['tbl_grade_treino'] ? "{$ligados['tbl_grade_treino']} horário(s) da grade" : null,
            $ligados['tbl_campeonato'] ? "{$ligados['tbl_campeonato']} campeonato(s)" : null,
        ]);

        return redirect()->route('admin.locais.index')
            ->with('sucesso', 'Ligados a ' . $local->nome_local . ': ' . implode(', ', $partes) . '. Ninguém foi avisado (o lugar é o mesmo).');
    }

    // Desativado: some das novas escolhas, mas continua nos eventos em que já está
    public function toggleStatus($id)
    {
        $local = Local::findOrFail($id);
        $local->status_local = $local->estaAtivo() ? 'INATIVO' : 'ATIVO';
        $local->save();

        return redirect()->route('admin.locais.index')->with('sucesso', $local->estaAtivo()
            ? "O local {$local->nome_local} foi reativado."
            : "O local {$local->nome_local} foi desativado: não aparece mais para novas escolhas.");
    }

    private function dados(Request $request, ?Local $atual = null): array
    {
        $request->merge(collect(['nome_local', 'rua_local', 'numero_local', 'complemento_local', 'bairro_local', 'cidade_local', 'estado_local', 'cep_local', 'mapa_url_local'])
            ->mapWithKeys(fn ($campo) => [$campo => is_string($request->input($campo)) ? trim($request->input($campo)) : $request->input($campo)])->all());

        $request->validate([
            'nome_local'        => ['required', 'string', 'max:100',
                Rule::unique('tbl_local', 'nome_local')->ignore($atual?->id_local, 'id_local')],
            'rua_local'         => 'required|string|max:100',
            'numero_local'      => 'required|string|max:10',
            'complemento_local' => 'nullable|string|max:100',
            'bairro_local'      => 'required|string|max:50',
            'cidade_local'      => 'required|string|max:50',
            'estado_local'      => ['required', 'string', 'regex:/^[A-Za-z]{2}$/'],
            'cep_local'         => ['nullable', 'string', 'regex:/^\d{5}-?\d{3}$/'],
            'mapa_url_local'    => ['nullable', 'string', 'max:500', function ($campo, $valor, $falha) {
                if (! Local::linkDoGoogleMaps($valor)) {
                    $falha('Cole um link do Google Maps (ex.: https://maps.app.goo.gl/…).');
                }
            }],
        ], [
            // O admin usa o idioma do config/app.php (as mensagens em português estão ligadas só na API): todas aqui
            'nome_local.required'   => 'Informe o nome do local.',
            'nome_local.max'        => 'O nome pode ter no máximo 100 caracteres.',
            'nome_local.unique'     => 'Já existe um local com este nome.',
            'rua_local.required'    => 'Informe a rua.',
            'rua_local.max'         => 'A rua pode ter no máximo 100 caracteres.',
            'numero_local.required' => 'Informe o número (ou S/N, sem número).',
            'numero_local.max'      => 'O número pode ter no máximo 10 caracteres.',
            'complemento_local.max' => 'O complemento pode ter no máximo 100 caracteres.',
            'bairro_local.required' => 'Informe o bairro.',
            'bairro_local.max'      => 'O bairro pode ter no máximo 50 caracteres.',
            'cidade_local.required' => 'Informe a cidade.',
            'cidade_local.max'      => 'A cidade pode ter no máximo 50 caracteres.',
            'estado_local.required' => 'Informe a UF.',
            'estado_local.regex'    => 'Informe a UF com 2 letras (ex.: SP).',
            'cep_local.regex'       => 'Informe um CEP com 8 dígitos (ex.: 13560-000).',
            'mapa_url_local.max'    => 'O link do mapa pode ter no máximo 500 caracteres.',
        ]);

        return [
            ...$request->only(['nome_local', 'rua_local', 'numero_local', 'bairro_local', 'cidade_local', 'estado_local', 'cep_local']),
            'complemento_local' => $request->filled('complemento_local') ? $request->input('complemento_local') : null,
            'mapa_url_local'    => $request->filled('mapa_url_local') ? $request->input('mapa_url_local') : null,
        ];
    }
}
