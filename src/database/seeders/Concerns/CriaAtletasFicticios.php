<?php

namespace Database\Seeders\Concerns;

use App\Models\Endereco;
use App\Models\Responsavel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Dados fictícios de atleta, endereço e responsável para os seeders (DemonstracaoSeeder e EscolinhaSeeder).
 * O $i de cada atleta deixa CPF, RG, endereço e telefone diferentes entre si.
 */
trait CriaAtletasFicticios
{
    private const RUAS = ['Rua das Palmeiras', 'Rua Jacarandá', 'Avenida dos Ipês', 'Rua Sabiá', 'Rua Bem-te-vi',
        'Rua das Acácias', 'Travessa do Campo', 'Rua Manacá'];

    private const BAIRROS = ['Jardim Juscelino', 'Vila Esperança', 'Parque das Flores', 'Jardim Primavera'];

    private const ESCOLAS = ['E.E. Professora Maria Aparecida', 'E.M. Monteiro Lobato', 'Colégio Novo Horizonte',
        'E.E. Dom Pedro II'];

    private function dadosDoAtleta(string $nome, int $idade, string $sexo, string $periodo, int $i): array
    {
        // Nascimento pela idade no ano (regra das categorias): ano atual − idade, num dia qualquer do ano
        $nascimento = Carbon::create(now()->year - $idade, 1 + ($i * 5) % 12, 1 + ($i * 7) % 28);

        return [
            'nome_atleta'            => $nome,
            'data_nasc_atleta'       => $nascimento->toDateString(),
            'cpf_atleta'             => $this->cpf(1000 + $i),
            'rg_atleta'              => sprintf('%02d.%03d.%03d-%d', 30 + $i, 100 + $i * 13, 200 + $i * 7, $i % 10),
            'sexo_atleta'            => $sexo,
            'escola_atleta'          => self::ESCOLAS[$i % count(self::ESCOLAS)],
            'serie_atleta'           => max(1, min(9, $idade - 5)) . 'º ano',
            'periodo_escolar_atleta' => $periodo,
            'peso_atleta'            => 25 + ($idade - 9) * 5 + $i % 4,
            'altura_atleta'          => round(1.30 + ($idade - 9) * 0.05 + ($i % 3) * 0.02, 2),
            'foto_atleta'            => 'default-player.jpg',
            'descricao_atleta'       => '',
        ];
    }

    private function criarEndereco(int $i): Endereco
    {
        return Endereco::create([
            'rua_endereco'    => self::RUAS[$i % count(self::RUAS)],
            'numero_endereco' => (string) (100 + $i * 17),
            'bairro_endereco' => self::BAIRROS[$i % count(self::BAIRROS)],
            'cep_endereco'    => sprintf('08%03d-%03d', 100 + $i, $i * 11 % 1000),
            'cidade_endereco' => 'São Paulo',
            'estado_endereco' => 'SP',
        ]);
    }

    private function criarResponsavel(string $nome, Endereco $endereco, int $i): Responsavel
    {
        $telefone = sprintf('(11) 9%04d-%04d', 8100 + $i, 2000 + $i * 37);

        return Responsavel::create([
            'nome_responsavel'       => $nome,
            'cpf_responsavel'        => $this->cpf(5000 + $i),
            'rg_responsavel'         => '',
            'telefone_responsavel'   => $telefone,
            'whatsapp_responsavel'   => $telefone,
            'email_responsavel'      => $this->email($nome),
            'assinatura_responsavel' => '',
            'aceite_responsavel'     => 'N',
            'id_endereco'            => $endereco->id_endereco,
        ]);
    }

    // "Theo Barbosa" => theo.barbosa@exemplo.com (domínio reservado para exemplos: nenhum e-mail sai de verdade)
    private function email(string $nome): string
    {
        return Str::slug($nome, '.') . '@exemplo.com';
    }

    // CPF fictício com os dígitos verificadores válidos, formatado (000.000.000-00), diferente para cada $semente
    private function cpf(int $semente): string
    {
        $base = str_split(sprintf('%09d', 123456789 + $semente * 7919));
        foreach ([10, 11] as $peso) {
            $soma = 0;
            foreach ($base as $k => $digito) {
                $soma += (int) $digito * ($peso - $k);
            }
            $base[] = (string) (($soma * 10) % 11 % 10);
        }
        $d = implode('', $base);

        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }
}
