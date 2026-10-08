<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Linha de dois dias => [dia que fica na linha, dia da linha nova]. Sexta e sábado não mudam.
    public const PARES = [
        'segunda_quarta' => ['segunda', 'quarta'],
        'terca_quinta'   => ['terca', 'quinta'],
    ];

    // Dia do ENUM => DAYOFWEEK() do MySQL (1 = domingo ... 7 = sábado)
    public const DAYOFWEEK = [
        'domingo' => 1, 'segunda' => 2, 'terca' => 3, 'quarta' => 4, 'quinta' => 5, 'sexta' => 6, 'sabado' => 7,
    ];

    private const ENUM_ANTIGO = "'segunda_quarta', 'terca_quinta', 'sexta', 'sabado'";
    private const ENUM_NOVO   = "'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'";
    private const ENUM_LARGO  = "'segunda_quarta', 'terca_quinta', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'";

    // Colunas copiadas para a linha nova (tudo menos o id e o dia)
    private const COLUNAS_COPIADAS = [
        'id_categoria', 'categoria_grade_treino', 'tipo_grade_treino', 'horario_inicio_grade_treino',
        'horario_fim_grade_treino', 'horario_obs_grade_treino', 'local_grade_treino', 'ordem_grade_treino',
        'status_grade_treino',
    ];

    /**
     * Run the migrations.
     *
     * A grade passa a ter um dia por linha, de segunda a domingo (antes: segunda_quarta, terca_quinta,
     * sexta e sabado). Cada linha de dois dias continua com o id (e os eventos) do primeiro dia e ganha uma
     * linha nova, cópia dela, para o segundo; os eventos gerados com data de ORIGEM no segundo dia passam
     * para a linha nova (pela data de origem: o evento pode ter mudado de data à mão). A chave única
     * (grade + data de origem) continua valendo, então gerar o mês de novo não duplica.
     *
     * ENUM em três passos: amplia (antigos + novos), move os dados, reduz aos 7 dias. A trava roda antes do
     * primeiro ALTER (DDL no MySQL não volta com rollback).
     */
    public function up(): void
    {
        $this->conferirDatasDeOrigem();

        $this->alterarEnum(self::ENUM_LARGO);
        DB::transaction(fn () => $this->separarLinhas());
        $this->alterarEnum(self::ENUM_NOVO);
    }

    /**
     * Reverse the migrations.
     *
     * Junta de volta só os pares idênticos (segunda + quarta, terça + quinta, com os mesmos dados): os eventos
     * da linha do segundo dia voltam para a do primeiro e a linha do segundo dia é apagada. Domingo, ou uma
     * linha sem par, para antes de mudar qualquer coisa (o caminho seguro é o backup).
     */
    public function down(): void
    {
        $pares = $this->paresParaJuntar();

        $this->alterarEnum(self::ENUM_LARGO);
        DB::transaction(fn () => $this->juntarLinhas($pares));
        $this->alterarEnum(self::ENUM_ANTIGO);
    }

    // Públicos para o teste rodar cada parte sobre dados simulados

    /**
     * Para se algum evento gerado tem data de origem fora dos dias da linha dele (não teria para onde ir).
     */
    public function conferirDatasDeOrigem(): void
    {
        $diasPorValor = [
            'segunda_quarta' => ['segunda', 'quarta'], 'terca_quinta' => ['terca', 'quinta'],
            'sexta'          => ['sexta'],             'sabado'       => ['sabado'],
        ];

        $fora = DB::table('tbl_evento_calendario as e')
            ->join('tbl_grade_treino as g', 'g.id_grade_treino', '=', 'e.id_grade_treino')
            ->whereIn('g.dia_semana_grade_treino', array_keys($diasPorValor))
            ->selectRaw('e.id_evento_calendario, e.data_grade_evento_calendario, g.id_grade_treino, g.dia_semana_grade_treino,
                DAYOFWEEK(e.data_grade_evento_calendario) AS dia_da_semana')
            ->get()
            ->reject(fn ($e) => in_array(
                (int) $e->dia_da_semana,
                array_map(fn ($dia) => self::DAYOFWEEK[$dia], $diasPorValor[$e->dia_semana_grade_treino]),
                true
            ));

        if ($fora->isNotEmpty()) {
            throw new RuntimeException(
                'Eventos gerados com data de origem fora dos dias da linha da grade (evento/linha/data): '
                . $fora->map(fn ($e) => "{$e->id_evento_calendario}/{$e->id_grade_treino}/{$e->data_grade_evento_calendario}")->implode(', ')
                . '. Corrija antes de rodar de novo.'
            );
        }
    }

    // Cada linha de dois dias: fica com o primeiro dia; a cópia recebe o segundo e os eventos dele
    public function separarLinhas(): void
    {
        $linhas = DB::table('tbl_grade_treino')
            ->whereIn('dia_semana_grade_treino', array_keys(self::PARES))
            ->orderBy('id_grade_treino')
            ->lockForUpdate()
            ->get();

        foreach ($linhas as $linha) {
            [$primeiro, $segundo] = self::PARES[$linha->dia_semana_grade_treino];

            $copia = collect((array) $linha)->only(self::COLUNAS_COPIADAS)->all();
            $idNova = DB::table('tbl_grade_treino')->insertGetId(['dia_semana_grade_treino' => $segundo] + $copia);

            DB::table('tbl_evento_calendario')
                ->where('id_grade_treino', $linha->id_grade_treino)
                ->whereRaw('DAYOFWEEK(data_grade_evento_calendario) = ?', [self::DAYOFWEEK[$segundo]])
                ->update(['id_grade_treino' => $idNova]);

            DB::table('tbl_grade_treino')
                ->where('id_grade_treino', $linha->id_grade_treino)
                ->update(['dia_semana_grade_treino' => $primeiro]);
        }
    }

    /**
     * Pares [id que fica, id que sai] para o down(). Para (sem mexer em nada) se houver domingo ou linha de
     * segunda a quinta sem o par idêntico.
     */
    public function paresParaJuntar(): array
    {
        $linhas = DB::table('tbl_grade_treino')
            ->whereIn('dia_semana_grade_treino', ['segunda', 'terca', 'quarta', 'quinta', 'domingo'])
            ->orderBy('id_grade_treino')
            ->get();

        $problemas = $linhas->where('dia_semana_grade_treino', 'domingo')
            ->map(fn ($l) => "linha {$l->id_grade_treino} (domingo)")
            ->values()->all();

        $pares   = [];
        $usadas  = [];
        $chave   = fn ($l) => json_encode(collect((array) $l)->only(self::COLUNAS_COPIADAS)->all());

        foreach (self::PARES as [$primeiro, $segundo]) {
            $segundos = $linhas->where('dia_semana_grade_treino', $segundo);

            foreach ($linhas->where('dia_semana_grade_treino', $primeiro) as $fica) {
                $sai = $segundos->first(fn ($l) => ! isset($usadas[$l->id_grade_treino]) && $chave($l) === $chave($fica));

                if ($sai === null) {
                    $problemas[] = "linha {$fica->id_grade_treino} ({$primeiro}) sem {$segundo} igual";
                    continue;
                }

                $usadas[$sai->id_grade_treino] = true;
                $pares[] = [$fica->id_grade_treino, $sai->id_grade_treino];
            }

            foreach ($segundos as $l) {
                if (! isset($usadas[$l->id_grade_treino])) {
                    $problemas[] = "linha {$l->id_grade_treino} ({$segundo}) sem {$primeiro} igual";
                }
            }
        }

        if ($problemas) {
            throw new RuntimeException(
                'A grade não volta para os dias em pares: ' . implode(', ', $problemas)
                . '. Restaure o backup de antes da migration.'
            );
        }

        return $pares;
    }

    public function juntarLinhas(array $pares): void
    {
        $voltaPara = array_flip(array_map(fn ($par) => $par[0], self::PARES));

        foreach ($pares as [$idFica, $idSai]) {
            DB::table('tbl_evento_calendario')->where('id_grade_treino', $idSai)->update(['id_grade_treino' => $idFica]);
            DB::table('tbl_grade_treino')->where('id_grade_treino', $idSai)->delete();

            $dia = DB::table('tbl_grade_treino')->where('id_grade_treino', $idFica)->value('dia_semana_grade_treino');
            DB::table('tbl_grade_treino')->where('id_grade_treino', $idFica)->update(['dia_semana_grade_treino' => $voltaPara[$dia]]);
        }
    }

    private function alterarEnum(string $valores): void
    {
        DB::statement("ALTER TABLE tbl_grade_treino MODIFY dia_semana_grade_treino ENUM({$valores}) NOT NULL");
    }
};
