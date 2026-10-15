<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Participantes do campeonato numa categoria (Fase H, Etapa H1): sem $idCategoria, na principal
     * (tbl_campeonato.id_categoria). Põe a categoria na lista do campeonato antes (a FK composta exige).
     */
    protected function participantes(int $idCampeonato, array $idsTimes, ?int $idCategoria = null): void
    {
        $idCategoria ??= (int) DB::table('tbl_campeonato')->where('id_campeonato', $idCampeonato)->value('id_categoria');

        DB::table('tbl_campeonato_categoria')->insertOrIgnore(['id_campeonato' => $idCampeonato, 'id_categoria' => $idCategoria]);
        foreach ($idsTimes as $idTime) {
            DB::table('tbl_campeonato_time')->insert(['id_campeonato' => $idCampeonato, 'id_categoria' => $idCategoria, 'id_time' => $idTime]);
        }
    }

    /**
     * Convocação gravada direto (sem a tela), na categoria do time no campeonato (Fase H, Etapa H2). O time que
     * ainda não é participante entra na categoria de referência (a FK composta exige o participante). Sem $camisa,
     * um número ainda não usado.
     */
    protected function convocacaoDireta(int $idCampeonato, int $idTime, int $idAtleta, ?int $camisa = null): void
    {
        $doTime = fn () => DB::table('tbl_campeonato_time')->where('id_campeonato', $idCampeonato)->where('id_time', $idTime)->value('id_categoria');
        if ($doTime() === null) {
            $this->participantes($idCampeonato, [$idTime]);
        }

        DB::table('tbl_convocacao')->insert([
            'id_campeonato'     => $idCampeonato,
            'id_categoria'      => $doTime(),
            'id_time'           => $idTime,
            'id_atleta'         => $idAtleta,
            'camisa_convocacao' => $camisa ?? DB::table('tbl_convocacao')->count() + 1,
        ]);
    }
}
