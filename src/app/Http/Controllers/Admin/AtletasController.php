<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AtletasController extends Controller
{
    public function index()
    {
        $atletas = DB::table('tbl_atletas as a')
            ->leftJoin('tbl_categoria_atleta as ca', 'ca.id_atleta', '=', 'a.id_atleta')
            ->leftJoin('tbl_atleta_responsavel as ar', 'ar.id_atleta', '=', 'a.id_atleta')
            ->leftJoin('tbl_responsavel as r', 'r.id_responsavel', '=', 'ar.id_responsavel')
            ->leftJoin('tbl_categoria as c', 'c.id_categoria', '=', 'ca.id_categoria')
            ->leftJoin('tbl_atleta_time as at', 'at.id_atleta', '=', 'a.id_atleta')
            ->select(
                'a.id_atleta',
                'a.nome_atleta',
                'a.foto_atleta',
                'a.status_atleta',
                'a.numero_atleta',
                DB::raw('MAX(c.nome_categoria) as nome_categoria'),
                DB::raw('MAX(at.posicao_atleta_time) as posicao_atleta_time'),
                DB::raw('MAX(r.nome_responsavel) as nome_responsavel'),                
            )
            ->groupBy('a.id_atleta', 'a.nome_atleta', 'a.foto_atleta', 'a.status_atleta', 'a.numero_atleta')
            ->orderBy('a.nome_atleta')
            ->get();

        $categorias = DB::table('tbl_categoria')->orderBy('nome_categoria')->get();

        return view('admin.atletas.index', compact('atletas', 'categorias'));
    }

    public function create()
    {
        $categorias = DB::table('tbl_categoria')->orderBy('nome_categoria')->get();
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

            $idEndereco = DB::table('tbl_endereco')->insertGetId([
                'rua_endereco'         => $request->rua_endereco,
                'numero_endereco'      => $request->numero_endereco,
                'bairro_endereco'      => $request->bairro_endereco,
                'complemento_endereco' => $request->complemento_endereco,
                'cep_endereco'         => $request->cep_endereco,
                'cidade_endereco'      => $request->cidade_endereco,
                'estado_endereco'      => strtoupper($request->estado_endereco),
            ]);

            $idResponsavel = DB::table('tbl_responsavel')->insertGetId([
                'nome_responsavel'       => $request->nome_responsavel,
                'cpf_responsavel'        => $request->cpf_responsavel,
                'rg_responsavel'         => '',
                'telefone_responsavel'   => $request->whatsapp_responsavel,
                'whatsapp_responsavel'   => $request->whatsapp_responsavel,
                'assinatura_responsavel' => '',
                'aceite_responsavel'     => 'pendente',
                'id_endereco'            => $idEndereco,
            ]);

            $fotoPath = null;
            if ($request->hasFile('foto_atleta')) {
                $fotoPath = $request->file('foto_atleta')->store('atletas', 'public');
            }

            $idAtleta = DB::table('tbl_atletas')->insertGetId([
                'nome_atleta'            => $request->nome_atleta,
                'data_nasc_atleta'       => $request->data_nasc_atleta,
                'cpf_atleta'             => $request->cpf_atleta,
                'rg_atleta'              => $request->rg_atleta,
                'numero_atleta'          => $request->numero_atleta,
                'escola_atleta'          => $request->escola_atleta,
                'foto_atleta'            => $fotoPath,
                'status_atleta'          => 'Ativo',
                'id_endereco'            => $idEndereco,
                'peso_atleta'            => 0,
                'altura_atleta'          => 0,
                'sexo_atleta'            => 'M',
                'serie_atleta'           => '',
                'descricao_atleta'       => '',
                'sala_atleta'            => 0,
                'periodo_escolar_atleta' => '',
            ]);

            DB::table('tbl_atleta_responsavel')->insert([
                'id_atleta'                   => $idAtleta,
                'id_responsavel'              => $idResponsavel,
                'grau_parentesco_responsavel' => $request->grau_parentesco_responsavel,
            ]);

            if ($request->filled('id_categoria')) {
                DB::table('tbl_categoria_atleta')->insert([
                    'id_atleta'                    => $idAtleta,
                    'id_categoria'                 => $request->id_categoria,
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
        $atleta = DB::table('tbl_atletas as a')
            ->leftJoin('tbl_endereco as e', 'e.id_endereco', '=', 'a.id_endereco')
            ->leftJoin('tbl_atleta_responsavel as ar', 'ar.id_atleta', '=', 'a.id_atleta')
            ->leftJoin('tbl_responsavel as r', 'r.id_responsavel', '=', 'ar.id_responsavel')
            ->leftJoin('tbl_categoria_atleta as ca', 'ca.id_atleta', '=', 'a.id_atleta')
            ->leftJoin('tbl_atleta_time as at', 'at.id_atleta', '=', 'a.id_atleta')
            ->select(
                'a.*',
                'e.*',
                'r.id_responsavel',
                'r.nome_responsavel',
                'r.cpf_responsavel',
                'r.whatsapp_responsavel',
                'ar.grau_parentesco_responsavel',
                'ca.id_categoria as id_categoria_atual',
                DB::raw('MAX(at.posicao_atleta_time) as posicao_atleta_time')
            )
            ->where('a.id_atleta', $id)
            ->groupBy(
                'a.id_atleta',
                'e.id_endereco',
                'r.id_responsavel',
                'ar.grau_parentesco_responsavel',
                'ca.id_categoria'
            )
            ->first();

        abort_if(!$atleta, 404);

        $categorias = DB::table('tbl_categoria')->orderBy('nome_categoria')->get();

        return view('admin.atletas.edit', compact('atleta', 'categorias'));
    }

    public function update(Request $request, $id)
    {
        $atleta = DB::table('tbl_atletas')->where('id_atleta', $id)->first();
        abort_if(!$atleta, 404);

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

        DB::transaction(function () use ($request, $id, $atleta) {

            // 1. Atualiza tbl_atletas
            $fotoPath = $atleta->foto_atleta;
            if ($request->hasFile('foto_atleta')) {
                $fotoPath = $request->file('foto_atleta')->store('atletas', 'public');
            }

            DB::table('tbl_atletas')->where('id_atleta', $id)->update([
                'nome_atleta'      => $request->nome_atleta,
                'data_nasc_atleta' => $request->data_nasc_atleta,
                'cpf_atleta'       => $request->cpf_atleta,
                'rg_atleta'        => $request->rg_atleta,
                'numero_atleta'    => $request->numero_atleta,
                'escola_atleta'    => $request->escola_atleta,
                'status_atleta'    => $request->status_atleta,
                'foto_atleta'      => $fotoPath,
            ]);

            // 2. Atualiza tbl_endereco
            DB::table('tbl_endereco')->where('id_endereco', $atleta->id_endereco)->update([
                'rua_endereco'         => $request->rua_endereco,
                'numero_endereco'      => $request->numero_endereco,
                'bairro_endereco'      => $request->bairro_endereco,
                'complemento_endereco' => $request->complemento_endereco,
                'cep_endereco'         => $request->cep_endereco,
                'cidade_endereco'      => $request->cidade_endereco,
                'estado_endereco'      => strtoupper($request->estado_endereco),
            ]);

            // 3. Atualiza tbl_responsavel via pivot
            $pivot = DB::table('tbl_atleta_responsavel')->where('id_atleta', $id)->first();
            if ($pivot) {
                DB::table('tbl_responsavel')->where('id_responsavel', $pivot->id_responsavel)->update([
                    'nome_responsavel'     => $request->nome_responsavel,
                    'cpf_responsavel'      => $request->cpf_responsavel,
                    'whatsapp_responsavel' => $request->whatsapp_responsavel,
                    'telefone_responsavel' => $request->whatsapp_responsavel,
                ]);

                DB::table('tbl_atleta_responsavel')->where('id_atleta', $id)->update([
                    'grau_parentesco_responsavel' => $request->grau_parentesco_responsavel,
                ]);
            }

            // 4. Atualiza categoria
            if ($request->filled('id_categoria')) {
                $catExiste = DB::table('tbl_categoria_atleta')->where('id_atleta', $id)->exists();
                if ($catExiste) {
                    DB::table('tbl_categoria_atleta')->where('id_atleta', $id)->update([
                        'id_categoria' => $request->id_categoria,
                    ]);
                } else {
                    DB::table('tbl_categoria_atleta')->insert([
                        'id_atleta'                    => $id,
                        'id_categoria'                 => $request->id_categoria,
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
        $atleta = DB::table('tbl_atletas')->where('id_atleta', $id)->first();
        abort_if(!$atleta, 404);

        $novoStatus = strtolower($atleta->status_atleta) === 'ativo' ? 'Inativo' : 'Ativo';

        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['status_atleta' => $novoStatus]);

        return back()->with('sucesso', "Atleta {$novoStatus} com sucesso.");
    }

    public function destroy($id)
    {
        $atleta = DB::table('tbl_atletas')->where('id_atleta', $id)->first();
        abort_if(!$atleta, 404);

        DB::transaction(function () use ($id) {
            DB::table('tbl_atleta_responsavel')->where('id_atleta', $id)->delete();
            DB::table('tbl_atleta_time')->where('id_atleta', $id)->delete();
            DB::table('tbl_categoria_atleta')->where('id_atleta', $id)->delete();
            DB::table('tbl_atletas')->where('id_atleta', $id)->delete();
        });

        return redirect()->route('admin.atletas.index')
            ->with('sucesso', 'Atleta removido com sucesso.');
    }
}
