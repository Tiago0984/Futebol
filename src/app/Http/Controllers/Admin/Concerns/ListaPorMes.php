<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\EventoCalendario;
use Illuminate\Support\Carbon;

/**
 * Listas do admin abertas por mês (?mes=AAAA-MM, padrão o mês atual), com setas e select de meses.
 * Usado pelo Calendário e pela página de Notificações.
 */
trait ListaPorMes
{
    // Mês pedido na lista (AAAA-MM); vazio ou inválido = mês atual
    private function mesDaLista(?string $valor): Carbon
    {
        if ($valor && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor)) {
            return Carbon::createFromFormat('!Y-m', $valor);
        }

        return now()->startOfMonth();
    }

    /**
     * Meses do select: do mês mais novo ao mais antigo entre as datas dadas (as vazias são ignoradas),
     * como "AAAA-MM" => "Outubro de 2026". Quem chama inclui o mês atual e o mês aberto.
     */
    private function mesesEntre(array $datas): array
    {
        $datas = collect($datas)->filter()->map(fn ($d) => Carbon::parse($d)->startOfMonth());

        $meses = [];
        for ($m = $datas->max()->copy(); $m->gte($datas->min()); $m->subMonthNoOverflow()) {
            $meses[$m->format('Y-m')] = EventoCalendario::rotuloDoMes($m);
        }

        return $meses;
    }
}
