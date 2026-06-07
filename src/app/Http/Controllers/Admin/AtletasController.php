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
        $atletas = Atleta::with([
            'categorias',
            'responsaveis',
            'times',
        ])->orderBy('nome_atleta')->get();

        $categorias = Categoria::orderBy('nome_categoria')->get();

        return view('admin.atletas.index', compact('atletas', 'categorias'));
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
            'numero_atleta'               => 'nullable|string|max:20|unique:tbl_atletas,numero_atleta',
            'escola_atleta'               => 'required|string|max:255',
            'id_categoria'                => 'nullable|integer|exists:tbl_categoria,id_categoria',
            'foto_atleta'                 => 'nullable|image|max:2048',
            'nome_responsavel'            => 'required|string|max:255',
            'cpf_responsavel'             => 'required|string|max:14',
            'whatsapp_responsavel'        => 'required|string|max:20',
            'grau_parentesco_responsavel' => 'required|string|max:50',
            'cep_endereco'                => 'required|string|max:9',
            'rua_endereco'                => 'required|string|max:255',
            'numero_endereco'             => 'required|string|max:10',
            'bairro_endereco'             => 'required|string|max:100',
            'complemento_endereco'        => 'nullable|string|max:100',
            'cidade_endereco'             => 'required|string|max:100',
            'estado_endereco'             => 'required|string|max:2',
        ]);

        DB::transaction(function () use ($request) {

            // 1. Endereço do atleta
            $endereco = Endereco::create([
                'rua_endereco'         => $request->rua_endereco,
                'numero_endereco'      => $request->numero_endereco,
                'bairro_endereco'      => $request->bairro_endereco,
                'complemento_endereco' => $request->complemento_endereco,
                'cep_endereco'         => $request->cep_endereco,
                'cidade_endereco'      => $request->cidade_endereco,
                'estado_endereco'      => strtoupper($request->estado_endereco),
            ]);

            // 2. Responsável
            $responsavel = Responsavel::create([
                'nome_responsavel'       => $request->nome_responsavel,
                'cpf_responsavel'        => $request->cpf_responsavel,
                'rg_responsavel'         => '',
                'telefone_responsavel'   => $request->whatsapp_responsavel,
                'whatsapp_responsavel'   => $request->whatsapp_responsavel,
                'assinatura_responsavel' => '',
                'aceite_responsavel'     => 'pendente',
                'id_endereco'            => $endereco->id_endereco,
            ]);

            // 3. Atleta
            $fotoPath = null;
            if ($request->hasFile('foto_atleta')) {
                $fotoPath = $request->file('foto_atleta')->store('atletas', 'public');
            }

            $atleta = Atleta::create([
                'nome_atleta'            => $request->nome_atleta,
                'data_nasc_atleta'       => $request->data_nasc_atleta,
                'cpf_atleta'             => $request->cpf_atleta,
                'rg_atleta'              => $request->rg_atleta,
                'numero_atleta'          => $request->numero_atleta,
                'escola_atleta'          => $request->escola_atleta,
                'foto_atleta'            => $fotoPath,
                'status_atleta'          => 'Ativo',
                'id_endereco'            => $endereco->id_endereco,
                'sexo_atleta'            => $request->sexo_atleta ?? 'M',
                'peso_atleta'            => $request->peso_atleta ?? 0,
                'altura_atleta'          => $request->altura_atleta ?? 0,
                'serie_atleta'           => $request->serie_atleta ?? '',
                'periodo_escolar_atleta' => $request->periodo_escolar_atleta ?? '',
                'descricao_atleta'       => $request->descricao_atleta ?? '',
                'sala_atleta'            => 0,
            ]);

            // 4. Pivot atleta <-> responsável
            $atleta->responsaveis()->attach($responsavel->id_responsavel, [
                'grau_parentesco_responsavel' => $request->grau_parentesco_responsavel,
            ]);

            // 5. Categoria (se selecionada)
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
        $atleta = Atleta::with(['endereco', 'responsaveis'])->findOrFail($id);

        $request->validate([
            'nome_atleta'                 => 'required|string|max:255',
            'data_nasc_atleta'            => 'required|date',
            'cpf_atleta'                  => 'required|string|max:14',
            'rg_atleta'                   => 'required|string|max:20',
            'escola_atleta'               => 'required|string|max:255',
            'status_atleta'               => 'required|in:Ativo,Inativo',
            'numero_atleta'               => 'nullable|string|max:20',
            'nome_responsavel'            => 'required|string|max:255',
            'cpf_responsavel'             => 'required|string|max:14',
            'whatsapp_responsavel'        => 'required|string|max:20',
            'grau_parentesco_responsavel' => 'required|string|max:50',
            'cep_endereco'                => 'required|string|max:9',
            'rua_endereco'                => 'required|string|max:255',
            'numero_endereco'             => 'required|string|max:10',
            'bairro_endereco'             => 'required|string|max:100',
            'complemento_endereco'        => 'nullable|string|max:100',
            'cidade_endereco'             => 'required|string|max:100',
            'estado_endereco'             => 'required|string|max:2',
        ]);

        DB::transaction(function () use ($request, $atleta) {

            // 1. Atualiza atleta
            $fotoPath = $atleta->foto_atleta;
            if ($request->hasFile('foto_atleta')) {
                $fotoPath = $request->file('foto_atleta')->store('atletas', 'public');
            }

            $atleta->update([
                'nome_atleta'      => $request->nome_atleta,
                'data_nasc_atleta' => $request->data_nasc_atleta,
                'cpf_atleta'       => $request->cpf_atleta,
                'rg_atleta'        => $request->rg_atleta,
                'numero_atleta'    => $request->numero_atleta,
                'escola_atleta'    => $request->escola_atleta,
                'status_atleta'    => $request->status_atleta,
                'foto_atleta'      => $fotoPath,
            ]);

            // 2. Atualiza endereço
            if ($atleta->endereco) {
                $atleta->endereco->update([
                    'rua_endereco'         => $request->rua_endereco,
                    'numero_endereco'      => $request->numero_endereco,
                    'bairro_endereco'      => $request->bairro_endereco,
                    'complemento_endereco' => $request->complemento_endereco,
                    'cep_endereco'         => $request->cep_endereco,
                    'cidade_endereco'      => $request->cidade_endereco,
                    'estado_endereco'      => strtoupper($request->estado_endereco),
                ]);
            }

            // 3. Atualiza responsável
            $responsavel = $atleta->responsaveis->first();
            if ($responsavel) {
                $responsavel->update([
                    'nome_responsavel'     => $request->nome_responsavel,
                    'cpf_responsavel'      => $request->cpf_responsavel,
                    'whatsapp_responsavel' => $request->whatsapp_responsavel,
                    'telefone_responsavel' => $request->whatsapp_responsavel,
                ]);

                $atleta->responsaveis()->updateExistingPivot($responsavel->id_responsavel, [
                    'grau_parentesco_responsavel' => $request->grau_parentesco_responsavel,
                ]);
            }

            // 4. Atualiza categoria
            if ($request->filled('id_categoria')) {
                $categoriaAtual = $atleta->categorias->first();
                if ($categoriaAtual) {
                    $atleta->categorias()->updateExistingPivot($categoriaAtual->id_categoria, [
                        'id_categoria' => $request->id_categoria,
                    ]);
                    // Se mudou de categoria, sync
                    if ($categoriaAtual->id_categoria != $request->id_categoria) {
                        $atleta->categorias()->sync([$request->id_categoria => [
                            'data_inicio_categoria_atleta' => now(),
                            'status_categoria_atleta'      => 'Ativo',
                        ]]);
                    }
                } else {
                    $atleta->categorias()->attach($request->id_categoria, [
                        'data_inicio_categoria_atleta' => now(),
                        'status_categoria_atleta'      => 'Ativo',
                    ]);
                }
            }
        });

        return redirect()->route('admin.atletas.index')
            ->with('sucesso', 'Atleta atualizado com sucesso.');
    }

    public function toggleStatus($id)
    {
        $atleta = Atleta::findOrFail($id);
        $novoStatus = strtolower($atleta->status_atleta) === 'ativo' ? 'Inativo' : 'Ativo';
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