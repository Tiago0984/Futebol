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
            'times',
            'endereco',
        ])->orderBy('nome_atleta')->get();

        $categorias = Categoria::orderBy('nome_categoria')->get();
        $times      = Time::orderBy('nome_time')->get();

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
            'numero_matricula_atleta'     => 'nullable|string|max:20|unique:tbl_atletas,numero_matricula_atleta',
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
                'aceite_responsavel'     => 'N',
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
                'escola_atleta'          => $request->escola_atleta,
                'foto_atleta'            => $fotoPath,
                'status_atleta'          => 'ATIVO', // Padronizado para MAIÚSCULO
                'id_endereco'            => $endereco->id_endereco,
                'posicao_atleta'         => $request->posicao_atleta,
                'sexo_atleta'            => $request->sexo_atleta ?? 'M',
                'peso_atleta'            => $request->peso_atleta ?? 0,
                'altura_atleta'          => $request->altura_atleta ?? 0,
                'serie_atleta'           => $request->serie_atleta ?? '',
                'periodo_escolar_atleta' => $request->periodo_escolar_atleta ?? '',
                'descricao_atleta'       => $request->descricao_atleta ?? '',
                'sala_atleta'            => $request->sala_atleta,
            ]);

            // 4. Pivot atleta <-> responsável
            $atleta->responsaveis()->attach($responsavel->id_responsavel, [
                'grau_parentesco_responsavel' => $request->grau_parentesco_responsavel,
            ]);

            // 5. Categoria (se selecionada)
            if ($request->filled('id_categoria')) {
                $atleta->categorias()->attach($request->id_categoria, [
                    'data_inicio_categoria_atleta' => now(),
                    'status_categoria_atleta'      => 'ATIVO', // Padronizado para MAIÚSCULO
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
        $atleta = Atleta::with(['endereco', 'responsaveis', 'categorias', 'times'])->findOrFail($id);

        $request->validate([
            'nome_atleta'                 => 'required|string|max:255',
            'data_nasc_atleta'            => 'required|date',
            'cpf_atleta'                  => 'required|string|max:14',
            'rg_atleta'                   => 'required|string|max:20',
            'escola_atleta'               => 'required|string|max:255',
            'status_atleta'               => 'required|in:ATIVO,INATIVO',
            'camisa_atleta_time'          => 'nullable|string|max:10',
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
                'nome_atleta'             => $request->nome_atleta,
                'data_nasc_atleta'        => $request->data_nasc_atleta,
                'cpf_atleta'              => $request->cpf_atleta,
                'rg_atleta'               => $request->rg_atleta,
                'escola_atleta'           => $request->escola_atleta,
                'serie_atleta'            => $request->serie_atleta,
                'periodo_escolar_atleta'  => $request->periodo_escolar_atleta,
                'sexo_atleta'             => $request->sexo_atleta,
                'peso_atleta'             => $request->peso_atleta,
                'altura_atleta'           => $request->altura_atleta,
                'posicao_atleta'          => $request->posicao_atleta,
                'descricao_atleta'        => $request->descricao_atleta,
                'sala_atleta'             => $request->sala_atleta,
                'status_atleta'           => strtoupper($request->status_atleta),
                'foto_atleta'             => $fotoPath,
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

            // 3. Atualiza responsável (ou cria se ainda não existe)
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
            } else {
                $novoResponsavel = Responsavel::create([
                    'nome_responsavel'       => $request->nome_responsavel,
                    'cpf_responsavel'        => $request->cpf_responsavel,
                    'rg_responsavel'         => '',
                    'telefone_responsavel'   => $request->whatsapp_responsavel,
                    'whatsapp_responsavel'   => $request->whatsapp_responsavel,
                    'assinatura_responsavel' => '',
                    'aceite_responsavel'     => 'N',
                    'id_endereco'            => $atleta->id_endereco,
                ]);

                $atleta->responsaveis()->attach($novoResponsavel->id_responsavel, [
                    'grau_parentesco_responsavel' => $request->grau_parentesco_responsavel,
                ]);
            }

            // 4. Atualiza categoria (sync substitui qualquer categoria anterior)
            if ($request->filled('id_categoria')) {
                $atleta->categorias()->sync([$request->id_categoria => [
                    'data_inicio_categoria_atleta' => $atleta->categorias->first()?->pivot->data_inicio_categoria_atleta ?? now(),
                    'status_categoria_atleta'      => 'ATIVO',
                ]]);
            } else {
                $atleta->categorias()->detach();
            }

            // 5. Sincroniza times
            $novosTimeIds    = collect($request->input('times', []))->map(fn($v) => (int) $v);
            $timesExistentes = $atleta->times->unique('id_time')->pluck('id_time')->map(fn($v) => (int) $v);
            $camisaValue     = ($request->filled('camisa_atleta_time') && (int) $request->camisa_atleta_time > 0)
                                ? (int) $request->camisa_atleta_time
                                : 0;
            $posicaoValue    = $request->posicao_atleta ?? '';

            // Remove times desmarcados
            $remover = $timesExistentes->diff($novosTimeIds);
            if ($remover->isNotEmpty()) {
                $atleta->times()->detach($remover->toArray());
            }

            // Adiciona times novos
            $adicionar = $novosTimeIds->diff($timesExistentes);
            foreach ($adicionar as $timeId) {
                $atleta->times()->attach($timeId, [
                    'status_atleta_time'  => 'TITULAR',
                    'camisa_atleta_time'  => $camisaValue,
                    'posicao_atleta_time' => $posicaoValue,
                ]);
            }

            // Atualiza posição e camisa em TODOS os times selecionados via SQL direto
            if ($novosTimeIds->isNotEmpty()) {
                $pivotUpdate = ['posicao_atleta_time' => $posicaoValue];
                if ($camisaValue > 0) {
                    $pivotUpdate['camisa_atleta_time'] = $camisaValue;
                }
                DB::table('tbl_atleta_time')
                    ->where('id_atleta', $atleta->id_atleta)
                    ->whereIn('id_time', $novosTimeIds->toArray())
                    ->update($pivotUpdate);
            }
        });

        return redirect()->route('admin.atletas.index')
            ->with('sucesso', 'Atleta atualizado com sucesso.');
    }

    public function toggleStatus($id)
    {
        $atleta = Atleta::findOrFail($id);

        // Garante a comparação e atribuição estritamente em maiúsculo
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
