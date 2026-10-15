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
}
