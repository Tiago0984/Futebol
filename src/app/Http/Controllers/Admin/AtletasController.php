<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\Responsavel;
use App\Models\Endereco;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AtletasController extends Controller
{
    public function index()
    {
        $atletas = Atleta::with(['endereco', 'responsaveis'])->orderBy('nome_atleta')->get();

        return view('admin.atletas.index', compact('atletas'));
    }

    public function create()
    {
        $categorias = Categoria::orderBy('nome_categoria')->get();

        return view('admin.atletas.create', compact('categorias'));
    }

    public function store(Request $request)
    {
        $request->validate([
            // Atleta
            'nome_atleta'          => 'required|string|max:255',
            'data_nasc_atleta'     => 'required|date',
            'rg_atleta'            => 'required|string|max:20',
            'cpf_atleta'           => 'required|string|max:14|unique:tbl_atletas,cpf_atleta',
            'numero_atleta'        => 'required|string|max:20|unique:tbl_atletas,numero_atleta',
            'peso_atleta'          => 'required|numeric|min:0',
            'altura_atleta'        => 'required|numeric|min:0',
            'sexo_atleta'          => 'required|in:M,F',
            'escola_atleta'        => 'required|string|max:255',
            'serie_atleta'         => 'required|string|max:50',
            'sala_atleta'          => 'nullable|integer',
            'periodo_escolar_atleta' => 'nullable|string|max:50',
            'status_atleta'        => 'required|string|max:50',
            'foto_atleta'          => 'nullable|image|max:2048',
            // Responsável
            'nome_responsavel'     => 'required|string|max:255',
            'cpf_responsavel'      => 'required|string|max:14',
            'rg_responsavel'       => 'required|string|max:20',
            'telefone_responsavel' => 'nullable|string|max:20',
            'whatsapp_responsavel' => 'required|string|max:20',
            'grau_parentesco'      => 'required|string|max:50',
            // Endereço (compartilhado)
            'cep_endereco'         => 'required|string|max:9',
            'rua_endereco'         => 'required|string|max:255',
            'numero_endereco'      => 'required|string|max:10',
            'bairro_endereco'      => 'required|string|max:100',
            'complemento_endereco' => 'nullable|string|max:100',
            'cidade_endereco'      => 'required|string|max:100',
            'estado_endereco'      => 'required|string|max:2',
        ]);

        DB::transaction(function () use ($request) {
            // 1. Endereço do atleta
            $endereco = Endereco::create($request->only([
                'cep_endereco', 'rua_endereco', 'numero_endereco',
                'bairro_endereco', 'complemento_endereco', 'cidade_endereco', 'estado_endereco',
            ]));

            // 2. Endereço do responsável (mesmo endereço)
            $enderecoResp = Endereco::create($request->only([
                'cep_endereco', 'rua_endereco', 'numero_endereco',
                'bairro_endereco', 'complemento_endereco', 'cidade_endereco', 'estado_endereco',
            ]));

            // 3. Responsável
            $responsavel = Responsavel::create([
                'nome_responsavel'     => $request->nome_responsavel,
                'cpf_responsavel'      => $request->cpf_responsavel,
                'rg_responsavel'       => $request->rg_responsavel,
                'telefone_responsavel' => $request->telefone_responsavel,
                'whatsapp_responsavel' => $request->whatsapp_responsavel,
                'assinatura_responsavel' => '',
                'aceite_responsavel'   => 'pendente',
                'id_endereco'          => $enderecoResp->id_endereco,
            ]);

            // 4. Atleta
            $dadosAtleta = $request->only([
                'nome_atleta', 'data_nasc_atleta', 'rg_atleta', 'cpf_atleta', 'numero_atleta',
                'peso_atleta', 'altura_atleta', 'sexo_atleta', 'escola_atleta', 'serie_atleta',
                'sala_atleta', 'periodo_escolar_atleta', 'status_atleta', 'descricao_atleta',
            ]);
            $dadosAtleta['id_endereco'] = $endereco->id_endereco;

            if ($request->hasFile('foto_atleta')) {
                $dadosAtleta['foto_atleta'] = $request->file('foto_atleta')->store('atletas', 'public');
            }

            $atleta = Atleta::create($dadosAtleta);

            // 5. Pivot atleta ↔ responsável
            $atleta->responsaveis()->attach($responsavel->id_responsavel, [
                'grau_parentesco_responsavel' => $request->grau_parentesco,
            ]);
        });

        return redirect()->route('admin.atletas.index')->with('sucesso', 'Atleta cadastrado com sucesso.');
    }

    public function edit($id)
    {
        $atleta     = Atleta::with(['endereco', 'responsaveis.endereco'])->findOrFail($id);
        $categorias = Categoria::orderBy('nome_categoria')->get();

        return view('admin.atletas.edit', compact('atleta', 'categorias'));
    }

    public function update(Request $request, $id)
    {
        $atleta = Atleta::with(['endereco', 'responsaveis'])->findOrFail($id);

        $request->validate([
            'nome_atleta'          => 'required|string|max:255',
            'data_nasc_atleta'     => 'required|date',
            'rg_atleta'            => 'required|string|max:20',
            'cpf_atleta'           => "required|string|max:14|unique:tbl_atletas,cpf_atleta,{$atleta->id_atleta},id_atleta",
            'numero_atleta'        => "required|string|max:20|unique:tbl_atletas,numero_atleta,{$atleta->id_atleta},id_atleta",
            'peso_atleta'          => 'required|numeric|min:0',
            'altura_atleta'        => 'required|numeric|min:0',
            'sexo_atleta'          => 'required|in:M,F',
            'escola_atleta'        => 'required|string|max:255',
            'serie_atleta'         => 'required|string|max:50',
            'sala_atleta'          => 'nullable|integer',
            'periodo_escolar_atleta' => 'nullable|string|max:50',
            'status_atleta'        => 'required|string|max:50',
            'foto_atleta'          => 'nullable|image|max:2048',
            'cep_endereco'         => 'required|string|max:9',
            'rua_endereco'         => 'required|string|max:255',
            'numero_endereco'      => 'required|string|max:10',
            'bairro_endereco'      => 'required|string|max:100',
            'complemento_endereco' => 'nullable|string|max:100',
            'cidade_endereco'      => 'required|string|max:100',
            'estado_endereco'      => 'required|string|max:2',
        ]);

        DB::transaction(function () use ($request, $atleta) {
            // Atualiza endereço do atleta
            $atleta->endereco->update($request->only([
                'cep_endereco', 'rua_endereco', 'numero_endereco',
                'bairro_endereco', 'complemento_endereco', 'cidade_endereco', 'estado_endereco',
            ]));

            $dadosAtleta = $request->only([
                'nome_atleta', 'data_nasc_atleta', 'rg_atleta', 'cpf_atleta', 'numero_atleta',
                'peso_atleta', 'altura_atleta', 'sexo_atleta', 'escola_atleta', 'serie_atleta',
                'sala_atleta', 'periodo_escolar_atleta', 'status_atleta', 'descricao_atleta',
            ]);

            if ($request->hasFile('foto_atleta')) {
                $dadosAtleta['foto_atleta'] = $request->file('foto_atleta')->store('atletas', 'public');
            }

            $atleta->update($dadosAtleta);
        });

        return redirect()->route('admin.atletas.index')->with('sucesso', 'Atleta atualizado com sucesso.');
    }

    public function destroy($id)
    {
        $atleta = Atleta::findOrFail($id);
        $atleta->responsaveis()->detach();
        $atleta->times()->detach();
        $atleta->categorias()->detach();
        $atleta->delete();

        return redirect()->route('admin.atletas.index')->with('sucesso', 'Atleta removido com sucesso.');
    }
}
