<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Local;
use Illuminate\Http\Request;
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
        return view('admin.locais.form', ['local' => new Local(['nome_local' => mb_substr(trim((string) $request->query('nome')), 0, 100)])]);
    }

    public function store(Request $request)
    {
        $local = new Local($this->dados($request));
        $local->status_local = 'ATIVO';
        $local->save();

        return redirect()->route('admin.locais.index')->with('sucesso', "Local {$local->nome_local} cadastrado.");
    }

    public function edit($id)
    {
        return view('admin.locais.form', ['local' => Local::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $local = Local::findOrFail($id);
        $local->update($this->dados($request, $local));

        return redirect()->route('admin.locais.index')->with('sucesso', "Dados do local {$local->nome_local} atualizados.");
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
