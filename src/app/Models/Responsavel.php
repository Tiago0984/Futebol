<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Responsavel extends Model
{
    protected $table = 'tbl_responsavel';
    protected $primaryKey = 'id_responsavel';
    public $timestamps = false;

    // Opções de tbl_atleta_responsavel.grau_parentesco_responsavel (varchar(20)), iguais no site e no admin.
    // O valor gravado é o próprio texto exibido.
    public const GRAUS_PARENTESCO = ['Pai', 'Mãe', 'Avô', 'Avó', 'Tio', 'Tia', 'Responsável Legal', 'Outro'];

    protected $fillable = [
        'nome_responsavel',
        'cpf_responsavel',
        'rg_responsavel',
        'telefone_responsavel',
        'whatsapp_responsavel',
        'email_responsavel',
        'assinatura_responsavel',
        'aceite_responsavel',
        'id_endereco',
    ];

    public function endereco()
    {
        return $this->belongsTo(Endereco::class, 'id_endereco', 'id_endereco');
    }

    public function atletas()
    {
        return $this->belongsToMany(Atleta::class, 'tbl_atleta_responsavel', 'id_responsavel', 'id_atleta')
                    ->withPivot('grau_parentesco_responsavel');
    }

    public function autorizacoes()
    {
        return $this->hasMany(Autorizacao::class, 'id_responsavel', 'id_responsavel');
    }

    /**
     * Converte um grau gravado em outro formato ("PAI", "MAE") para a opção da lista ("Pai", "Mãe").
     * Primeiro compara sem diferenciar maiúsculas; depois, também sem acentos. Valor desconhecido
     * volta como está. Sem acento, "AVO" é ambíguo (Avô/Avó) e vira a primeira opção, Avô.
     */
    public static function normalizarGrau(?string $grau): ?string
    {
        if (blank($grau)) {
            return null;
        }

        $maiusculo = fn (string $texto) => mb_strtoupper(trim($texto));
        $semAcento = fn (string $texto) => Str::ascii($maiusculo($texto));

        foreach ([$maiusculo, $semAcento] as $comparar) {
            foreach (self::GRAUS_PARENTESCO as $opcao) {
                if ($comparar($opcao) === $comparar($grau)) {
                    return $opcao;
                }
            }
        }

        return $grau;
    }
}
