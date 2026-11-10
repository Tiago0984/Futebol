<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InicioDoPainel;

/**
 * Configurações do painel (10/11/2026): aparência (modo escuro, no navegador), atalho para o perfil e a situação do
 * sistema, só leitura, para conferir o servidor sem entrar no SSH: versão do código, ambiente, e-mail, fila, link das
 * fotos, contatos do site e push. Nenhuma senha ou chave aparece (só se está configurada) e nada é gravado.
 */
class ConfiguracoesController extends Controller
{
    public function index(InicioDoPainel $inicio)
    {
        return view('admin.configuracoes.index', ['sistema' => $this->sistema($inicio)]);
    }

    private function sistema(InicioDoPainel $inicio): array
    {
        $contagens = $inicio->contagens();
        $mailer = (string) config('mail.default');

        return [
            'versao'     => $this->versaoDoCodigo(),
            'ambiente'   => app()->environment(),
            'debug'      => (bool) config('app.debug'),
            'php'        => PHP_VERSION,
            'laravel'    => app()->version(),
            'fuso'       => config('app.timezone'),
            'agora'      => now(),
            'email'      => [
                'mailer'    => $mailer,
                'remetente' => config('mail.from.address'),
                // "log" e "array" não entregam: o e-mail vai para o arquivo de log (normal só no computador local)
                'entrega'   => ! in_array($mailer, ['log', 'array'], true),
            ],
            'fila'       => [
                'conexao'      => config('queue.default'),
                'parada_desde' => $contagens['fila_parada_desde'],
                'falhas'       => $contagens['fila_falhas'],
            ],
            'storage'    => file_exists(public_path('storage')),
            'contato'    => [
                'E-mail'    => config('contato.email'),
                'Telefone'  => config('contato.telefone'),
                'WhatsApp'  => config('contato.whatsapp'),
            ],
            'push'       => filled(config('services.expo.access_token')),
        ];
    }

    /**
     * Branch e commit do código em uso, lidos da pasta .git do repositório (a pasta acima de src/, no computador e no
     * servidor), sem rodar o git. Sem a pasta ou sem leitura: null.
     *
     * @return array{branch: ?string, commit: ?string}|null
     */
    private function versaoDoCodigo(): ?array
    {
        $git = dirname(base_path()) . DIRECTORY_SEPARATOR . '.git';
        $head = @file_get_contents($git . '/HEAD');
        if ($head === false) {
            return null;
        }

        $head = trim($head);
        if (! str_starts_with($head, 'ref: ')) {
            return ['branch' => null, 'commit' => substr($head, 0, 7)];
        }

        $ref = substr($head, 5);
        $commit = @file_get_contents($git . '/' . $ref);
        if ($commit === false) {
            // Ref compactada (depois de um "git gc"): procura no packed-refs
            $empacotadas = (string) @file_get_contents($git . '/packed-refs');
            $commit = preg_match('/^([0-9a-f]{40}) ' . preg_quote($ref, '/') . '$/m', $empacotadas, $m) ? $m[1] : null;
        }

        return ['branch' => basename($ref), 'commit' => $commit ? substr(trim($commit), 0, 7) : null];
    }
}
