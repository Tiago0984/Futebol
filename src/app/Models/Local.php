<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Local de treino, jogo ou evento (Etapa L1, 30/10/2026): nome, endereço e o link do Google Maps opcional, para o
 * "Abrir mapa" do app achar o lugar certo (o texto livre "Quadra A" não acha nada). Ativo/inativo, sem exclusão,
 * como a comissão técnica: o desativado sai das novas escolhas e continua nos eventos em que já está (L2).
 */
class Local extends Model
{
    protected $table = 'tbl_local';
    protected $primaryKey = 'id_local';
    public $timestamps = false;

    protected $fillable = [
        'nome_local',
        'rua_local',
        'numero_local',
        'complemento_local',
        'bairro_local',
        'cidade_local',
        'estado_local',
        'cep_local',
        'mapa_url_local',
    ];

    // Endereços aceitos no link do mapa (o link abre no celular dos atletas, que são menores): só o Google Maps,
    // inclusive o link curto do "Compartilhar". [host => começo do caminho exigido]
    private const HOSTS_DO_MAPA = [
        'maps.app.goo.gl'   => '/',
        'goo.gl'            => '/maps',
        'maps.google.com'   => '/',
        'maps.google.com.br' => '/',
        'google.com'        => '/maps',
        'www.google.com'    => '/maps',
        'google.com.br'     => '/maps',
        'www.google.com.br' => '/maps',
    ];

    public function scopeAtivos($query)
    {
        return $query->where('status_local', 'ATIVO');
    }

    public function estaAtivo(): bool
    {
        return $this->status_local === 'ATIVO';
    }

    // CEP gravado como 00000-000 (vazio = null), UF em maiúsculas
    public function setCepLocalAttribute($valor): void
    {
        $digitos = preg_replace('/\D/', '', (string) $valor);
        $this->attributes['cep_local'] = $digitos === '' ? null : substr($digitos, 0, 5) . '-' . substr($digitos, 5);
    }

    public function setEstadoLocalAttribute($valor): void
    {
        $this->attributes['estado_local'] = mb_strtoupper(trim((string) $valor));
    }

    // "Rua X, 123, Portão 2 – Centro, São Carlos/SP, 13560-000" (sem CEP, termina na UF)
    public function enderecoEmUmaLinha(): string
    {
        $linha = Endereco::texto([
            'rua_endereco'         => $this->rua_local,
            'numero_endereco'      => $this->numero_local,
            'complemento_endereco' => $this->complemento_local,
            'bairro_endereco'      => $this->bairro_local,
            'cidade_endereco'      => $this->cidade_local,
            'estado_endereco'      => $this->estado_local,
            'cep_endereco'         => $this->cep_local,
        ]);

        return rtrim($linha, ', ');
    }

    // O link cadastrado ou, sem ele, a busca do Google Maps com o nome e o endereço (o nome ajuda a achar o lugar)
    public function mapaUrl(): string
    {
        return $this->mapa_url_local
            ?: 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($this->nome_local . ', ' . $this->enderecoEmUmaLinha());
    }

    // Link https do Google Maps (sem usuário e senha no endereço, que esconderiam outro site)
    public static function linkDoGoogleMaps(?string $url): bool
    {
        $partes = parse_url(trim((string) $url));
        if (! $partes || ($partes['scheme'] ?? '') !== 'https' || isset($partes['user']) || isset($partes['pass']) || isset($partes['port'])) {
            return false;
        }

        $inicio = self::HOSTS_DO_MAPA[mb_strtolower($partes['host'] ?? '')] ?? null;
        $caminho = $partes['path'] ?? '/';

        return $inicio !== null && str_starts_with($caminho, $inicio) && ($inicio !== '/' || strlen($caminho) > 1 || isset($partes['query']));
    }

    /**
     * Textos de local já usados nos eventos, na grade e no local principal dos campeonatos que ainda não são o nome
     * de um local cadastrado (sem diferenciar maiúsculas e acentos, pela collation), com quantas vezes aparecem:
     * a lista de Locais oferece "Cadastrar" com o nome preenchido. Nada é ligado sozinho (a escolha é da L2).
     *
     * @return Collection<int, object{texto: string, usos: int}>
     */
    public static function textosSemCadastro(): Collection
    {
        $usados = DB::query()->fromSub(
            DB::table('tbl_evento_calendario')->selectRaw('TRIM(local_evento_calendario) AS texto')
                ->unionAll(DB::table('tbl_grade_treino')->selectRaw('TRIM(local_grade_treino)'))
                ->unionAll(DB::table('tbl_campeonato')->selectRaw('TRIM(local_evento)')),
            'usados'
        );

        return $usados->selectRaw('MIN(texto) AS texto, COUNT(*) AS usos')
            ->whereNotNull('texto')->where('texto', '<>', '')
            ->whereNotExists(fn ($q) => $q->from('tbl_local')->whereColumn('tbl_local.nome_local', 'usados.texto'))
            ->groupBy('texto')
            ->orderByDesc('usos')->orderBy('texto')
            ->get()
            ->map(fn ($linha) => (object) ['texto' => $linha->texto, 'usos' => (int) $linha->usos]);
    }
}
