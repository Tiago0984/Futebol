<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Autorização de Matrícula — Escolinha de Futebol</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f2f2f2; color: #333; }

        .header {
            background: #c00;
            color: #fff;
            text-align: center;
            padding: 20px 15px;
        }
        .header h1 { font-size: 20px; letter-spacing: 1px; }
        .header p  { font-size: 13px; margin-top: 5px; opacity: .85; }

        .card {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,.1);
        }
        .card h2 {
            font-size: 15px;
            color: #c00;
            border-bottom: 2px solid #c00;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        .info-row span:first-child { color: #888; }
        .info-row span:last-child  { font-weight: bold; }

        .assinatura-box { margin: 15px; }
        .assinatura-box h2 {
            font-size: 15px;
            color: #c00;
            margin-bottom: 10px;
        }
        .assinatura-box p {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
        }

        #canvas-assinatura {
            width: 100%;
            height: 200px;
            border: 2px dashed #c00;
            border-radius: 8px;
            background: #fff;
            touch-action: none;
            cursor: crosshair;
            display: block;
        }

        .btn-limpar {
            background: #888;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 14px;
            margin-top: 10px;
            cursor: pointer;
            width: 100%;
        }
        .btn-assinar {
            background: #c00;
            color: #fff;
            border: none;
            padding: 15px;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
            cursor: pointer;
            width: 100%;
            letter-spacing: 1px;
        }
        .btn-assinar:disabled { background: #aaa; cursor: not-allowed; }

        .aviso {
            background: #fff8e1;
            border-left: 4px solid #f59c00;
            padding: 12px 15px;
            margin: 15px;
            border-radius: 4px;
            font-size: 13px;
            color: #7a5700;
        }

        .sucesso {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 20px 15px;
            margin: 15px;
            border-radius: 4px;
            text-align: center;
            color: #155724;
        }
        .sucesso h2 { font-size: 18px; margin-bottom: 8px; }
        .sucesso p  { font-size: 14px; }

        .ja-assinado {
            background: #e8f4fd;
            border-left: 4px solid #17a2b8;
            padding: 20px 15px;
            margin: 15px;
            border-radius: 4px;
            text-align: center;
            color: #0c5460;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>ESCOLINHA DE FUTEBOL</h1>
    <p>Autorização de Matrícula</p>
</div>

@if(session('assinado'))
    <div class="sucesso">
        <h2>✅ Assinatura Registrada!</h2>
        <p>Obrigado, <strong>{{ session('nome_responsavel') }}</strong>.<br>
        O cadastro de <strong>{{ session('nome_atleta') }}</strong> foi enviado para análise.<br>
        Em breve o administrador irá aprová-lo.</p>
    </div>

@elseif($autorizacao->status_autorizacao === 'ASSINADO')
    <div class="ja-assinado">
        <h2>ℹ️ Autorização já assinada</h2>
        <p>Esta autorização já foi registrada anteriormente. Aguarde a aprovação do administrador.</p>
    </div>

@else
    {{-- DADOS DO ATLETA --}}
    <div class="card">
        <h2>👦 Dados do Atleta</h2>
        <div class="info-row">
            <span>Nome</span>
            <span>{{ $atleta->nome_atleta }}</span>
        </div>
        <div class="info-row">
            <span>Data de Nasc.</span>
            <span>{{ \Carbon\Carbon::parse($atleta->data_nasc_atleta)->format('d/m/Y') }}</span>
        </div>
        <div class="info-row">
            <span>CPF</span>
            <span>{{ $atleta->cpf_atleta }}</span>
        </div>
        <div class="info-row">
            <span>Escola</span>
            <span>{{ $atleta->escola_atleta }}</span>
        </div>
        <div class="info-row">
            <span>Série / Período</span>
            <span>{{ $atleta->serie_atleta }} — {{ $atleta->periodo_escolar_atleta }}</span>
        </div>
    </div>

    {{-- DADOS DO RESPONSÁVEL --}}
    <div class="card">
        <h2>👤 Responsável</h2>
        <div class="info-row">
            <span>Nome</span>
            <span>{{ $responsavel->nome_responsavel }}</span>
        </div>
        <div class="info-row">
            <span>CPF</span>
            <span>{{ $responsavel->cpf_responsavel }}</span>
        </div>
        <div class="info-row">
            <span>Parentesco</span>
            <span>{{ $grauParentesco }}</span>
        </div>
    </div>

    {{-- AVISO LEGAL --}}
    <div class="aviso">
        Ao assinar abaixo, você autoriza a matrícula do atleta na Escolinha de Futebol e confirma que as informações acima são verdadeiras.
    </div>

    {{-- CANVAS DE ASSINATURA --}}
    <div class="assinatura-box">
        <h2>✍️ Assinatura do Responsável</h2>
        <p>Assine com o dedo no espaço abaixo:</p>

        <canvas id="canvas-assinatura"></canvas>

        <button class="btn-limpar" onclick="limparAssinatura()">Limpar Assinatura</button>

        <form id="form-assinatura" action="{{ route('assinar.store', $autorizacao->token_assinatura) }}" method="POST">
            @csrf
            <input type="hidden" name="assinatura_img" id="assinatura_img">
            <button type="submit" class="btn-assinar" id="btn-assinar" disabled>
                CONFIRMAR E ASSINAR
            </button>
        </form>
    </div>
@endif

<script>
const canvas  = document.getElementById('canvas-assinatura');
if (canvas) {
    // Ajusta resolução real do canvas ao tamanho CSS
    const rect = canvas.getBoundingClientRect();
    canvas.width  = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;

    const ctx     = canvas.getContext('2d');
    let desenhando = false;

    function getPosicao(e) {
        const r = canvas.getBoundingClientRect();
        if (e.touches) {
            return { x: e.touches[0].clientX - r.left, y: e.touches[0].clientY - r.top };
        }
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    }

    function iniciar(e) {
        e.preventDefault();
        desenhando = true;
        const p = getPosicao(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    }

    function desenhar(e) {
        e.preventDefault();
        if (!desenhando) return;
        const p = getPosicao(e);
        ctx.lineWidth   = 2.5;
        ctx.lineCap     = 'round';
        ctx.strokeStyle = '#000';
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        // Habilita botão assim que houver assinatura
        document.getElementById('btn-assinar').disabled = false;
    }

    function parar(e) { desenhando = false; }

    canvas.addEventListener('mousedown',  iniciar);
    canvas.addEventListener('mousemove',  desenhar);
    canvas.addEventListener('mouseup',    parar);
    canvas.addEventListener('touchstart', iniciar,  { passive: false });
    canvas.addEventListener('touchmove',  desenhar, { passive: false });
    canvas.addEventListener('touchend',   parar);

    window.limparAssinatura = function() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        document.getElementById('btn-assinar').disabled = true;
    };

    // Antes de enviar, converte o canvas para imagem base64
    document.getElementById('form-assinatura').addEventListener('submit', function(e) {
        document.getElementById('assinatura_img').value = canvas.toDataURL('image/png');
    });
}
</script>

</body>
</html>
