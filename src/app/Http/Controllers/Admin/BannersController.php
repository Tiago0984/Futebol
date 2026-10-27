<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\TratadorDeImagem;
use Illuminate\Http\Request;

class BannersController extends Controller
{
    // Pasta das fotos no disco "imagens" (public/futebol/images)
    public const PASTA = 'banner';

    public function index()
    {
        $banners = Banner::orderBy('ordem_banner')->get();

        return view('admin.banners.index', compact('banners'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'titulo_banner'    => 'required|string|max:255',
            'subtitulo_banner' => 'nullable|string|max:255',
            'ordem_banner'     => 'required|integer|min:1',
            'status_banner'    => 'required|in:ativo,inativo',
            'foto_banner'      => 'required|image|max:' . TratadorDeImagem::ENVIO_MAX_KB,
            'recorte_foto_banner' => 'nullable|string|max:60',
        ], $this->mensagens());

        $dados = $request->only([
            'titulo_banner',
            'subtitulo_banner',
            'ordem_banner',
            'status_banner',
        ]);

        $foto = $this->tratarFoto($request);
        $dados['foto_banner'] = $foto['arquivo'];

        Banner::create($dados);

        return redirect()->route('admin.banners.index')->with('sucesso', 'Banner criado com sucesso.')
            ->with('avisos_imagem', array_filter([$foto['aviso']]));
    }

    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);

        $request->validate([
            'titulo_banner'    => 'required|string|max:255',
            'subtitulo_banner' => 'nullable|string|max:255',
            'ordem_banner'     => 'required|integer|min:1',
            'status_banner'    => 'required|in:ativo,inativo',
            'foto_banner'      => 'nullable|image|max:' . TratadorDeImagem::ENVIO_MAX_KB,
            'recorte_foto_banner' => 'nullable|string|max:60',
        ], $this->mensagens());

        $dados = $request->only([
            'titulo_banner',
            'subtitulo_banner',
            'ordem_banner',
            'status_banner',
        ]);

        $antiga = $banner->foto_banner;
        $aviso = null;
        if ($request->hasFile('foto_banner')) {
            $foto = $this->tratarFoto($request);
            $dados['foto_banner'] = $foto['arquivo'];
            $aviso = $foto['aviso'];
        }

        $banner->update($dados);

        if ($antiga !== $banner->foto_banner) {
            TratadorDeImagem::apagarSeNinguemUsa(self::PASTA, $antiga, fn ($arquivo) => Banner::where('foto_banner', $arquivo)->exists());
        }

        return redirect()->route('admin.banners.index')->with('sucesso', 'Banner atualizado com sucesso.')
            ->with('avisos_imagem', array_filter([$aviso]));
    }

    public function toggleStatus($id)
    {
        $banner = Banner::findOrFail($id);
        $banner->status_banner = strtolower($banner->status_banner) === 'ativo' ? 'inativo' : 'ativo';
        $banner->save();

        $msg = $banner->status_banner === 'inativo' ? 'Banner inativado.' : 'Banner reativado.';
        return back()->with('sucesso', $msg);
    }

    public function destroy($id)
    {
        Banner::findOrFail($id)->delete();

        return redirect()->route('admin.banners.index')->with('sucesso', 'Banner excluído com sucesso.');
    }

    // Foto do carrossel da home: 1920×1080 (16:9), pelo recorte do formulário ou pelo centro (TratadorDeImagem)
    private function tratarFoto(Request $request): array
    {
        [$largura, $altura] = TratadorDeImagem::BANNER_SITE;

        return TratadorDeImagem::capa($request->file('foto_banner')->getRealPath(), self::PASTA, 'banner-site',
            $largura, $altura, $request->input('recorte_foto_banner'));
    }

    private function mensagens(): array
    {
        return ['foto_banner.image' => 'Envie uma imagem (JPG, PNG, WebP ou GIF).', ...TratadorDeImagem::mensagensDoEnvio(['foto_banner'])];
    }
}
