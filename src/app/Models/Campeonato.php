<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Time;
use App\Models\Jogo;

class Campeonato extends Model
{
    protected $table = 'tbl_campeonato';
    protected $primaryKey = 'id_campeonato';
    public $timestamps = false;

    public function times()
    {
        return $this->belongsToMany(Time::class, 'tbl_campeonato_time', 'id_campeonato', 'id_time');
    }

    public function jogos()
    {
        return $this->hasMany(Jogo::class, 'id_campeonato');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria');
    }
}