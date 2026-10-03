<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class Atleta extends Authenticatable
{
    // HasApiTokens: permite gerar e gerenciar tokens do Sanctum (login pela API)
    use HasApiTokens;

    protected $table = 'tbl_atletas';
    protected $primaryKey = 'id_atleta';
    public $timestamps = false;

    // Valores de tbl_categoria_atleta.status_categoria_atleta
    public const CATEGORIA_ATIVA     = 'ATIVO';
    public const CATEGORIA_ENCERRADA = 'ENCERRADO';

    // Status de quem já passou pela aprovação da matrícula; só entre eles o admin alterna.
    // PENDENTE e REJEITADO só mudam pela tela de Matrículas.
    public const STATUS_APROVADOS = ['ATIVO', 'INATIVO'];

    // Faixa atendida pela escolinha, contada pelo ano (idade = ano atual − ano de nascimento)
    public const IDADE_MINIMA = 9;
    public const IDADE_MAXIMA = 17;

    protected $fillable = [
        'nome_atleta',
        'data_nasc_atleta',
        'rg_atleta',
        'cpf_atleta',
        'numero_matricula_atleta',
        'posicao_atleta',
        'telefone_atleta',
        'peso_atleta',
        'altura_atleta',
        'sexo_atleta',
        'escola_atleta',
        'serie_atleta',
        'descricao_atleta',
        'foto_atleta',
        'sala_atleta',
        'periodo_escolar_atleta',
        'status_atleta',
        'token_cadastro',
        'id_endereco',
        'email_atleta',
        'password',
        'remember_token',
    ];

    // Nunca devolver esses campos no JSON da API
    protected $hidden = [
        'password',
        'remember_token',
        'token_cadastro',
    ];

    protected $casts = [
        'data_nasc_atleta' => 'date',
        'peso_atleta'      => 'decimal:2',
        'altura_atleta'    => 'decimal:2',
    ];

    public function endereco()
    {
        return $this->belongsTo(Endereco::class, 'id_endereco', 'id_endereco');
    }

    public function responsaveis()
    {
        return $this->belongsToMany(Responsavel::class, 'tbl_atleta_responsavel', 'id_atleta', 'id_responsavel')
            ->withPivot('grau_parentesco_responsavel');
    }

    // Define que um Atleta pertence a muitos Times
    public function times()
    {
        return $this->belongsToMany(Time::class, 'tbl_atleta_time', 'id_atleta', 'id_time')
            ->withPivot([
                'status_atleta_time',
                'posicao_atleta_time',
                'camisa_atleta_time',
                'gols_atleta_time',
                'defesas_atleta_time',
                'jogos_atleta_time',
                'convocacao_atleta_time'
            ]);
    }

    public function foiAprovado(): bool
    {
        return in_array(strtoupper((string) $this->status_atleta), self::STATUS_APROVADOS, true);
    }

    /**
     * Datas de nascimento aceitas no cadastro (site e admin): de 9 a 17 anos no ano,
     * ou seja, de 1º/jan de (ano − 17) até 31/dez de (ano − 9). Inclui a mensagem de erro.
     */
    public static function limitesNascimento(?int $ano = null): array
    {
        $ano        ??= (int) now()->format('Y');
        $anoMaisVelho = $ano - self::IDADE_MAXIMA;
        $anoMaisNovo  = $ano - self::IDADE_MINIMA;

        return [
            'min'      => "{$anoMaisVelho}-01-01",
            'max'      => "{$anoMaisNovo}-12-31",
            'mensagem' => 'O atleta deve ter de ' . self::IDADE_MINIMA . ' a ' . self::IDADE_MAXIMA
                . " anos em {$ano} (nascido entre {$anoMaisVelho} e {$anoMaisNovo}).",
        ];
    }

    // Regras de validação de data_nasc_atleta, com as mensagens, para os controllers
    public static function regrasNascimento(): array
    {
        $limites = self::limitesNascimento();

        return [
            'regra'     => "required|date|after_or_equal:{$limites['min']}|before_or_equal:{$limites['max']}",
            'mensagens' => [
                'data_nasc_atleta.after_or_equal'  => $limites['mensagem'],
                'data_nasc_atleta.before_or_equal' => $limites['mensagem'],
            ],
        ];
    }

    // Histórico completo de categorias (linhas ativas e encerradas)
    public function categorias()
    {
        return $this->belongsToMany(Categoria::class, 'tbl_categoria_atleta', 'id_atleta', 'id_categoria')
            ->withPivot([
                'data_inicio_categoria_atleta',
                'data_fim_categoria_atleta',
                'data_atualizacao_categoria_atleta',
                'status_categoria_atleta',
                'observacao_categoria_atleta',
            ]);
    }

    // Só a categoria atual (no máximo uma linha ATIVO)
    public function categoriasAtivas()
    {
        return $this->categorias()->wherePivot('status_categoria_atleta', self::CATEGORIA_ATIVA);
    }

    /**
     * Troca a categoria do atleta sem apagar histórico: encerra a linha ativa (data_fim + ENCERRADO)
     * e abre uma nova. Escolher a mesma categoria não faz nada; null só encerra a atual.
     * A validação (sexo, idade, motivo) fica com quem chama: Categoria::erroParaAtleta().
     */
    public function trocarCategoria(?int $idCategoria, ?string $observacao = null): void
    {
        $atual = DB::table('tbl_categoria_atleta')
            ->where('id_atleta', $this->id_atleta)
            ->where('status_categoria_atleta', self::CATEGORIA_ATIVA)
            ->value('id_categoria');

        if ($atual !== null && (int) $atual === $idCategoria) {
            return;
        }

        DB::transaction(function () use ($idCategoria, $observacao) {
            DB::table('tbl_categoria_atleta')
                ->where('id_atleta', $this->id_atleta)
                ->where('status_categoria_atleta', self::CATEGORIA_ATIVA)
                ->update([
                    'data_fim_categoria_atleta' => now(),
                    'status_categoria_atleta'   => self::CATEGORIA_ENCERRADA,
                ]);

            if ($idCategoria !== null) {
                DB::table('tbl_categoria_atleta')->insert([
                    'id_categoria'                 => $idCategoria,
                    'id_atleta'                    => $this->id_atleta,
                    'data_inicio_categoria_atleta' => now(),
                    'status_categoria_atleta'      => self::CATEGORIA_ATIVA,
                    'observacao_categoria_atleta'  => $observacao,
                ]);
            }
        });

        $this->unsetRelation('categorias')->unsetRelation('categoriasAtivas');
    }

    public function cartoes()
    {
        return $this->hasMany(Cartao::class, 'id_atleta', 'id_atleta');
    }

    public function autorizacoes()
    {
        return $this->hasMany(Autorizacao::class, 'id_atleta', 'id_atleta');
    }

    // Exclui o atleta e todos os vínculos dele (as FKs não têm ON DELETE CASCADE).
    // Atleta com cartões não é excluído, para não apagar o histórico dos jogos:
    // retorna false e quem chamou deve avisar o usuário.
    public function excluirComDependencias(): bool
    {
        if ($this->cartoes()->exists()) {
            return false;
        }

        DB::transaction(function () {
            $this->responsaveis()->detach();
            $this->categorias()->detach();
            $this->times()->detach();
            $this->autorizacoes()->delete();
            $this->tokens()->delete(); // tokens do Sanctum (personal_access_tokens não tem FK)
            $this->delete();
        });

        return true;
    }
}
