<?php

namespace Database\Seeders;

use App\Models\Atleta;
use App\Models\Categoria;
use App\Models\Endereco;
use App\Models\Responsavel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Contas fictícias dos exemplos de /api/documentacao, para testar o login do app nos dois perfis:
 * a atleta Joana Exemplo (joana@exemplo.com, Sub-13 Feminino, ativa) e a mãe dela, Carla Exemplo (carla@exemplo.com).
 *
 * Fora do DatabaseSeeder de propósito (a entrega começa do zero):
 *   php artisan db:seed --class=ContaDeTesteApiSeeder
 *
 * Cria só o que falta (procura pelos e-mails) e, a cada vez, grava uma senha nova aleatória para as duas contas,
 * mostrada só no terminal (não fica no repositório). Rodar de novo serve para trocar a senha esquecida.
 */
class ContaDeTesteApiSeeder extends Seeder
{
    private const EMAIL_ATLETA = 'joana@exemplo.com';

    private const EMAIL_RESPONSAVEL = 'carla@exemplo.com';

    public function run(): void
    {
        $senha = Str::password(12, symbols: false);

        [$atleta, $responsavel] = DB::transaction(function () use ($senha) {
            $responsavel = Responsavel::porEmail(self::EMAIL_RESPONSAVEL) ?? $this->criarResponsavel();
            $atleta = Atleta::porEmail(self::EMAIL_ATLETA) ?? $this->criarAtleta($responsavel);

            if ($atleta->status_atleta !== 'ATIVO') {
                throw new \RuntimeException('A atleta ' . self::EMAIL_ATLETA . ' existe, mas não está ATIVO: ative pelo admin antes.');
            }

            $atleta->definirSenha($senha);
            $responsavel->definirSenha($senha);

            return [$atleta, $responsavel];
        });

        $this->command?->info("Atleta {$atleta->nome_atleta} (id {$atleta->id_atleta}): " . self::EMAIL_ATLETA);
        $this->command?->info("Responsável {$responsavel->nome_responsavel} (id {$responsavel->id_responsavel}): " . self::EMAIL_RESPONSAVEL);
        $this->command?->warn("Senha das duas contas: {$senha}");
    }

    private function criarResponsavel(): Responsavel
    {
        return Responsavel::create([
            'nome_responsavel'       => 'Carla Exemplo',
            'cpf_responsavel'        => '529.982.247-25',
            'rg_responsavel'         => '',
            'telefone_responsavel'   => '(11) 98888-0000',
            'whatsapp_responsavel'   => '(11) 98888-0000',
            'email_responsavel'      => self::EMAIL_RESPONSAVEL,
            'assinatura_responsavel' => '',
            'aceite_responsavel'     => 'N',
            'id_endereco'            => $this->criarEndereco()->id_endereco,
        ]);
    }

    // O mesmo caminho do cadastro pelo admin (como no DemonstracaoSeeder): atleta, posição, vínculo, categoria e matrícula
    private function criarAtleta(Responsavel $responsavel): Atleta
    {
        $atleta = Atleta::create([
            'nome_atleta'            => 'Joana Exemplo',
            'data_nasc_atleta'       => Carbon::create(now()->year - 12, 3, 15)->toDateString(),
            'cpf_atleta'             => '111.444.777-35',
            'rg_atleta'              => '',
            'sexo_atleta'            => 'F',
            'escola_atleta'          => 'E.E. Exemplo',
            'serie_atleta'           => '7º ano',
            'periodo_escolar_atleta' => 'MANHA',
            'peso_atleta'            => 40,
            'altura_atleta'          => 1.50,
            'foto_atleta'            => 'default-player.jpg',
            'descricao_atleta'       => '',
            'email_atleta'           => self::EMAIL_ATLETA,
            'status_atleta'          => 'ATIVO',
            'id_endereco'            => $this->criarEndereco()->id_endereco,
        ]);

        $atleta->definirPosicoes(['MEIA_CENTRAL']);
        $atleta->responsaveis()->attach($responsavel->id_responsavel, ['grau_parentesco_responsavel' => 'Mãe']);
        $atleta->trocarCategoria(Categoria::sugeridaPara($atleta->data_nasc_atleta, 'F')->id_categoria);
        $atleta->atribuirNumeroMatricula();

        return $atleta;
    }

    private function criarEndereco(): Endereco
    {
        return Endereco::create([
            'rua_endereco'    => 'Rua Exemplo',
            'numero_endereco' => '100',
            'bairro_endereco' => 'Jardim Juscelino',
            'cep_endereco'    => '08000-000',
            'cidade_endereco' => 'São Paulo',
            'estado_endereco' => 'SP',
        ]);
    }
}
