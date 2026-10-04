<?php

namespace App\Models\Concerns;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Formato único das datas no JSON (API /api/v1): ISO 8601 com o deslocamento de Brasília e sem
 * milissegundos, ex.: 2099-05-01T19:00:00-03:00. Quem converte a data (new Date) acerta o instante;
 * quem só recorta o texto lê a hora local. Sem isto, o Laravel manda em UTC ("...T22:00:00.000000Z").
 *
 * Vale para colunas com cast date/datetime (serializeDate) e para accessors anexados ($appends) que
 * devolvem data, como Jogo::data_jogo (mutateAttributeForArray). Só muda toArray()/toJson(): nas telas,
 * $model->campo continua sendo Carbon.
 */
trait SerializaDatasComFuso
{
    public const FORMATO_DATA_JSON = 'Y-m-d\TH:i:sP';

    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)->setTimezone(config('app.timezone'))->format(self::FORMATO_DATA_JSON);
    }

    protected function mutateAttributeForArray($key, $value)
    {
        $valor = parent::mutateAttributeForArray($key, $value);

        return $valor instanceof DateTimeInterface ? $this->serializeDate($valor) : $valor;
    }
}
