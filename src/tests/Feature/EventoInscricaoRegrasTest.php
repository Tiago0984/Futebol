<?php

namespace Tests\Feature;

use App\Models\EventoCalendario;
use Illuminate\Support\Facades\DB;
use Tests\CriaDadosDeAtleta;
use Tests\RefreshBancoDeTestes;
use Tests\TestCase;

/**
 * O que acontece com as inscrições (Fase 5, Etapa 2): evento que muda de categoria, cancelado ou
 * oculto, atleta que muda de categoria ("Mover inscrições") e quem entra na categoria depois.
 */
class EventoInscricaoRegrasTest extends TestCase
{
    use RefreshBancoDeTestes, CriaDadosDeAtleta;

    // ---------- evento muda de categoria ----------

    public function test_evento_futuro_que_muda_de_categoria_sincroniza_so_as_automaticas(): void
    {
        $sub13Ana  = $this->atletaNaCategoria('Ana Sub13', 'Sub-13', 'M');
        $sub15Bia  = $this->atletaNaCategoria('Bia Sub15', 'Sub-15', 'M');
        $convidado = $this->atletaNaCategoria('Caio Convidado', 'Sub-17', 'M');

        $evento = $this->criarEvento(['id_categoria' => $this->idCategoria('Sub-13', 'M')]);
        $evento->inscrever($convidado, 'INDIVIDUAL', null);

        $this->comoAdmin()
            ->put(route('admin.calendario.eventos.update', $evento->id_evento_calendario), $this->dadosEdicaoEvento($evento, [
                'id_categoria' => $this->idCategoria('Sub-15', 'M'),
            ]))
            ->assertSessionHas('sucesso', 'Evento atualizado. Inscrições pela categoria: 1 atleta(s) inscrito(s), 1 removido(s). As inscrições individuais foram mantidas.');

        $this->assertInscritos($evento, [$sub15Bia => 'CATEGORIA', $convidado => 'INDIVIDUAL']);
        $this->assertDatabaseMissing('tbl_evento_atleta', ['id_atleta' => $sub13Ana]);
    }

    public function test_evento_que_fica_sem_categoria_perde_so_as_automaticas(): void
    {
        $this->atletaNaCategoria('Ana Sub13', 'Sub-13', 'M');
        $convidado = $this->atletaNaCategoria('Caio Convidado', 'Sub-17', 'M');
        $evento = $this->criarEvento(['id_categoria' => $this->idCategoria('Sub-13', 'M')]);
        $evento->inscrever($convidado, 'INDIVIDUAL', null);

        $this->comoAdmin()->put(route('admin.calendario.eventos.update', $evento->id_evento_calendario), $this->dadosEdicaoEvento($evento, ['id_categoria' => '']));

        $this->assertInscritos($evento, [$convidado => 'INDIVIDUAL']);
    }

    public function test_evento_concluido_que_muda_de_categoria_nao_muda_inscricoes(): void
    {
        $ana    = $this->atletaNaCategoria('Ana Sub13', 'Sub-13', 'M');
        $this->atletaNaCategoria('Bia Sub15', 'Sub-15', 'M');
        $evento = $this->criarEvento(['id_categoria' => $this->idCategoria('Sub-13', 'M')]);
        DB::table('tbl_evento_calendario')->where('id_evento_calendario', $evento->id_evento_calendario)
            ->update(['data_evento_calendario' => now()->subWeek()->toDateString()]);

        $this->comoAdmin()
            ->put(route('admin.calendario.eventos.update', $evento->id_evento_calendario), $this->dadosEdicaoEvento($evento->fresh(), [
                'id_categoria' => $this->idCategoria('Sub-15', 'M'),
            ]))
            ->assertSessionHas('sucesso', 'Evento atualizado.');

        $this->assertInscritos($evento, [$ana => 'CATEGORIA']);
    }

    public function test_editar_sem_mudar_a_categoria_nao_mexe_nas_inscricoes(): void
    {
        $ana    = $this->atletaNaCategoria('Ana Sub13', 'Sub-13', 'M');
        $evento = $this->criarEvento(['id_categoria' => $this->idCategoria('Sub-13', 'M')]);
        $this->atletaNaCategoria('Entrou Depois', 'Sub-13', 'M'); // não é inscrito por editar o título

        $this->comoAdmin()
            ->put(route('admin.calendario.eventos.update', $evento->id_evento_calendario), $this->dadosEdicaoEvento($evento, ['titulo_evento_calendario' => 'Novo Título']))
            ->assertSessionHas('sucesso', 'Evento atualizado.');

        $this->assertInscritos($evento, [$ana => 'CATEGORIA']);
    }

    // ---------- evento cancelado ou oculto ----------

    public function test_cancelar_e_ocultar_mantem_as_inscricoes(): void
    {
        $ana    = $this->atletaNaCategoria('Ana Sub13', 'Sub-13', 'M');
        $evento = $this->criarEvento(['id_categoria' => $this->idCategoria('Sub-13', 'M')]);

        $this->comoAdmin()->patch(route('admin.calendario.eventos.cancelar', $evento->id_evento_calendario));
        $this->assertInscritos($evento, [$ana => 'CATEGORIA']);

        $this->comoAdmin()->patch(route('admin.calendario.eventos.ocultar', $evento->id_evento_calendario));
        $this->assertInscritos($evento, [$ana => 'CATEGORIA']);
    }

    // ---------- quem entra na categoria depois ----------

    public function test_atualizar_inscritos_pela_categoria_so_acrescenta(): void
    {
        $evento    = $this->criarEvento(['id_categoria' => $this->idCategoria('Sub-13', 'M')]);
        $convidado = $this->atletaNaCategoria('Convidado', 'Sub-17', 'M');
        $evento->inscrever($convidado, 'INDIVIDUAL', null);
        $novo1 = $this->atletaNaCategoria('Novo Um', 'Sub-13', 'M');
        $novo2 = $this->atletaNaCategoria('Novo Dois', 'Sub-13', 'M');

        $this->comoAdmin()
            ->get(route('admin.calendario.eventos.show', $evento->id_evento_calendario))
            ->assertSee('<strong>2</strong> atleta(s) ativo(s) da categoria ainda não', false);

        $this->comoAdmin()
            ->post(route('admin.calendario.eventos.inscricoes.atualizar', $evento->id_evento_calendario))
            ->assertSessionHas('sucesso', '2 atleta(s) da categoria inscrito(s).');

        $this->assertInscritos($evento, [$convidado => 'INDIVIDUAL', $novo1 => 'CATEGORIA', $novo2 => 'CATEGORIA']);

        $this->comoAdmin()
            ->get(route('admin.calendario.eventos.show', $evento->id_evento_calendario))
            ->assertSee('Todos os atletas ativos da categoria estão inscritos.');
    }

    public function test_atualizar_inscritos_em_evento_sem_categoria_e_recusado(): void
    {
        $evento = $this->criarEvento();

        $this->comoAdmin()
            ->post(route('admin.calendario.eventos.inscricoes.atualizar', $evento->id_evento_calendario))
            ->assertSessionHas('erro', 'Este evento não tem categoria.');
    }

    // ---------- atleta muda de categoria ----------

    public function test_troca_de_categoria_avisa_e_mover_inscricoes_move_so_os_eventos_certos(): void
    {
        $idSub13 = $this->idCategoria('Sub-13', 'M');
        $idSub15 = $this->idCategoria('Sub-15', 'M');
        // 14 anos no ano: Sub-15 é a categoria certa; ainda está na Sub-13
        $atleta = $this->atletaNaCategoria('Davi', 'Sub-13', 'M', 14);

        $futuroSub13   = $this->criarEvento(['titulo_evento_calendario' => 'Treino Sub13', 'id_categoria' => $idSub13]);
        $individualSub13 = $this->criarEvento(['titulo_evento_calendario' => 'Amistoso Sub13', 'id_categoria' => $idSub13]);
        DB::table('tbl_evento_atleta')->where('id_evento_calendario', $individualSub13->id_evento_calendario)->update(['origem_evento_atleta' => 'INDIVIDUAL']);
        $canceladoSub13 = $this->criarEvento(['titulo_evento_calendario' => 'Cancelado Sub13', 'id_categoria' => $idSub13]);
        DB::table('tbl_evento_calendario')->where('id_evento_calendario', $canceladoSub13->id_evento_calendario)->update(['status_evento_calendario' => 'CANCELADO']);
        $passadoSub13 = $this->criarEvento(['titulo_evento_calendario' => 'Passado Sub13', 'id_categoria' => $idSub13]);
        DB::table('tbl_evento_calendario')->where('id_evento_calendario', $passadoSub13->id_evento_calendario)->update(['data_evento_calendario' => now()->subWeek()->toDateString()]);
        $futuroSub15  = $this->criarEvento(['titulo_evento_calendario' => 'Treino Sub15', 'id_categoria' => $idSub15]);

        // Edita o atleta trocando a categoria: aparece o aviso, nada muda ainda
        $this->comoAdmin()
            ->put(route('admin.atletas.update', $atleta), $this->dadosEdicao($atleta, ['id_categoria' => $idSub15]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('mover_inscricoes', fn ($aviso) => $aviso['de'] === $idSub13 && $aviso['para'] === $idSub15
                && count($aviso['sair']) === 1 && str_contains($aviso['sair'][0], 'Treino Sub13')
                && count($aviso['entrar']) === 1 && str_contains($aviso['entrar'][0], 'Treino Sub15'));

        $this->assertTrue($this->inscrito($futuroSub13, $atleta));
        $this->assertFalse($this->inscrito($futuroSub15, $atleta));

        // Confirma: sai do futuro automático da Sub-13, entra no da Sub-15; o resto fica
        $this->comoAdmin()
            ->post(route('admin.atletas.moverInscricoes', $atleta), ['de' => $idSub13, 'para' => $idSub15])
            ->assertSessionHas('sucesso', 'Inscrições de Davi movidas: saiu de 1 evento(s) e entrou em 1.');

        $this->assertFalse($this->inscrito($futuroSub13, $atleta));
        $this->assertTrue($this->inscrito($futuroSub15, $atleta));
        $this->assertTrue($this->inscrito($individualSub13, $atleta)); // individual: escolha do admin
        $this->assertTrue($this->inscrito($canceladoSub13, $atleta));  // cancelado: não mexe
        $this->assertTrue($this->inscrito($passadoSub13, $atleta));    // passado: não mexe
    }

    public function test_mover_inscricoes_recusa_aviso_velho(): void
    {
        $idSub13 = $this->idCategoria('Sub-13', 'M');
        $atleta  = $this->atletaNaCategoria('Davi', 'Sub-13', 'M', 14);

        // O aviso dizia "para Sub-15", mas o atleta continua na Sub-13
        $this->comoAdmin()
            ->post(route('admin.atletas.moverInscricoes', $atleta), ['de' => $idSub13, 'para' => $this->idCategoria('Sub-15', 'M')])
            ->assertSessionHas('erro');
    }

    public function test_sem_eventos_a_mover_nao_ha_aviso(): void
    {
        $atleta = $this->atletaNaCategoria('Davi', 'Sub-13', 'M', 14);

        $this->comoAdmin()
            ->put(route('admin.atletas.update', $atleta), $this->dadosEdicao($atleta, ['id_categoria' => $this->idCategoria('Sub-15', 'M')]))
            ->assertSessionMissing('mover_inscricoes');
    }

    // ---------- helpers ----------

    private function atletaNaCategoria(string $nome, string $categoria, string $sexo, ?int $idade = null): int
    {
        $idade ??= ['Sub-9' => 9, 'Sub-11' => 11, 'Sub-13' => 13, 'Sub-15' => 15, 'Sub-17' => 17][$categoria];
        $id      = $this->criarAtleta($this->nascidoComIdade($idade), $sexo, 'ATIVO');
        DB::table('tbl_atletas')->where('id_atleta', $id)->update(['nome_atleta' => $nome]);
        $this->colocarNaCategoria($id, $this->idCategoria($categoria, $sexo));

        return $id;
    }

    private function criarEvento(array $extra = []): EventoCalendario
    {
        return EventoCalendario::criarPor(null, array_merge([
            'titulo_evento_calendario'         => 'Evento de Teste',
            'tipo_evento_calendario'           => 'TREINO',
            'data_evento_calendario'           => now()->addWeek()->toDateString(),
            'horario_inicio_evento_calendario' => '09:00',
            'horario_fim_evento_calendario'    => '10:30',
            'local_evento_calendario'          => 'Campo A',
            'status_evento_calendario'         => 'ATIVO',
        ], $extra));
    }

    // Formulário de edição de evento (o de atleta é o dadosEdicao() do trait)
    private function dadosEdicaoEvento(EventoCalendario $alvo, array $extra = []): array
    {
        return array_merge([
            'titulo_evento_calendario'         => $alvo->titulo_evento_calendario,
            'tipo_evento_calendario'           => $alvo->tipo_evento_calendario,
            'id_categoria'                     => $alvo->id_categoria,
            'data_evento_calendario'           => $alvo->data_evento_calendario->toDateString(),
            'horario_inicio_evento_calendario' => substr((string) $alvo->horario_inicio_evento_calendario, 0, 5),
            'horario_fim_evento_calendario'    => substr((string) $alvo->horario_fim_evento_calendario, 0, 5),
            'local_evento_calendario'          => $alvo->local_evento_calendario,
        ], $extra);
    }

    private function inscrito(EventoCalendario $evento, int $idAtleta): bool
    {
        return DB::table('tbl_evento_atleta')
            ->where('id_evento_calendario', $evento->id_evento_calendario)
            ->where('id_atleta', $idAtleta)
            ->exists();
    }

    // Inscrições do evento: [id_atleta => origem]
    private function assertInscritos(EventoCalendario $evento, array $esperado): void
    {
        $atual = DB::table('tbl_evento_atleta')
            ->where('id_evento_calendario', $evento->id_evento_calendario)
            ->pluck('origem_evento_atleta', 'id_atleta')
            ->mapWithKeys(fn ($origem, $id) => [(int) $id => $origem])
            ->sortKeys()
            ->all();

        ksort($esperado);
        $this->assertSame($esperado, $atual);
    }
}
