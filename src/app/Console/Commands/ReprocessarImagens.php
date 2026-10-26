<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\BannersController;
use App\Http\Controllers\Admin\CampeonatosController;
use App\Services\TratadorDeImagem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Trata as imagens já cadastradas (26/10/2026), como o painel faz no envio (TratadorDeImagem): logo do campeonato
 * 512×512 PNG inteira, banner do campeonato 1920×960 e Banners da home 1920×1080, recortados pelo centro.
 * - Antes de mexer, copia os originais para storage/app/imagens-originais/AAAAMMDD-HHMMSS/ (fora do public).
 * - Grava com nome novo e atualiza o banco; o original fica onde está (pode estar no Git ou em outro registro).
 * - Arquivo usado por vários registros é tratado uma vez só. Já tratado (nome gerado) ou que não existe: pulado.
 * - Capa que perderia mais da metade no recorte (ex.: foto em pé): pulada, para recortar à mão pelo formulário,
 *   a não ser com --forcar.
 * Grava no banco: no servidor, só com backup antes (CLAUDE.md, regra 5).
 */
class ReprocessarImagens extends Command
{
    protected $signature = 'imagens:reprocessar
        {--campeonatos : Só a logo e o banner dos campeonatos}
        {--banners : Só os Banners da home}
        {--simular : Só lista o que faria, sem gravar nada}
        {--forcar : Trata também a capa que perderia mais da metade no recorte}';

    protected $description = 'Trata as imagens já cadastradas (recorte, tamanho e formato), como o painel faz no envio';

    // Mais que isso cortado no recorte do centro: pula (sem --forcar)
    public const CORTE_MAXIMO = 0.5;

    public function handle(): int
    {
        $todos = ! $this->option('campeonatos') && ! $this->option('banners');
        $simular = (bool) $this->option('simular');
        $pastaOriginais = 'imagens-originais/' . now()->format('Ymd-His');

        [$largCamp, $altCamp] = TratadorDeImagem::BANNER_CAMPEONATO;
        [$largSite, $altSite] = TratadorDeImagem::BANNER_SITE;

        // [tabela, coluna, pasta, prefixo, tipo, largura, altura]
        $alvos = [];
        if ($todos || $this->option('campeonatos')) {
            $alvos[] = ['tbl_campeonato', 'logo_evento', CampeonatosController::PASTA_IMAGENS, 'logo-campeonato', 'logo', 0, 0];
            $alvos[] = ['tbl_campeonato', 'banner_evento', CampeonatosController::PASTA_IMAGENS, 'banner-campeonato', 'capa', $largCamp, $altCamp];
        }
        if ($todos || $this->option('banners')) {
            $alvos[] = ['tbl_banner', 'foto_banner', BannersController::PASTA, 'banner-site', 'capa', $largSite, $altSite];
        }

        $linhas = [];
        $tratados = 0;
        $disco = Storage::disk('imagens');

        foreach ($alvos as [$tabela, $coluna, $pasta, $prefixo, $tipo, $largura, $altura]) {
            $arquivos = DB::table($tabela)->whereNotNull($coluna)->where($coluna, '<>', '')->distinct()->orderBy($coluna)->pluck($coluna);

            foreach ($arquivos as $arquivo) {
                $usos = DB::table($tabela)->where($coluna, $arquivo)->count();
                $linha = fn (string $antes, string $depois, string $situacao) => [$coluna, $arquivo, $usos, $antes, $depois, $situacao];

                if (TratadorDeImagem::ehGerado($arquivo)) {
                    $linhas[] = $linha('—', '—', 'já tratado');
                    continue;
                }
                if (! $disco->exists("{$pasta}/{$arquivo}")) {
                    $linhas[] = $linha('—', '—', 'arquivo não encontrado');
                    continue;
                }

                $caminho = $disco->path("{$pasta}/{$arquivo}");
                try {
                    [$l, $a] = TratadorDeImagem::dimensoes($caminho);
                } catch (\Throwable $e) {
                    $linhas[] = $linha('—', '—', 'não é uma imagem que dá para ler');
                    continue;
                }
                $antes = "{$l} × {$a}";

                if ($tipo === 'capa') {
                    $cortada = TratadorDeImagem::fracaoCortada($l, $a, $largura / $altura);
                    [, , $lRecorte] = TratadorDeImagem::recorteDoCentro($l, $a, $largura / $altura);
                    $lFinal = min($lRecorte, $largura);
                    $depois = "{$lFinal} × " . (int) round($lFinal * $altura / $largura) . ' JPG (corta ' . (int) round($cortada * 100) . '%)';
                    if ($cortada > self::CORTE_MAXIMO && ! $this->option('forcar')) {
                        $linhas[] = $linha($antes, $depois, 'pulado: corta demais (recorte pelo formulário ou use --forcar)');
                        continue;
                    }
                } else {
                    $lado = min(TratadorDeImagem::LOGO, max($l, $a));
                    $depois = "{$lado} × {$lado} PNG";
                }

                if ($simular) {
                    $linhas[] = $linha($antes, $depois, 'seria tratado');
                    continue;
                }

                $this->copiarOriginal($caminho, storage_path("app/{$pastaOriginais}/{$pasta}/{$arquivo}"));

                $resultado = $tipo === 'capa'
                    ? TratadorDeImagem::capa($caminho, $pasta, $prefixo, $largura, $altura)
                    : TratadorDeImagem::logo($caminho, $pasta, $prefixo);
                DB::table($tabela)->where($coluna, $arquivo)->update([$coluna => $resultado['arquivo']]);

                $tratados++;
                $linhas[] = $linha($antes, $depois, 'tratado: ' . $resultado['arquivo'] . ($resultado['aviso'] ? ' (pequena)' : ''));
            }
        }

        $this->table(['Coluna', 'Arquivo', 'Registros', 'Antes', 'Depois', 'Situação'], $linhas);

        if ($simular) {
            $this->info('Simulação: nada foi gravado.');
        } else {
            $this->info("{$tratados} arquivo(s) tratado(s)." . ($tratados ? " Originais copiados em storage/app/{$pastaOriginais}." : ''));
        }

        return self::SUCCESS;
    }

    private function copiarOriginal(string $de, string $para): void
    {
        if (! is_dir(dirname($para))) {
            mkdir(dirname($para), 0755, true);
        }
        copy($de, $para);
    }
}
