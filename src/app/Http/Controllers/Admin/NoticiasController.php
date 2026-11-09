<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Noticia;
use App\Services\TratadorDeImagem;
use Illuminate\Http\Request;

class NoticiasController extends Controller
{
    // Pasta das capas no disco "imagens" (public/futebol/images)
    public const PASTA = 'news';

    // 1. Carrega a listagem e os modais estruturados nela
    public function index()
    {
        $noticias = Noticia::orderBy('data_publicacao_noticia', 'desc')->get();
        return view('admin.noticias.index', compact('noticias'));
    }

    // 2. Salva os dados enviados pelo modal de criação
    public function store(Request $request)
    {
        $request->validate($this->regras(), $this->mensagens());

        $dados = $this->dados($request);
        $aviso = null;

        if ($request->hasFile('foto_noticia')) {
            $capa = $this->tratarCapa($request, $request->file('foto_noticia')->getRealPath());
            $dados['foto_noticia'] = $capa['arquivo'];
            $aviso = $capa['aviso'];
        }

        Noticia::create($dados);

        return redirect()->route('admin.noticias.index')->with('sucesso', 'Notícia criada com sucesso.')
            ->with('avisos_imagem', array_filter([$aviso]));
    }

    // 3. Processa a atualização dos dados vindos do modal de edição
    public function update(Request $request, $id)
    {
        $noticia = Noticia::findOrFail($id);

        $request->validate($this->regras(), $this->mensagens());

        $dados = $this->dados($request);
        $antiga = $noticia->foto_noticia;
        $aviso = null;

        // Arquivo novo ou, sem ele, o recorte da capa atual ("Editar imagem"); nenhum dos dois: a capa fica
        if ($origem = TratadorDeImagem::origemDoEnvio($request, 'foto_noticia', self::PASTA, $antiga)) {
            $capa = $this->tratarCapa($request, $origem);
            $dados['foto_noticia'] = $capa['arquivo'];
            $aviso = $capa['aviso'];
        }

        $noticia->update($dados);

        if ($antiga !== $noticia->foto_noticia) {
            TratadorDeImagem::apagarSeNinguemUsa(self::PASTA, $antiga, fn ($arquivo) => Noticia::where('foto_noticia', $arquivo)->exists());
        }

        return redirect()->route('admin.noticias.index')->with('sucesso', 'Notícia atualizada com sucesso.')
            ->with('avisos_imagem', array_filter([$aviso]));
    }

    // 4. Alterna o Status entre Ativo e Inativo
    public function destroy($id)
    {
        $noticia = Noticia::findOrFail($id);

        if ($noticia->status_noticia === 'ATIVO') {
            $noticia->status_noticia = 'INATIVO';
            $mensagem = 'Notícia inativada com sucesso!';
        } else {
            $noticia->status_noticia = 'ATIVO';
            $mensagem = 'Notícia reativada com sucesso!';
        }

        $noticia->save();

        return redirect()->route('admin.noticias.index')->with('sucesso', $mensagem);
    }

    private function regras(): array
    {
        return [
            'titulo_noticia'          => 'required|string|max:255',
            'conteudo_noticia'        => 'required|string',
            'categoria_noticia'       => 'required|string|max:100',
            'autor_noticia'           => 'required|string|max:100',
            'data_publicacao_noticia' => 'required|date',
            // Qualquer foto até 10 MB: o servidor recorta em 16:9 e reduz (antes, 2 MB e gravada como veio)
            'foto_noticia'            => 'nullable|image|max:' . TratadorDeImagem::ENVIO_MAX_KB,
            'recorte_foto_noticia'    => 'nullable|string|max:60',
        ];
    }

    private function mensagens(): array
    {
        return ['foto_noticia.image' => 'Envie uma imagem (JPG, PNG, WebP ou GIF).', ...TratadorDeImagem::mensagensDoEnvio(['foto_noticia'])];
    }

    private function dados(Request $request): array
    {
        return $request->only([
            'titulo_noticia',
            'conteudo_noticia',
            'categoria_noticia',
            'autor_noticia',
            'data_publicacao_noticia',
        ]);
    }

    // Capa da notícia: 1920×1080 (16:9), pelo recorte do formulário ou pelo centro (TratadorDeImagem), nome gerado
    private function tratarCapa(Request $request, string $origem): array
    {
        [$largura, $altura] = TratadorDeImagem::NOTICIA;

        return TratadorDeImagem::capa($origem, self::PASTA, 'capa-noticia', $largura, $altura, $request->input('recorte_foto_noticia'));
    }
}
