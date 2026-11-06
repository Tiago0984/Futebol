{{-- Tabela "Todas as rotas", montada das rotas documentadas ($endpoints); o opcional leva "?" no fim do nome --}}
<div class="table-wrap">
    <table class="table tabela-rotas">
        <thead><tr><th>Método</th><th>Rota</th><th>Acesso</th><th>Parâmetros</th></tr></thead>
        <tbody>
            @foreach ($endpoints as $rota)
                <tr>
                    <td class="rota-metodos">
                        @foreach ($rota['metodos'] as $metodo)
                            <span class="method {{ strtolower($metodo) }}">{{ $metodo }}</span>
                        @endforeach
                    </td>
                    <td><a href="#{{ $rota['id'] }}"><code>/api/v1{{ $rota['rota'] }}</code></a></td>
                    <td>{{ \App\Support\DocumentacaoApi::ACESSOS[$rota['acesso']]['rotulo'] }}</td>
                    <td class="rota-parametros">
                        {{ collect($rota['parametros'] ?? [])->map(fn ($parametro) => $parametro[0] . ($parametro[3] ? '' : '?'))->implode(', ') ?: '—' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
