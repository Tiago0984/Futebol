<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atleta;
use App\Models\Notificacao;
use Illuminate\Http\Request;

/**
 * Foto que o atleta enviou pelo app (29/10/2026): espera a aprovação da secretaria fora de public/ (o atleta é menor e
 * a foto vai para o site). A tela de Atletas mostra a atual e a nova lado a lado; aprovar ou recusar avisa o atleta
 * (AVISO). As regras ficam no model (Atleta::aprovarFotoPendente, recusarFotoPendente).
 */
class FotosPendentesController extends Controller
{
    // A imagem pendente, só para o admin logado (não há URL pública para ela)
    public function mostrar($id)
    {
        $caminho = Atleta::findOrFail($id)->caminhoDaFotoPendente();
        abort_unless($caminho, 404);

        return response()->file($caminho, ['Cache-Control' => 'private, no-store']);
    }

    public function aprovar($id)
    {
        $atleta = Atleta::findOrFail($id);

        if (! $atleta->aprovarFotoPendente(auth('admin')->id())) {
            return back()->with('erro', "{$atleta->nome_atleta} não tem foto nova esperando aprovação.");
        }

        return back()->with('sucesso', "Foto de {$atleta->nome_atleta} aprovada. " . $this->textoDoAviso($atleta));
    }

    public function recusar(Request $request, $id)
    {
        $atleta = Atleta::findOrFail($id);

        // Erro na tela pelo "erro" da sessão: o $errors padrão da lista de Atletas reabre o modal de Novo Atleta
        $motivo = trim((string) $request->input('motivo'));
        if (mb_strlen($motivo) > Notificacao::MOTIVO_FOTO_MAX) {
            return back()->with('erro', 'O motivo da recusa pode ter até ' . Notificacao::MOTIVO_FOTO_MAX . ' caracteres.');
        }

        if (! $atleta->recusarFotoPendente($motivo, auth('admin')->id())) {
            return back()->with('erro', "{$atleta->nome_atleta} não tem foto nova esperando aprovação.");
        }

        return back()->with('sucesso', "Foto nova de {$atleta->nome_atleta} recusada; a atual continua. " . $this->textoDoAviso($atleta));
    }

    // Só ATIVO recebe aviso (Notificacao::gravarParaAtivos): o admin fica sabendo se o atleta foi avisado
    private function textoDoAviso(Atleta $atleta): string
    {
        return strtoupper((string) $atleta->status_atleta) === 'ATIVO'
            ? 'O atleta foi avisado no app.'
            : 'O atleta não está ativo e não recebeu o aviso.';
    }
}
