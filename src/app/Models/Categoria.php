<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Categoria extends Model
{
    protected $table = 'tbl_categoria';
    protected $primaryKey = 'id_categoria';
    public $timestamps = false;

    // Resultado de posicaoPara()
    public const NA_FAIXA = 'NA_FAIXA';
    public const ACIMA    = 'ACIMA';
    public const ABAIXO   = 'ABAIXO';

    // Valores de sexo_categoria => rótulo
    public const SEXOS = [
        'M' => 'Masculino',
        'F' => 'Feminino',
    ];

    protected $fillable = [
        'nome_categoria',
        'idade_min_categoria',
        'idade_max_categoria',
        'sexo_categoria',
        'status_categoria',
    ];

    public function campeonatos()
    {
        return $this->hasMany(Campeonato::class, 'id_categoria', 'id_categoria');
    }

    public function times()
    {
        return $this->hasMany(Time::class, 'id_categoria', 'id_categoria');
    }

    public function atletas()
    {
        return $this->belongsToMany(Atleta::class, 'tbl_categoria_atleta', 'id_categoria', 'id_atleta')
                    ->withPivot([
                        'data_inicio_categoria_atleta',
                        'data_fim_categoria_atleta',
                        'data_atualizacao_categoria_atleta',
                        'status_categoria_atleta',
                        'observacao_categoria_atleta',
                    ]);
    }

    // Nome com o sexo, para não confundir Sub-13 M e Sub-13 F nos selects: "Sub-13 Masculino"
    public function getRotuloAttribute(): string
    {
        $sexo = self::SEXOS[$this->sexo_categoria] ?? $this->sexo_categoria;

        return "{$this->nome_categoria} {$sexo}";
    }

    // Categorias que podem ser escolhidas, na ordem dos selects (masculino e depois feminino, por idade)
    public function scopeAtivas($query)
    {
        return $query->where('status_categoria', 'ATIVO')
            ->orderBy('sexo_categoria', 'desc')
            ->orderBy('idade_min_categoria');
    }

    /**
     * Idade que conta para a categoria: ano de referência − ano de nascimento (regra do professor).
     * A data exata não importa: quem nasceu em 31/12/2017 tem 9 anos durante todo o ano de 2026.
     */
    public static function idadeNoAno($nascimento, ?int $ano = null): int
    {
        return ($ano ?? (int) now()->format('Y')) - Carbon::parse($nascimento)->year;
    }

    // Categoria ativa do mesmo sexo cuja faixa contém a idade no ano; null se nenhuma servir
    public static function sugeridaPara($nascimento, ?string $sexo, ?int $ano = null): ?self
    {
        if (! $nascimento || ! $sexo) {
            return null;
        }

        $idade = self::idadeNoAno($nascimento, $ano);

        return self::where('status_categoria', 'ATIVO')
            ->where('sexo_categoria', $sexo)
            ->where('idade_min_categoria', '<=', $idade)
            ->where('idade_max_categoria', '>=', $idade)
            ->first();
    }

    /**
     * Onde a idade fica em relação a esta categoria:
     * NA_FAIXA; ACIMA = categoria de atletas mais velhos (permitido, com motivo);
     * ABAIXO = categoria de atletas mais novos (bloqueado, provisório: CLAUDE.md, seção 8, pergunta 11).
     */
    public function posicaoPara(int $idade): string
    {
        if ($idade < $this->idade_min_categoria) {
            return self::ACIMA;
        }

        if ($idade > $this->idade_max_categoria) {
            return self::ABAIXO;
        }

        return self::NA_FAIXA;
    }

    /**
     * Confere se o atleta pode ser colocado nesta categoria. Devolve a mensagem de erro, ou null se pode.
     * Usado no cadastro e na edição de atleta e na aprovação da matrícula.
     */
    public function erroParaAtleta($nascimento, ?string $sexo, ?string $motivo, ?int $ano = null): ?string
    {
        if ($this->status_categoria !== 'ATIVO') {
            return "A categoria {$this->rotulo} está inativa.";
        }

        if ($sexo !== $this->sexo_categoria) {
            return "A categoria {$this->rotulo} não é do mesmo sexo do atleta.";
        }

        $idade   = self::idadeNoAno($nascimento, $ano);
        $posicao = $this->posicaoPara($idade);

        if ($posicao === self::ABAIXO) {
            return "O atleta tem {$idade} anos no ano e não pode jogar na {$this->rotulo} (até {$this->idade_max_categoria} anos).";
        }

        if ($posicao === self::ACIMA && blank($motivo)) {
            $sugerida = self::sugeridaPara($nascimento, $sexo, $ano);
            $dica     = $sugerida ? "; sugerida: {$sugerida->rotulo}" : '';

            return "A {$this->rotulo} está acima da idade do atleta ({$idade} anos no ano{$dica}). Informe o motivo para continuar.";
        }

        return null;
    }
}
