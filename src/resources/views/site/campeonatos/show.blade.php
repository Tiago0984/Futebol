@extends('layout.site')

@section('content')

@include('site.campeonatos.banner')

<div class="section singlepage">
    <div class="container">

        {{-- Cabeçalho do campeonato --}}
        <div class="row text-center" style="padding: 50px 0 30px 0;">
            <div class="col-sm-12">
                <h1 style="font-size: 36px; font-weight: bold; color: #000;">{{ $campeonato->nome_campeonato }}</h1>
                <div class="border-style"></div>
                <p style="font-size: 16px; color: #888; margin-top: 10px; display: flex;align-items: center;justify-content: center;">
                    <span style="margin-right: 20px;">{{ $campeonato->tipo_campeonato }}</span>
                    <i class="fa fa-map-marker" style="color:#f00;"></i>
                    <span style="margin: 0 20px 0 8px;">{{ $campeonato->local_evento }}</span>
                    <i class="fa fa-calendar" style="color:#f00;"></i>
                    <span style="margin-left: 8px;">
                        {{ \Carbon\Carbon::parse($campeonato->data_inicio_campeonato)->format('d/m/Y') }}
                        até
                        {{ \Carbon\Carbon::parse($campeonato->data_fim_campeonato)->format('d/m/Y') }}
                    </span><br><br><br><br>
                </p>
            </div>
        </div>

        {{-- Logo + Times lado a lado --}}
        <div class="row" style="margin-bottom: 40px; align-items: center; display: flex; justify-content: center;">
            <div class="col-sm-2 text-center">
                <img src="{{ asset('futebol/images/campeonatos/' . $campeonato->logo_evento) }}"
                    alt="{{ $campeonato->nome_campeonato }}"
                    style="max-width: 100%;">
            </div>
            <div class="col-sm-10">
                <h3 class="lead" style="margin-bottom: 20px; text-align: center;">TIMES PARTICIPANTES</h3><br>
                <div class="row" style="display:flex; justify-content: center">
                    @foreach ($campeonato->times as $time)
                    <div class="col-xs-4 col-sm-2 text-center" style="margin-bottom: 20px;">
                        <img src="{{ asset('futebol/images/team/' . $time->logo_time) }}"
                            alt="{{ $time->nome_time }}"
                            style="width: 70px; height: 70px; object-fit: contain;">
                        <p style="margin-top: 8px; font-weight: bold; font-size: 13px;">{{ $time->nome_time }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Tabela de Classificação --}}
        <div class="row">
            <div class="col-sm-12">
                <h3 class="lead">CLASSIFICAÇÃO</h3>
                <div class="border-style"></div>
                <table class="table table-striped table-bordered" style="margin-top:20px;">
                    <thead style="background:#c0392b; color:#fff;">
                        <tr>
                            <th>#</th>
                            <th>Time</th>
                            <th>Pts</th>
                            <th>PJ</th>
                            <th>V</th>
                            <th>E</th>
                            <th>D</th>
                            <th>GM</th>
                            <th>GC</th>
                            <th>SG</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($classificacao as $pos => $time)
                        <tr>
                            <td>{{ $pos + 1 }}</td>
                            <td>{{ $time['nome'] }}</td>
                            <td><strong>{{ $time['pontos'] }}</strong></td>
                            <td>{{ $time['pj'] }}</td>
                            <td>{{ $time['v'] }}</td>
                            <td>{{ $time['e'] }}</td>
                            <td>{{ $time['d'] }}</td>
                            <td>{{ $time['gm'] }}</td>
                            <td>{{ $time['gc'] }}</td>
                            <td>{{ $time['gm'] - $time['gc'] }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center">Nenhum jogo cadastrado.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

@endsection