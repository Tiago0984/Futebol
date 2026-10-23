<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Um gol da súmula do jogo (Etapa S1, 22/10/2026), no time que ganhou o gol no placar (id_time).
 * - NORMAL / PENALTI: gol de um time da AACJ; autor = atleta escalado nesse time (NULL = não identificado);
 *   assistência opcional, de outro escalado no mesmo time.
 * - CONTRA: sem autor = gol contra de um adversário (id_time = o time da AACJ); com autor = gol contra de um atleta
 *   da AACJ, escalado no outro time do jogo (soma para o adversário e não conta como gol dele). Sem assistência.
 * Os gols do adversário não são lançados (os externos não têm atletas). Os pênaltis da disputa não entram.
 */
class JogoGol extends Model
{
    protected $table      = 'tbl_jogo_gol';
    protected $primaryKey = 'id_jogo_gol';
    public    $timestamps = false;

    public const TIPOS = [
        'NORMAL'  => 'Gol',
        'PENALTI' => 'Pênalti',
        'CONTRA'  => 'Gol contra',
    ];

    public const PERIODOS = [
        'TEMPO_NORMAL' => 'Tempo normal',
        'PRORROGACAO'  => 'Prorrogação',
    ];

    public const MINUTO_MAX = 130;

    // id_usuario fora: quem lançou é o usuário logado (gravado pelo controller)
    protected $fillable = [
        'id_jogo',
        'id_time',
        'id_atleta',
        'id_atleta_assistencia',
        'tipo_jogo_gol',
        'periodo_jogo_gol',
        'minuto_jogo_gol',
    ];

    protected $casts = [
        'data_jogo_gol' => 'datetime',
    ];

    public function jogo()
    {
        return $this->belongsTo(Jogo::class, 'id_jogo', 'id_jogo');
    }

    public function time()
    {
        return $this->belongsTo(Time::class, 'id_time', 'id_time');
    }

    public function atleta()
    {
        return $this->belongsTo(Atleta::class, 'id_atleta', 'id_atleta');
    }

    public function assistente()
    {
        return $this->belongsTo(Atleta::class, 'id_atleta_assistencia', 'id_atleta');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function ehContra(): bool
    {
        return $this->tipo_jogo_gol === 'CONTRA';
    }

    // Conta como gol do autor: não é contra e tem autor
    public function contaParaOAutor(): bool
    {
        return ! $this->ehContra() && $this->id_atleta !== null;
    }

    // "Gol" / "Pênalti" / "Gol contra", para a lista da súmula
    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo_jogo_gol] ?? $this->tipo_jogo_gol;
    }

    public function getPeriodoLabelAttribute(): string
    {
        return self::PERIODOS[$this->periodo_jogo_gol] ?? $this->periodo_jogo_gol;
    }

    /**
     * Erros de um gol novo no jogo ([campo => mensagem]; vazio = pode gravar). Confere os times, o autor e a
     * assistência pela escalação, o período e se o placar ainda comporta mais um gol daquele time naquele período.
     *
     * @param array $dados id_time, id_atleta, id_atleta_assistencia, tipo_jogo_gol, periodo_jogo_gol (inteiros ou null)
     */
    public static function errosPara(Jogo $jogo, array $dados): array
    {
        if (! $jogo->temPlacar()) {
            return ['id_time' => 'Lance o placar do jogo antes da súmula.'];
        }

        $idTime  = $dados['id_time'];
        $tipo    = $dados['tipo_jogo_gol'];
        $periodo = $dados['periodo_jogo_gol'];
        $autor   = $dados['id_atleta'];
        $assist  = $dados['id_atleta_assistencia'];

        $times = [(int) $jogo->id_time_casa, (int) $jogo->id_time_visitante];
        if (! in_array($idTime, $times, true)) {
            return ['id_time' => 'Escolha o mandante ou o visitante deste jogo.'];
        }
        $outroTime = $idTime === $times[0] ? $times[1] : $times[0];
        $internos  = $jogo->timesEscalaveis()->pluck('id_time')->map(fn ($id) => (int) $id)->all();

        $erros = [];

        if ($tipo === 'CONTRA' && $autor !== null) {
            // Gol contra de um atleta da AACJ: ele joga pelo outro time, que precisa ser da AACJ
            if (! $jogo->escalouNoTime($autor, $outroTime)) {
                $erros['id_atleta'] = 'No gol contra, o autor é um atleta escalado no time que sofreu o gol.';
            }
        } elseif (! in_array($idTime, $internos, true)) {
            $erros['id_time'] = 'Os gols do time adversário não são lançados (ele não tem atletas no sistema). Lance só o gol contra de um atleta da AACJ.';
        } elseif ($autor !== null && ! $jogo->escalouNoTime($autor, $idTime)) {
            $erros['id_atleta'] = 'O autor precisa estar escalado no time que fez o gol.';
        }

        if ($assist !== null) {
            if ($tipo === 'CONTRA') {
                $erros['id_atleta_assistencia'] = 'Gol contra não tem assistência.';
            } elseif ($autor === null) {
                $erros['id_atleta_assistencia'] = 'Para lançar a assistência, escolha o autor do gol.';
            } elseif ($assist === $autor) {
                $erros['id_atleta_assistencia'] = 'A assistência é de outro atleta, não do autor do gol.';
            } elseif (! $jogo->escalouNoTime($assist, $idTime)) {
                $erros['id_atleta_assistencia'] = 'A assistência precisa ser de um atleta escalado no time que fez o gol.';
            }
        }

        if ($periodo === 'PRORROGACAO' && ! $jogo->temProrrogacao()) {
            $erros['periodo_jogo_gol'] = 'Este jogo não teve prorrogação (lance o placar da prorrogação antes).';
        }

        if (! $erros) {
            $noPlacar = $jogo->golsNoPlacar($idTime, $periodo);
            $lancados = $jogo->gols()->where('id_time', $idTime)->where('periodo_jogo_gol', $periodo)->count();
            if ($lancados >= $noPlacar) {
                $time = $idTime === $times[0] ? $jogo->timeCasa : $jogo->timeVisitante;
                $erros['id_time'] = "O placar do {$time->nome_time} ("
                    . mb_strtolower(self::PERIODOS[$periodo]) . ") é {$noPlacar} e já tem {$lancados} gol(s) lançado(s). Corrija o placar antes.";
            }
        }

        return $erros;
    }
}
