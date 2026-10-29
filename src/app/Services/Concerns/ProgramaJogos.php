<?php

namespace App\Services\Concerns;

use App\Models\EventoCalendario;
use App\Models\Jogo;
use App\Models\Local;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * O que o gerador de rodadas (Fase I, Etapa I2) e o montador da próxima fase (Etapa I3) têm em comum: horários do
 * dia, conflito de horário dos convocados e a gravação pelo caminho da tela de Jogos. Quem usa tem $campeonato,
 * $categoria e $opcoes (horario, intervalo, duracao e local).
 *
 * Um jogo da programação: ['data' (Carbon), 'casa' e 'visitante' (id_time e nome_time), 'inicio', 'fim', 'acj',
 * 'colunas' (do tbl_jogos: fase_jogo, rodada_jogo…), e opcionalmente 'chave' (nome para ser achado depois) e
 * 'volta_de' (a chave do jogo de ida: grava o id_jogo_ida)].
 */
trait ProgramaJogos
{
    // Horário do k-ésimo jogo do dia (0 = o primeiro): escalonado pelo intervalo; fim pela duração, se houver
    protected function horarioDoJogo(int $k): array
    {
        $inicio = Carbon::parse('2000-01-01 ' . $this->opcoes['horario'])->addMinutes($k * (int) $this->opcoes['intervalo']);

        return [
            'inicio' => $inicio->format('H:i'),
            'fim'    => filled($this->opcoes['duracao'] ?? null) ? $inicio->copy()->addMinutes((int) $this->opcoes['duracao'])->format('H:i') : null,
        ];
    }

    // Tem time da AACJ (nasce rascunho, com os convocados inscritos)
    protected function temTimeDaAcj(object $casa, object $visitante): bool
    {
        return (bool) Jogo::idsInternos([$casa->id_time, $visitante->id_time]);
    }

    // Conflito de horário dos convocados dos times da AACJ (o mesmo cálculo em lote da geração da grade)
    protected function conflitosDosJogos(array $jogos): array
    {
        $lote = [];
        foreach ($jogos as $jogo) {
            if (! $jogo['acj']) {
                continue;
            }
            $evento = new EventoCalendario([
                'titulo_evento_calendario'         => $jogo['casa']->nome_time . ' x ' . $jogo['visitante']->nome_time,
                'tipo_evento_calendario'           => 'JOGO',
                'data_evento_calendario'           => $jogo['data']->toDateString(),
                'horario_inicio_evento_calendario' => $jogo['inicio'],
                'horario_fim_evento_calendario'    => $jogo['fim'],
            ]);
            $lote[] = ['evento' => $evento, 'atletas' => array_keys(Jogo::atletasDosTimes(
                Jogo::idsInternos([$jogo['casa']->id_time, $jogo['visitante']->id_time]),
                $this->campeonato->id_campeonato, idCategoria: $this->categoria->id_categoria))];
        }

        return EventoCalendario::conflitosEmLote($lote);
    }

    /**
     * Avisos (não bloqueiam) de time que ficaria com dois jogos no mesmo dia: contra os jogos já cadastrados (de
     * qualquer campeonato ou amistoso, fora os ocultos) e entre os da própria programação. Vale para qualquer time,
     * interno ou externo: o conflito de horário só olha os atletas da AACJ, e os externos não têm atletas.
     */
    protected function avisosDeTimesNoMesmoDia(array $jogos): array
    {
        if (! $jogos) {
            return [];
        }

        $ids  = collect($jogos)->flatMap(fn ($j) => [(int) $j['casa']->id_time, (int) $j['visitante']->id_time])->unique()->all();
        $dias = collect($jogos)->map(fn ($j) => $j['data']->toDateString())->unique()->all();
        $existentes = Jogo::with('evento')
            ->where(fn ($q) => $q->whereIn('id_time_casa', $ids)->orWhereIn('id_time_visitante', $ids))
            ->whereHas('evento', fn ($q) => $q->whereIn('data_evento_calendario', $dias)->where('status_evento_calendario', '<>', 'INATIVO'))
            ->get();

        $nomes = [];
        foreach ($jogos as $jogo) {
            $nomes[(int) $jogo['casa']->id_time]      = $jogo['casa']->nome_time;
            $nomes[(int) $jogo['visitante']->id_time] = $jogo['visitante']->nome_time;
        }
        $avisos  = [];
        $doLote  = [];   // [id_time][dia] => horário
        foreach ($jogos as $jogo) {
            $dia = $jogo['data']->toDateString();
            foreach ([(int) $jogo['casa']->id_time, (int) $jogo['visitante']->id_time] as $idTime) {
                foreach ($existentes as $outro) {
                    if ($outro->evento->data_evento_calendario->toDateString() === $dia && in_array($idTime, [(int) $outro->id_time_casa, (int) $outro->id_time_visitante], true)) {
                        $avisos["{$idTime}|{$dia}|{$outro->id_jogo}"] = "{$nomes[$idTime]} já tem jogo em " . $jogo['data']->format('d/m')
                            . ' às ' . substr((string) $outro->evento->horario_inicio_evento_calendario, 0, 5) . " ({$outro->evento->titulo_evento_calendario}).";
                    }
                }
                if (isset($doLote[$idTime][$dia])) {
                    $avisos["{$idTime}|{$dia}|lote"] = "{$nomes[$idTime]} ficaria com dois jogos em " . $jogo['data']->format('d/m')
                        . " (às {$doLote[$idTime][$dia]} e às {$jogo['inicio']}).";
                }
                $doLote[$idTime][$dia] ??= $jogo['inicio'];
            }
        }

        return array_values($avisos);
    }

    // Grava os jogos numa transação (tudo ou nada), pelo mesmo caminho da tela de Jogos (Jogo::criarComEvento)
    protected function gravarJogos(array $jogos, ?int $idUsuario): void
    {
        // O local escolhido do cadastro (L2), o texto das requisições antigas ou, sem nenhum, o local principal do campeonato
        $escolhido = filled($this->opcoes['id_local'] ?? null) ? Local::find((int) $this->opcoes['id_local']) : null;
        [$idLocal, $local] = match (true) {
            $escolhido !== null                   => [$escolhido->id_local, $escolhido->nome_local],
            filled($this->opcoes['local'] ?? null) => [null, $this->opcoes['local']],
            default                               => [$this->campeonato->id_local, $this->campeonato->local_evento],
        };

        DB::transaction(function () use ($jogos, $idUsuario, $local, $idLocal) {
            $criados = [];
            foreach ($jogos as $jogo) {
                $colunas = $jogo['colunas'];
                if (isset($jogo['volta_de'])) {
                    $colunas['id_jogo_ida'] = $criados[$jogo['volta_de']]->id_jogo;
                }

                [, $criado] = Jogo::criarComEvento($idUsuario, [
                    'id_campeonato'     => $this->campeonato->id_campeonato,
                    'id_time_casa'      => $jogo['casa']->id_time,
                    'id_time_visitante' => $jogo['visitante']->id_time,
                    ...$colunas,
                ], [
                    'titulo_evento_calendario'         => Jogo::tituloPara($jogo['casa']->id_time, $jogo['visitante']->id_time),
                    'tipo_evento_calendario'           => 'JOGO',
                    'id_categoria'                     => $this->categoria->id_categoria,
                    'data_evento_calendario'           => $jogo['data']->toDateString(),
                    'horario_inicio_evento_calendario' => $jogo['inicio'],
                    'horario_fim_evento_calendario'    => $jogo['fim'],
                    'local_evento_calendario'          => $local,
                    'id_local'                         => $idLocal,
                    'status_evento_calendario'         => 'ATIVO',
                ]);

                if (isset($jogo['chave'])) {
                    $criados[$jogo['chave']] = $criado;
                }
            }
        });
    }
}
