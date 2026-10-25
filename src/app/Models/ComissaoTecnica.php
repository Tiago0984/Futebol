<?php

namespace App\Models;

use App\Models\Concerns\UrlDeImagem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Profissional da comissão técnica (Etapa C1, 25/10/2026): o perfil que o app abre pelo responsável do compromisso.
 * Cadastro próprio, sem login (não é tbl_usuarios). Não é excluído, só desativado: fica nos eventos em que já está.
 * Foto no app só com a autorização marcada; contato só o institucional (da escolinha).
 */
class ComissaoTecnica extends Model
{
    use UrlDeImagem;

    protected $table      = 'tbl_comissao_tecnica';
    protected $primaryKey = 'id_comissao_tecnica';
    public    $timestamps = false;

    // Cargo gravado (chave, sem acento) => rótulo. Lista fixa do dono do projeto (25/10/2026); para ampliar, acrescente aqui
    public const CARGOS = [
        'TREINADOR_TECNICO'     => 'Treinador técnico',
        'AUXILIAR_TECNICO'      => 'Auxiliar técnico',
        'PREPARADOR_FISICO'     => 'Preparador físico',
        'PREPARADOR_GOLEIROS'   => 'Preparador de goleiros',
        'COORDENADOR'           => 'Coordenador',
        'FISIOTERAPEUTA'        => 'Fisioterapeuta',
        'NUTRICIONISTA'         => 'Nutricionista',
    ];

    // Pasta das fotos em public/futebol/images (disco "imagens"; fora do Git)
    public const PASTA_FOTO = 'comissao';

    // status fica fora: muda só pelo ativar/desativar; a foto, só pelo upload
    protected $fillable = [
        'nome_comissao_tecnica',
        'cargo_comissao_tecnica',
        'foto_autorizada_comissao_tecnica',
        'id_categoria',
        'data_inicio_comissao_tecnica',
        'licenca_comissao_tecnica',
        'especialidade_comissao_tecnica',
        'telefone_institucional_comissao_tecnica',
        'email_institucional_comissao_tecnica',
    ];

    protected $casts = [
        'data_inicio_comissao_tecnica'     => 'date',
        'foto_autorizada_comissao_tecnica' => 'boolean',
    ];

    // Categoria principal (também está em categorias())
    public function categoriaPrincipal()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    // Categorias atendidas, na ordem dos selects (masculino e depois feminino, por idade)
    public function categorias()
    {
        return $this->belongsToMany(Categoria::class, 'tbl_comissao_tecnica_categoria', 'id_comissao_tecnica', 'id_categoria')
            ->orderBy('sexo_categoria')->orderBy('idade_max_categoria');
    }

    public function scopeAtivos($query)
    {
        return $query->where('status_comissao_tecnica', 'ATIVO');
    }

    // ── Responsável pela atividade (Etapa C2) ───────────────────────────────

    // Valor do select para manter o texto livre de antes (24/10/2026) num evento ou linha da grade que ainda o tem
    public const TEXTO_ANTIGO = 'texto-antigo';

    // Ativos para o select "Responsável pela atividade", pelo nome; uma consulta por requisição (vários formulários na tela)
    public static function paraEscolher()
    {
        return once(fn () => self::ativos()->orderBy('nome_comissao_tecnica')->get());
    }

    /**
     * O que gravar a partir do select "Responsável pela atividade": [id_comissao_tecnica, texto antigo], ou a
     * mensagem de erro. Vazio = sem responsável; TEXTO_ANTIGO = mantém o texto que o registro já tem; um id = o
     * profissional (ativo, ou o inativo que já estava: continua onde estava, mas não é escolhido de novo). Escolher
     * da lista limpa o texto antigo.
     */
    public static function daEscolha(?string $valor, ?int $idAtual, ?string $textoAtual): array|string
    {
        if ($valor === null || $valor === '') {
            return [null, null];
        }

        if ($valor === self::TEXTO_ANTIGO) {
            return filled($textoAtual) ? [$idAtual, $textoAtual] : 'Escolha um profissional da lista.';
        }

        if (! ctype_digit($valor)) {
            return 'Escolha um profissional da lista.';
        }

        $id = (int) $valor;
        if ($id === $idAtual || self::ativos()->whereKey($id)->exists()) {
            return [$id, null];
        }

        return 'Escolha um profissional ativo da comissão técnica.';
    }

    public function estaAtivo(): bool
    {
        return $this->status_comissao_tecnica === 'ATIVO';
    }

    public function getCargoLabelAttribute(): string
    {
        return self::CARGOS[$this->cargo_comissao_tecnica] ?? (string) $this->cargo_comissao_tecnica;
    }

    // Foto no admin (sempre que houver); no app, só com a autorização (fotoParaOApp)
    public function urlFoto(): ?string
    {
        return self::urlDaImagem($this->foto_comissao_tecnica, self::PASTA_FOTO);
    }

    public function fotoParaOApp(): ?string
    {
        return $this->foto_autorizada_comissao_tecnica ? $this->urlFoto() : null;
    }

    // ── Perfil no app (Etapa C3) ────────────────────────────────────────────

    /**
     * O perfil que o app abre pelo responsável do compromisso (GET /comissao-tecnica/{id}). Foto só autorizada;
     * contato só o institucional (null sem telefone e e-mail). Quem chama já filtrou os ativos.
     */
    public function paraOApp(): array
    {
        $categoria = fn (Categoria $c) => [
            'id_categoria'   => $c->id_categoria,
            'nome_categoria' => $c->nome_categoria,
            'categoria'      => $c->rotulo,
        ];
        $telefone = $this->telefone_institucional_comissao_tecnica ?: null;
        $email = $this->email_institucional_comissao_tecnica ?: null;

        return [
            'id_comissao_tecnica'  => $this->id_comissao_tecnica,
            'nome'                 => $this->nome_comissao_tecnica,
            'cargo'                => $this->cargo_comissao_tecnica,
            'cargo_label'          => $this->cargo_label,
            'foto_url'             => $this->fotoParaOApp(),
            'categoria_principal'  => $this->categoriaPrincipal ? $categoria($this->categoriaPrincipal) : null,
            'categorias_atendidas' => $this->categorias->map($categoria)->values()->all(),
            'data_inicio'          => $this->data_inicio_comissao_tecnica?->format('Y-m-d'),
            'tempo_na_aacj'        => $this->tempoNaAacj(),
            'licenca'              => $this->licenca_comissao_tecnica ?: null,
            'especialidade'        => $this->especialidade_comissao_tecnica ?: null,
            'contato'              => ($telefone || $email) ? ['telefone' => $telefone, 'email' => $email] : null,
        ];
    }

    // Desde a data de início: { anos, meses, texto: "2 anos e 3 meses" }; null sem a data ou com a data no futuro
    public function tempoNaAacj(): ?array
    {
        $inicio = $this->data_inicio_comissao_tecnica;
        if (! $inicio || $inicio->isAfter(today())) {
            return null;
        }

        $diferenca = $inicio->diff(today());
        [$anos, $meses] = [$diferenca->y, $diferenca->m];
        $partes = array_filter([
            $anos ? $anos . ($anos === 1 ? ' ano' : ' anos') : null,
            $meses ? $meses . ($meses === 1 ? ' mês' : ' meses') : null,
        ]);

        return ['anos' => $anos, 'meses' => $meses, 'texto' => $partes ? implode(' e ', $partes) : 'Menos de 1 mês'];
    }

    /**
     * Grava as categorias atendidas; a principal entra sempre na lista (se o admin não marcou, o servidor acrescenta).
     *
     * @param int[] $ids
     */
    public function definirCategorias(array $ids): void
    {
        $ids = collect($ids)->push($this->id_categoria)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        DB::transaction(fn () => $this->categorias()->sync($ids));
    }
}
