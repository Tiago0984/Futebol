<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use App\Models\Jogo;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * Página inicial do painel (09/11/2026; App\Services\InicioDoPainel): "Precisa da sua atenção" só com o que
 * está pendente, números do dia, agenda dos próximos 7 dias, últimos resultados e o acesso rápido igual ao menu.
 */
class DashboardTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    public function test_sem_pendencias_mostra_tudo_em_dia_e_o_acesso_rapido_do_menu(): void
    {
        $resposta = $this->comoAdmin()->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Tudo em dia')
            ->assertDontSee('dash-atencao--', false)
            ->assertSee('Nenhuma atividade nos próximos 7 dias.')
            ->assertSee('Nenhum jogo realizado ainda.');

        // Os grupos e as telas do menu (antes faltavam Comissão técnica, Locais, Fale conosco, Notificações,
        // Amistosos e Individuais)
        $resposta->assertSeeInOrder(['Conteúdo do site', 'Esporte', 'Cadastros', 'Pessoas']);
        foreach ([
            route('admin.comissao.index'), route('admin.locais.index'), route('admin.fale-conosco.index'),
            route('admin.notificacoes.index'), EventoCalendario::urlDoRamo('amistosos'),
            EventoCalendario::urlDoRamo('individuais'), route('admin.calendario.index', ['tab' => 'grade']),
        ] as $url) {
            $resposta->assertSee('href="' . e($url) . '"', false);
        }
    }

    public function test_pendencias_de_pessoas_e_mensagens_levam_a_tela_filtrada(): void
    {
        $this->criarAtleta('2014-06-15', 'M', 'PENDENTE');
        $comFoto = $this->criarAtleta('2013-06-15', 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $comFoto)->update([
            'foto_pendente_atleta' => 'fotos-pendentes/foto-atleta-x.jpg', 'data_foto_pendente_atleta' => now(),
        ]);
        $semPeriodo = $this->criarAtleta('2012-06-15', 'M', 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $semPeriodo)->update(['periodo_escolar_atleta' => null]);
        // Inativo sem período não conta (não treina)
        $inativo = $this->criarAtleta('2012-06-15', 'M', 'INATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $inativo)->update(['periodo_escolar_atleta' => null]);

        $this->mensagem(lida: false, email: 'ENVIADO');
        $this->mensagem(lida: true, email: 'FALHOU');

        $this->comoAdmin()->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee('Tudo em dia')
            ->assertSee('1 matrícula(s) aguardando aprovação.')
            ->assertSee('1 foto(s) enviada(s) pelo app para aprovar.')
            ->assertSee('href="' . e(route('admin.atletas.index', ['status' => 'FOTO'])) . '"', false)
            ->assertSee('1 atleta(s) ativo(s) sem período escolar.')
            ->assertSee('href="' . e(route('admin.atletas.index', ['status' => 'SEM_PERIODO'])) . '"', false)
            ->assertSee('1 mensagem(ns) do Fale conosco não lida(s).')
            ->assertSee('href="' . e(route('admin.fale-conosco.index', ['leitura' => 'nao_lidas'])) . '"', false)
            ->assertSee('1 mensagem(ns) do Fale conosco sem o e-mail de aviso (falha no envio).');
    }

    public function test_jogos_em_rascunho_e_sem_placar_aparecem_com_o_link_de_cada_um(): void
    {
        [$casa, $fora] = $this->times();
        $rascunho  = $this->jogo($casa, $fora, now()->addDays(10)->toDateString(), rascunho: true);
        $semPlacar = $this->jogo($casa, $fora, now()->subDays(2)->toDateString());
        $comPlacar = $this->jogo($casa, $fora, now()->subDays(5)->toDateString(), placar: [2, 1]);
        // Oculto não conta em nada
        $this->jogo($casa, $fora, now()->subDays(1)->toDateString(), status: 'INATIVO');

        $resposta = $this->comoAdmin()->get(route('admin.dashboard'))->assertOk()
            ->assertSee('1 jogo(s) em rascunho, ainda sem aviso aos atletas.')
            ->assertSee('href="' . route('admin.calendario.eventos.show', $rascunho->id_evento) . '"', false)
            ->assertSee('1 jogo(s) já realizado(s) sem placar.')
            ->assertSee('href="' . route('admin.calendario.eventos.show', $semPlacar->id_evento) . '"', false);

        // Últimos resultados: do mais recente ao mais antigo, com o placar ou o selo "Sem placar"
        $resposta->assertSeeInOrder([
            'Últimos resultados', now()->subDays(2)->format('d/m/Y'), 'Sem placar', now()->subDays(5)->format('d/m/Y'), '2 × 1',
        ]);
        $this->assertNotNull($comPlacar);
    }

    public function test_fila_parada_e_tarefas_que_falharam(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => '{}', 'attempts' => 0,
            'available_at' => now()->subMinutes(30)->timestamp, 'created_at' => now()->subMinutes(30)->timestamp,
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => 'teste-dashboard', 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'Falha de teste',
        ]);

        $this->comoAdmin()->get(route('admin.dashboard'))->assertOk()
            ->assertSee('A fila de e-mails está parada há 30 minutos.')
            ->assertSee('1 tarefa(s) da fila falharam depois de todas as tentativas.');
    }

    public function test_fila_andando_nao_avisa(): void
    {
        // Tarefa recém-chegada: o agendador ainda vai pegar no próximo minuto
        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => '{}', 'attempts' => 0,
            'available_at' => now()->subMinute()->timestamp, 'created_at' => now()->subMinute()->timestamp,
        ]);

        $this->comoAdmin()->get(route('admin.dashboard'))->assertOk()
            ->assertDontSee('A fila de e-mails está parada')
            ->assertSee('Tudo em dia');
    }

    public function test_agenda_dos_proximos_dias_com_inscritos_ativos_e_os_numeros(): void
    {
        $amanha = $this->evento('Treino de amanhã', now()->addDay()->toDateString());
        $this->evento('Reunião cancelada', now()->addDays(2)->toDateString(), status: 'CANCELADO');
        $this->evento('Treino oculto', now()->addDays(3)->toDateString(), status: 'INATIVO');
        $this->evento('Treino de ontem', now()->subDay()->toDateString());
        $this->evento('Treino daqui a 10 dias', now()->addDays(10)->toDateString());

        $ativo   = $this->criarAtleta('2013-06-15', 'M', 'ATIVO');
        $inativo = $this->criarAtleta('2013-06-15', 'M', 'INATIVO');
        foreach ([$ativo, $inativo] as $idAtleta) {
            DB::table('tbl_evento_atleta')->insert([
                'id_evento_calendario' => $amanha->id_evento_calendario, 'id_atleta' => $idAtleta, 'origem_evento_atleta' => 'INDIVIDUAL',
            ]);
        }
        DB::table('tbl_atleta_afastamento')->insert([
            'id_atleta' => $ativo, 'data_inicio_atleta_afastamento' => now()->subDay()->toDateString(),
            'data_fim_atleta_afastamento' => now()->addDays(5)->toDateString(),
        ]);

        $resposta = $this->comoAdmin()->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Treino de amanhã')
            ->assertSee('Reunião cancelada')
            ->assertDontSee('Treino oculto')
            ->assertDontSee('Treino de ontem')
            ->assertDontSee('Treino daqui a 10 dias')
            ->assertSee('1 afastado(s) hoje');

        // Inscritos: só o ativo conta
        $resposta->assertSeeInOrder(['Treino de amanhã', '<i class="bi bi-people"></i> 1'], false);
        // Atividades nos próximos 7 dias: só a ativa (a cancelada aparece na agenda, mas não conta)
        $resposta->assertSeeInOrder(['<div class="kpi-number">1</div>', 'Atividades nos próximos 7 dias'], false);
    }

    // ---------- ajudantes ----------

    private function mensagem(bool $lida, string $email): void
    {
        DB::table('tbl_fale_conosco')->insert([
            'origem_fale_conosco' => 'SITE', 'perfil_fale_conosco' => 'VISITANTE',
            'nome_fale_conosco' => 'Visitante de Teste', 'email_fale_conosco' => 'visitante@exemplo.com',
            'assunto_fale_conosco' => 'Dúvida', 'mensagem_fale_conosco' => 'Mensagem de teste.',
            'data_fale_conosco' => now(), 'data_leitura_fale_conosco' => $lida ? now() : null,
            'status_email_fale_conosco' => $email,
        ]);
    }

    private function times(): array
    {
        $idCategoria = $this->idCategoria('Sub-13', 'M');
        $time = fn (string $nome, string $tipo) => DB::table('tbl_time')->insertGetId([
            'id_categoria' => $idCategoria, 'logo_time' => 'time.png', 'nome_time' => $nome, 'tipo_time' => $tipo,
        ]);

        return [$time('AACJ Masculino', 'INTERNO'), $time('Botafogo', 'EXTERNO')];
    }

    private function jogo(int $casa, int $fora, string $data, bool $rascunho = false, ?array $placar = null, string $status = 'ATIVO'): Jogo
    {
        $evento = EventoCalendario::criarPor(null, rascunho: $rascunho, dados: [
            'titulo_evento_calendario'         => Jogo::tituloPara($casa, $fora),
            'tipo_evento_calendario'           => 'JOGO',
            'data_evento_calendario'           => $data,
            'horario_inicio_evento_calendario' => '14:00:00',
            'status_evento_calendario'         => $status,
        ]);

        return Jogo::create([
            'id_evento' => $evento->id_evento_calendario, 'id_campeonato' => null,
            'id_time_casa' => $casa, 'id_time_visitante' => $fora,
            'placar_time_casa_jogos' => $placar[0] ?? null, 'placar_time_visitante_jogos' => $placar[1] ?? null,
        ]);
    }

    private function evento(string $titulo, string $data, string $status = 'ATIVO'): EventoCalendario
    {
        return EventoCalendario::criarPor(null, dados: [
            'titulo_evento_calendario'         => $titulo,
            'tipo_evento_calendario'           => 'TREINO',
            'data_evento_calendario'           => $data,
            'horario_inicio_evento_calendario' => '14:00:00',
            'status_evento_calendario'         => $status,
        ]);
    }
}
