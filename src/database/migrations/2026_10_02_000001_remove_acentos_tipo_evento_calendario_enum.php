<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TIPOS_NOVOS = ['JOGO', 'TREINO', 'CAMPEONATO', 'EVENTO', 'REUNIAO', 'CONFRATERNIZACAO', 'AVALIACAO'];

    private const TIPOS_ANTIGOS = ['JOGO', 'TREINO', 'CAMPEONATO', 'EVENTO', 'REUNIÃO', 'CONFRATERNIZAÇÃO'];

    // Valor sem acento => grafias que viram ele. A corrompida (UTF-8 lido como Windows-1252
    // e salvo de novo) fica em escapes de bytes para não depender da codificação do arquivo.
    private const CONVERSOES = [
        'REUNIAO' => [
            'REUNIÃO',
            "REUNI\xC3\x83\xC6\x92O",
        ],
        'CONFRATERNIZACAO' => [
            'CONFRATERNIZAÇÃO',
            "CONFRATERNIZA\xC3\x83\xE2\x80\xA1\xC3\x83\xC6\x92O",
        ],
    ];

    /**
     * Run the migrations.
     *
     * A coluna usa utf8mb4_general_ci, que ignora acentos: o MySQL recusa um ENUM com
     * 'REUNIÃO' e 'REUNIAO' ao mesmo tempo (valor duplicado). Por isso a conversão passa
     * por VARCHAR em vez de ampliar o ENUM com os valores antigos e novos.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY tipo_evento_calendario VARCHAR(30) NOT NULL");

        foreach (self::CONVERSOES as $novo => $grafias) {
            DB::table('tbl_evento_calendario')->whereIn('tipo_evento_calendario', $grafias)->update(['tipo_evento_calendario' => $novo]);
        }

        $this->travaValoresFora(self::TIPOS_NOVOS);

        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY tipo_evento_calendario ENUM('" . implode("', '", self::TIPOS_NOVOS) . "') NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * AVALIACAO não existe no ENUM antigo: esses eventos voltam como EVENTO.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY tipo_evento_calendario VARCHAR(30) NOT NULL");

        DB::table('tbl_evento_calendario')->where('tipo_evento_calendario', 'REUNIAO')->update(['tipo_evento_calendario' => 'REUNIÃO']);
        DB::table('tbl_evento_calendario')->where('tipo_evento_calendario', 'CONFRATERNIZACAO')->update(['tipo_evento_calendario' => 'CONFRATERNIZAÇÃO']);
        DB::table('tbl_evento_calendario')->where('tipo_evento_calendario', 'AVALIACAO')->update(['tipo_evento_calendario' => 'EVENTO']);

        $this->travaValoresFora(self::TIPOS_ANTIGOS);

        DB::statement("ALTER TABLE tbl_evento_calendario
            MODIFY tipo_evento_calendario ENUM('" . implode("', '", self::TIPOS_ANTIGOS) . "') NOT NULL");
    }

    /**
     * Impede o MODIFY para ENUM se sobrar algum valor fora da lista (o MySQL gravaria '' no lugar).
     * A comparação (e o "distinct") é feita no PHP, byte a byte: no banco, a general_ci acharia
     * 'REUNIÃO' igual a 'REUNIAO' e o DISTINCT poderia esconder justamente o valor que sobrou.
     * Se a trava disparar, a coluna fica como VARCHAR sem perda de dados; basta corrigir e rodar de novo.
     */
    private function travaValoresFora(array $permitidos): void
    {
        $fora = DB::table('tbl_evento_calendario')
            ->pluck('tipo_evento_calendario')
            ->unique()
            ->reject(fn ($tipo) => in_array($tipo, $permitidos, true))
            ->values();

        if ($fora->isNotEmpty()) {
            throw new RuntimeException(
                'tbl_evento_calendario.tipo_evento_calendario tem valores fora da lista ('
                . implode(', ', $permitidos) . '): '
                . $fora->map(fn ($tipo) => "'{$tipo}' (hex " . bin2hex($tipo) . ')')->implode(', ')
                . '. Corrija esses eventos e rode a migration de novo.'
            );
        }
    }
};
