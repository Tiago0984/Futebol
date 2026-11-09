<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Galeria;
use App\Services\TratadorDeImagem;
use Illuminate\Http\Request;

class GaleriaController extends Controller
{
    // Pasta das fotos no disco "imagens" (public/futebol/images)
    public const PASTA = 'galeria';

    // 1. Carrega a listagem e os modais estruturados nela
    public function index()
    {
        $fotos = Galeria::orderBy('ordem_galeria')->get();

        $categorias = Galeria::select('categoria_galeria')
            ->distinct() // garante que só traga categorias únicas
            ->orderBy('categoria_galeria')
            ->pluck('categoria_galeria'); // pluck para obter apenas os valores da coluna

        $proximaOrdem = ($fotos->max('ordem_galeria') ?? 0) + 1;

        return view('admin.galeria.index', compact('fotos', 'categorias', 'proximaOrdem'));
    }

    // 2. Salva os dados enviados pelo modal de criação
    public function store(Request $request)
    {
        $request->validate($this->regras(obrigatoria: true), $this->mensagens());

        $dados = $this->dados($request);

        $foto = $this->tratarFoto($request, $request->file('foto_galeria')->getRealPath());
        $dados['foto_galeria'] = $foto['arquivo'];

        Galeria::create($dados);

        return redirect()->route('admin.galeria.index')->with('sucesso', 'Foto adicionada com sucesso.')
            ->with('avisos_imagem', array_filter([$foto['aviso']]));
    }

    // 3. Processa a atualização dos dados vindos do modal de edição
    public function update(Request $request, $id)
    {
        $foto = Galeria::findOrFail($id);

        $request->validate($this->regras(obrigatoria: false), $this->mensagens());

        $dados = $this->dados($request);
        $antiga = $foto->foto_galeria;
        $aviso = null;

        // Arquivo novo ou, sem ele, o recorte da foto atual ("Editar imagem"); nenhum dos dois: a foto fica
        if ($origem = TratadorDeImagem::origemDoEnvio($request, 'foto_galeria', self::PASTA, $antiga)) {
            $nova = $this->tratarFoto($request, $origem);
            $dados['foto_galeria'] = $nova['arquivo'];
            $aviso = $nova['aviso'];
        }

        $foto->update($dados);

        if ($antiga !== $foto->foto_galeria) {
            TratadorDeImagem::apagarSeNinguemUsa(self::PASTA, $antiga, fn ($arquivo) => Galeria::where('foto_galeria', $arquivo)->exists());
        }

        return redirect()->route('admin.galeria.index')->with('sucesso', 'Foto atualizada com sucesso.')
            ->with('avisos_imagem', array_filter([$aviso]));
    }

    // 4. Alterna o status entre ativo e inativo
    public function destroy($id)
    {
        $foto = Galeria::findOrFail($id);

        if ($foto->status_galeria === 'ATIVO') {
            $foto->status_galeria = 'INATIVO';
            $mensagem = 'Foto inativada com sucesso!';
        } else {
            $foto->status_galeria = 'ATIVO';
            $mensagem = 'Foto reativada com sucesso!';
        }

        $foto->save();

        return redirect()->route('admin.galeria.index')->with('sucesso', $mensagem);
    }

    private function regras(bool $obrigatoria): array
    {
        return [
            'titulo_galeria'         => 'required|string|max:255',
            'categoria_galeria'      => 'required|string',
            'nova_categoria_galeria' => 'required_if:categoria_galeria,__nova__|nullable|string|max:60',
            'ordem_galeria'          => 'required|integer|min:1',
            'status_galeria'         => 'required|in:ATIVO,INATIVO',
            // Qualquer foto até 10 MB: o servidor reduz (antes, 4 MB gravada como veio, com o nome original)
            'foto_galeria'           => ($obrigatoria ? 'required' : 'nullable') . '|image|max:' . TratadorDeImagem::ENVIO_MAX_KB,
            'recorte_foto_galeria'   => 'nullable|string|max:60',
        ];
    }

    private function mensagens(): array
    {
        return ['foto_galeria.image' => 'Envie uma imagem (JPG, PNG, WebP ou GIF).', ...TratadorDeImagem::mensagensDoEnvio(['foto_galeria'])];
    }

    // Se for nova categoria, usa o campo de nova categoria (em maiúsculas); senão, a selecionada
    private function dados(Request $request): array
    {
        $dados = $request->only(['titulo_galeria', 'ordem_galeria', 'status_galeria']);

        $dados['categoria_galeria'] = $request->input('categoria_galeria') === '__nova__'
            ? strtoupper(trim($request->input('nova_categoria_galeria')))
            : $request->input('categoria_galeria');

        return $dados;
    }

    // Foto da galeria: recorte livre do formulário (ou a foto inteira), até 1920 px no lado maior (TratadorDeImagem)
    private function tratarFoto(Request $request, string $origem): array
    {
        return TratadorDeImagem::fotoLivre($origem, self::PASTA, 'foto-galeria', $request->input('recorte_foto_galeria'));
    }
}
