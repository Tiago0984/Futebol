<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\ImageManager;

/**
 * Tratamento das imagens enviadas pelo painel (26/10/2026; formatos aprovados pelo dono do projeto), para quem não
 * entende de imagem poder usar qualquer foto:
 * - capa (Banners da home 1920×1080, banner do campeonato 1920×960): recorte na proporção (o do formulário, pelo
 *   Cropper.js, ou o centro) e redução, em JPG; menor que o formato não é ampliada (só borraria);
 * - logo do campeonato: a imagem inteira, sem cortar, centralizada num quadrado de até 512×512 com fundo
 *   transparente, em PNG.
 * A rotação das fotos de celular (EXIF) é acertada na leitura. Grava no disco "imagens" (public/futebol/images)
 * com nome gerado; quem chama grava o nome no banco e apaga o antigo (apagarSeNinguemUsa).
 */
class TratadorDeImagem
{
    public const BANNER_SITE = [1920, 1080];
    public const BANNER_CAMPEONATO = [1920, 960];
    public const LOGO = 512;
    public const QUALIDADE_JPG = 82;

    // Abaixo disso, aviso (sem bloquear): pode ficar sem nitidez
    public const LARGURA_MINIMA_CAPA = 1200;
    public const LADO_MINIMO_LOGO = 256;

    // Limite de envio (KB) na validação: a foto é reduzida aqui, então pode vir direto do celular
    public const ENVIO_MAX_KB = 10240;

    /**
     * Mensagens de validação dos campos de imagem, em português (o admin usa o idioma padrão do Laravel): "uploaded"
     * (o PHP recusou o arquivo, acima do upload_max_filesize) e "max" (acima do limite do campo) dizem o mesmo.
     */
    public static function mensagensDoEnvio(array $campos, int $maxKb = self::ENVIO_MAX_KB): array
    {
        $texto = 'Não foi possível enviar a imagem. Use um arquivo de até ' . round($maxKb / 1024) . ' MB.';

        return collect($campos)->flatMap(fn ($campo) => ["{$campo}.uploaded" => $texto, "{$campo}.max" => $texto])->all();
    }

    // Diferença aceita entre a proporção do recorte enviado e a do formato (o Cropper arredonda os pixels)
    private const TOLERANCIA_PROPORCAO = 0.02;

    // Nome gerado: "banner-campeonato-20261026-141500-a1b2c3.jpg". Só arquivos com esse nome são apagados (os que
    // vieram com o projeto, no Git, ficam)
    private const NOME_GERADO = '/^(logo-campeonato|banner-campeonato|banner-site)-\d{8}-\d{6}-[a-z0-9]{6}\.(jpg|png)$/';

    /**
     * Capa no formato $largura × $altura, em JPG. $recorte: "x,y,largura,altura" em pixels da imagem já virada
     * (o do Cropper.js); inválido, fora da imagem ou noutra proporção = o centro.
     *
     * @param string $origem caminho do arquivo enviado (ou de um já gravado, no reprocessamento)
     * @return array{arquivo: string, largura: int, altura: int, aviso: ?string}
     */
    public static function capa(string $origem, string $pasta, string $prefixo, int $largura, int $altura, ?string $recorte = null): array
    {
        $imagem = self::ler($origem);
        [$x, $y, $l, $a] = self::recorteValido($recorte, $imagem->width(), $imagem->height(), $largura / $altura)
            ?? self::recorteDoCentro($imagem->width(), $imagem->height(), $largura / $altura);

        $imagem->crop($l, $a, $x, $y);
        if ($l > $largura) {
            $imagem->resize($largura, $altura);
        }

        $arquivo = self::gravar($pasta, $prefixo, 'jpg', (string) $imagem->toJpeg(quality: self::QUALIDADE_JPG, progressive: true));

        return [
            'arquivo' => $arquivo,
            'largura' => $imagem->width(),
            'altura'  => $imagem->height(),
            'aviso'   => $imagem->width() < self::LARGURA_MINIMA_CAPA
                ? "Imagem pequena ({$imagem->width()} × {$imagem->height()} px): pode ficar sem nitidez no site. O ideal é uma foto com pelo menos " . self::LARGURA_MINIMA_CAPA . ' px de largura.'
                : null,
        ];
    }

    /**
     * Logo inteira num quadrado de lado min(512, maior lado da imagem), centralizada, fundo transparente, em PNG.
     *
     * @return array{arquivo: string, largura: int, altura: int, aviso: ?string}
     */
    public static function logo(string $origem, string $pasta, string $prefixo): array
    {
        $imagem = self::ler($origem);
        $maior = max($imagem->width(), $imagem->height());
        $lado = min(self::LOGO, $maior);

        // pad: como o contain, mas sem ampliar a imagem pequena
        $imagem->pad($lado, $lado, 'rgba(0, 0, 0, 0)');

        $arquivo = self::gravar($pasta, $prefixo, 'png', (string) $imagem->toPng());

        return [
            'arquivo' => $arquivo,
            'largura' => $lado,
            'altura'  => $lado,
            'aviso'   => $maior < self::LADO_MINIMO_LOGO
                ? "Logo pequena: o lado maior tem {$maior} px. Pode ficar sem nitidez no site; o ideal é o lado maior com pelo menos " . self::LADO_MINIMO_LOGO . ' px.'
                : null,
        ];
    }

    /**
     * Maior área na proporção, centralizada: [x, y, largura, altura].
     */
    public static function recorteDoCentro(int $largura, int $altura, float $proporcao): array
    {
        if ($largura / $altura > $proporcao) {
            $l = max(1, (int) round($altura * $proporcao));

            return [intdiv($largura - $l, 2), 0, $l, $altura];
        }

        $a = max(1, (int) round($largura / $proporcao));

        return [0, intdiv($altura - $a, 2), $largura, $a];
    }

    /**
     * Quanto da imagem sai no recorte do centro (0 a 1), para o reprocessamento avisar o que corta demais.
     */
    public static function fracaoCortada(int $largura, int $altura, float $proporcao): float
    {
        [, , $l, $a] = self::recorteDoCentro($largura, $altura, $proporcao);

        return 1 - ($l * $a) / ($largura * $altura);
    }

    // [largura, altura] já com a rotação do EXIF (a mesma leitura do tratamento)
    public static function dimensoes(string $origem): array
    {
        $imagem = self::ler($origem);

        return [$imagem->width(), $imagem->height()];
    }

    // Nome gerado por esta classe (já tratado)
    public static function ehGerado(?string $arquivo): bool
    {
        return (bool) preg_match(self::NOME_GERADO, (string) $arquivo);
    }

    // Arquivo gerado por esta classe e que nenhum registro usa mais: apaga (os do projeto, no Git, ficam)
    public static function apagarSeNinguemUsa(string $pasta, ?string $arquivo, callable $emUso): void
    {
        if ($arquivo && self::ehGerado($arquivo) && ! $emUso($arquivo)) {
            Storage::disk('imagens')->delete("{$pasta}/{$arquivo}");
        }
    }

    private static function ler(string $origem): ImageInterface
    {
        // autoOrientation: foto de celular "em pé" (EXIF) chega virada; decodeAnimation: GIF animado, só o 1º quadro
        return ImageManager::gd(autoOrientation: true, decodeAnimation: false)->read($origem);
    }

    // "x,y,largura,altura" dentro da imagem e na proporção (com tolerância), ou null
    private static function recorteValido(?string $recorte, int $largura, int $altura, float $proporcao): ?array
    {
        if (! $recorte || ! preg_match('/^\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*$/', $recorte, $m)) {
            return null;
        }

        [$x, $y, $l, $a] = array_map('intval', array_slice($m, 1));
        // O Cropper pode passar 1 pixel da borda ao arredondar
        $l = min($l, $largura - $x);
        $a = min($a, $altura - $y);

        if ($l < 1 || $a < 1 || abs($l / $a - $proporcao) / $proporcao > self::TOLERANCIA_PROPORCAO) {
            return null;
        }

        return [$x, $y, $l, $a];
    }

    private static function gravar(string $pasta, string $prefixo, string $extensao, string $conteudo): string
    {
        $arquivo = $prefixo . '-' . now()->format('Ymd-His') . '-' . Str::lower(Str::random(6)) . ".{$extensao}";
        Storage::disk('imagens')->put("{$pasta}/{$arquivo}", $conteudo);

        return $arquivo;
    }
}
