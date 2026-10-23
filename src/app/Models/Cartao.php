<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cartao extends Model
{
    protected $table = 'tbl_cartoes';
    protected $primaryKey = 'id_cartao';
    public $timestamps = false;

    // Súmula do jogo (Etapa S1, 22/10/2026): lançados pelo admin na tela do jogo, só para atleta da AACJ escalado
    public const TIPOS = [
        'AMARELO'  => 'Amarelo',
        'VERMELHO' => 'Vermelho',
    ];
    public const AMARELOS_MAX = 2; // o segundo amarelo é a expulsão

    // id_usuario fora: quem lançou é o usuário logado (gravado pelo controller)
    protected $fillable = [
        'id_campeonato',
        'id_atleta',
        'id_jogo',
        'tipo_cartao',
        'data_cartao',
    ];

    protected $casts = [
        'data_cartao' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    // Erro de um cartão novo no jogo (null = pode): atleta escalado num time da AACJ, no máximo 2 amarelos e 1 vermelho
    public static function erroPara(Jogo $jogo, int $idAtleta, string $tipo): ?string
    {
        $time = EventoAtleta::where('id_evento_calendario', $jogo->id_evento)->where('id_atleta', $idAtleta)->value('id_time');
        if ($time === null || ! $jogo->escalouNoTime($idAtleta, (int) $time)) {
            return 'O cartão é de um atleta escalado num time da AACJ neste jogo.';
        }

        $doAtleta = $jogo->cartoes()->where('id_atleta', $idAtleta)->pluck('tipo_cartao');
        if ($tipo === 'VERMELHO' && $doAtleta->contains('VERMELHO')) {
            return 'Este atleta já tem cartão vermelho neste jogo.';
        }
        if ($tipo === 'AMARELO' && $doAtleta->filter(fn ($t) => $t === 'AMARELO')->count() >= self::AMARELOS_MAX) {
            return 'Este atleta já tem ' . self::AMARELOS_MAX . ' cartões amarelos neste jogo.';
        }

        return null;
    }

    public function campeonato()
    {
        return $this->belongsTo(Campeonato::class, 'id_campeonato', 'id_campeonato');
    }

    public function atleta()
    {
        return $this->belongsTo(Atleta::class, 'id_atleta', 'id_atleta');
    }

    public function jogo()
    {
        return $this->belongsTo(Jogo::class, 'id_jogo', 'id_jogo');
    }
}
