<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\Responsavel;
use App\Models\Endereco;
use App\Models\Categoria;
use App\Models\Time;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AtletasController extends Controller
{
    public function index()
    {
        $atletas = Atleta::with([
            'categorias',
            'responsaveis',
            'endereco',
            'times',
        ])->whereIn('status_atleta', ['ATIVO', 'Ativo', 'ativo', 'INATIVO', 'Inativo', 'inativo'])
          ->orderBy('nome_atleta')->get();

        $categorias = Categoria::orderBy('nome_categoria')->get();
        $times = Time::orderBy('nome_time')->get();

        return view('admin.atletas.index', compact('atletas', 'categorias', 'times'));
    }

    public function create()
    {
        $categorias = Categoria::orderBy('nome_categoria')->get();
        return view('admin.atletas.create', compact('categorias'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome_atleta'                 => 'required|string|max:255',
            'data_nasc_atleta'            => 'required|date',
            'cpf_atleta'                  => 'required|string|max:14|unique:tbl_atletas,cpf_atleta',
            'rg_atleta'                   => 'required|string|max:20',
            'numero_matricula_atleta'               => 'nullable|string|max:20|unique:tbl_atletas,numero_matricula_atleta',
            'escola_atleta'               => 'required|string|max:255',
            'email_atleta'                => 'nullable|email|max:255|unique:tbl_atletas,email_atleta',
            'password'                    => 'nullable|string|min:8|confirmed',
            'id_categoria'                => 'nullable|integer|exists:tbl_categoria,id_categoria',
            'foto_atleta'                 => 'nullable|image|max:2048',
            'nome_responsavel'            => 'required|string|max:255',
            'cpf_responsavel'             => 'required|string|max:14',
            'rg_responsavel'              => 'nullable|string|max:20',
            'telefone_responsavel'        => 'nullable|string|max:20',
            'whatsapp_responsavel'        => 'required|string|max:20',
            'grau_parentesco_responsavel' => 'required|string|max:50',
            'cep_endereco'                => 'required|string|max:9',
            'rua_endereco'                => 'required|string|max:255',
            'numero_endereco'             => 'required|string|max:10',
            'bairro_endereco'             => 'required|string|max:100',
            'complemento_endereco'        => 'nullable|string|max:100',
            'cidade_endereco'             => 'required|string|max:100',
            'estado_endereco'             => 'required|string|max:2',
            'cep_resp_endereco'           => 'required|string|max:9',
            'rua_resp_endereco'           => 'required|string|max:255',
            'numero_resp_endereco'        => 'required|string|max:10',
            'bairro_resp_endereco'        => 'required|string|max:100',
            'cidade_resp_endereco'        => 'required|string|max:100',
            'estado_resp_endereco'        => 'required|string|max:2',
        ]);

        DB::transaction(function () use ($request) {

            // 1. Endereço do atleta
            $enderecoAtleta = Endereco::create([
                'cep_endereco'         => $request->cep_endereco,
                'rua_endereco'         => $request->rua_endereco,
                'numero_endereco'      => $request->numero_endereco,
                'bairro_endereco'      => $request->bairro_endereco,
                'complemento_endereco' => $request->complemento_endereco,
                'cidade_endereco'      => $request->cidade_endereco,
                'estado_endereco'      => strtoupper($request->estado_endereco),
            ]);

            // 2. Endereço do responsável
            $enderecoResp = Endereco::create([
                'cep_endereco'         => $request->cep_resp_endereco,
                'rua_endereco'         => $request->rua_resp_endereco,
                'numero_endereco'      => $request->numero_resp_endereco,
                'bairro_endereco'      => $request->bairro_resp_endereco,
                'complemento_endereco' => null,
                'cidade_endereco'      => $request->cidade_resp_endereco,
                'estado_endereco'      => strtoupper($request->estado_resp_endereco),
            ]);

            // 3. Responsável
            $responsavel = Responsavel::create([
                'nome_responsavel'     => $request->nome_responsavel,
                'cpf_responsavel'      => $request->cpf_responsavel,
                'rg_responsavel'       => $request->rg_responsavel ?? '',
                'telefone_responsavel' => $request->telefone_responsavel,
                'whatsapp_responsavel' => $request->whatsapp_responsavel,
                'aceite_responsavel'   => 'pendente',
                'id_endereco'          => $enderecoResp->id_endereco,
            ]);

            // 4. Atleta
            $fotoPath = null;
            if ($request->hasFile('foto_atleta')) {
                $fotoPath = $request->file('foto_atleta')->store('atletas', 'public');
            }

            $atleta = Atleta::create([
                'nome_atleta'            => $request->nome_atleta,
                'data_nasc_atleta'       => $request->data_nasc_atleta,
                'cpf_atleta'             => $request->cpf_atleta,
                'rg_atleta'              => $request->rg_atleta,
                'numero_matricula_atleta'          => $request->numero_matricula_atleta,
                'escola_atleta'          => $request->escola_atleta,
                'foto_atleta'            => $fotoPath,
                'email_atleta'           => $request->email_atleta,
                'password'               => $request->password ? \Illuminate\Support\Facades\Hash::make($request->password) : null,
                'status_atleta'          => 'Ativo',
                'id_endereco'            => $enderecoAtleta->id_endereco,
                'sexo_atleta'            => $request->sexo_atleta ?? 'M',
                'peso_atleta'            => $request->peso_atleta ?? 0,
                'altura_atleta'          => $request->altura_atleta ?? 0,
                'serie_atleta'           => $request->serie_atleta ?? '',
                'periodo_escolar_atleta' => $request->periodo_escolar_atleta ?? '',
                'descricao_atleta'       => $request->descricao_atleta ?? '',
                'posicao_atleta'         => $request->posicao_atleta,
                'telefone_atleta'        => $request->telefone_atleta,
                'sala_atleta'            => $request->sala_atleta,
            ]);

            // 5. Pivot atleta <-> responsável
            $atleta->responsaveis()->attach($responsavel->id_responsavel, [
                'grau_parentesco_responsavel' => $request->grau_parentesco_responsavel,
            ]);

            // 6. Categoria (se selecionada)
            if ($request->filled('id_categoria')) {
                $atleta->categorias()->attach($request->id_categoria, [
                    'data_inicio_categoria_atleta' => now(),
                    'status_categoria_atleta'      => 'Ativo',
                ]);
            }
        });

        return redirect()->route('admin.atletas.index')
            ->with('sucesso', 'Atleta cadastrado com sucesso.');
    }

    public function edit($id)
    {
        $atleta = Atleta::with(['endereco', 'responsaveis', 'categorias', 'times'])
            ->findOrFail($id);

        $categorias = Categoria::orderBy('nome_categoria')->get();

        return view('admin.atletas.edit', compact('atleta', 'categorias'));
    }

    public function update(Request $request, $id)
    {
        $atleta = Atleta::with(['categorias'])->findOrFail($id);

        $request->validate([
            'nome_atleta'            => 'required|string|max:255',
            'data_nasc_atleta'       => 'required|date',
            'cpf_atleta'             => 'required|string|max:14',
            'rg_atleta'              => 'required|string|max:20',
            'escola_atleta'          => 'required|string|max:255',
            'numero_matricula_atleta'          => 'nullable|string|max:20',
            'posicao_atleta'         => 'nullable|string|max:50',
            'sexo_atleta'            => 'nullable|in:M,F',
            'peso_atleta'            => 'nullable|numeric|min:0',
            'altura_atleta'          => 'nullable|numeric|min:0',
            'serie_atleta'           => 'nullable|string|max:50',
            'periodo_escolar_atleta' => 'nullable|string|max:20',
            'descricao_atleta'       => 'nullable|string|max:500',
            'telefone_atleta'        => 'nullable|string|max:20',
            'sala_atleta'            => 'nullable|string|max:20',
            'email_atleta'           => 'nullable|email|max:255',
            'cep_endereco'           => 'nullable|string|max:9',
            'rua_endereco'           => 'nullable|string|max:255',
            'numero_endereco'        => 'nullable|string|max:10',
            'bairro_endereco'        => 'nullable|string|max:100',
            'complemento_endereco'   => 'nullable|string|max:100',
            'cidade_endereco'        => 'nullable|string|max:100',
            'estado_endereco'        => 'nullable|string|max:2',
            'id_categoria'           => 'nullable|integer|exists:tbl_categoria,id_categoria',
            'foto_atleta'            => 'nullable|image|max:2048',
        ]);

        $fotoPath = $atleta->foto_atleta;
        if ($request->hasFile('foto_atleta')) {
            $fotoPath = $request->file('foto_atleta')->store('atletas', 'public');
        }

        $atleta->update([
            'nome_atleta'            => $request->nome_atleta,
            'data_nasc_atleta'       => $request->data_nasc_atleta,
            'cpf_atleta'             => $request->cpf_atleta,
            'rg_atleta'              => $request->rg_atleta,
            'numero_matricula_atleta'          => $request->numero_matricula_atleta,
            'posicao_atleta'         => $request->posicao_atleta,
            'telefone_atleta'        => $request->telefone_atleta,
            'sala_atleta'            => $request->sala_atleta,
            'email_atleta'           => $request->email_atleta,
            'escola_atleta'          => $request->escola_atleta,
            'foto_atleta'            => $fotoPath,
            'sexo_atleta'            => $request->sexo_atleta,
            'peso_atleta'            => $request->peso_atleta,
            'altura_atleta'          => $request->altura_atleta,
            'serie_atleta'           => $request->serie_atleta ?? '',
            'periodo_escolar_atleta' => $request->periodo_escolar_atleta ?? '',
            'descricao_atleta'       => $request->descricao_atleta ?? '',
        ]);

        if ($request->filled('id_categoria')) {
            $categoriaAtual = $atleta->categorias->first();
            if ($categoriaAtual && $categoriaAtual->id_categoria != $request->id_categoria) {
                $atleta->categorias()->sync([$request->id_categoria => [
                    'data_inicio_categoria_atleta' => now(),
                    'status_categoria_atleta'      => 'Ativo',
                ]]);
            } elseif (!$categoriaAtual) {
                $atleta->categorias()->attach($request->id_categoria, [
                    'data_inicio_categoria_atleta' => now(),
                    'status_categoria_atleta'      => 'Ativo',
                ]);
            }
        }

        $selectedTimeIds = array_filter((array) $request->input('id_time', []));
        if (!empty($selectedTimeIds)) {
            $atleta->load('times');
            $syncData = [];
            foreach ($selectedTimeIds as $timeId) {
                $existingTime = $atleta->times->find($timeId);
                $syncData[$timeId] = [
                    'camisa_atleta_time'     => $request->camisa_atleta_time ?? $existingTime?->pivot->camisa_atleta_time ?? '',
                    'posicao_atleta_time'    => $existingTime?->pivot->posicao_atleta_time ?? '',
                    'jogos_atleta_time'      => $existingTime?->pivot->jogos_atleta_time ?? 0,
                    'convocacao_atleta_time' => $existingTime?->pivot->convocacao_atleta_time ?? 0,
                    'gols_atleta_time'       => $existingTime?->pivot->gols_atleta_time ?? 0,
                    'defesas_atleta_time'    => $existingTime?->pivot->defesas_atleta_time ?? 0,
                ];
            }
            $atleta->times()->sync($syncData);
        } else {
            $atleta->times()->detach();
        }

        return redirect()->route('admin.atletas.index')
            ->with('sucesso', 'Atleta atualizado com sucesso.');
    }

    public function toggleStatus($id)
    {
        $atleta = Atleta::findOrFail($id);
        $novoStatus = strtoupper($atleta->status_atleta) === 'ATIVO' ? 'INATIVO' : 'ATIVO';
        $atleta->update(['status_atleta' => $novoStatus]);

        return back()->with('sucesso', "Atleta {$novoStatus} com sucesso.");
    }

    public function destroy($id)
    {
        $atleta = Atleta::findOrFail($id);

        DB::transaction(function () use ($atleta) {
            $atleta->responsaveis()->detach();
            $atleta->categorias()->detach();
            $atleta->times()->detach();
            $atleta->delete();
        });

        return redirect()->route('admin.atletas.index')
            ->with('sucesso', 'Atleta removido com sucesso.');
    }
}