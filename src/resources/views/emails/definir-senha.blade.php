<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $convite ? 'Defina sua senha' : 'Nova senha' }}</title>
</head>
<body style="margin:0; padding:0; background:#f5f5f5; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background:#ffffff; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="background:#e31c1c; padding:24px; text-align:center;">
                            <img src="{{ asset('futebol/images/logo2.png') }}" alt="AACJ Futebol" style="height:48px;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 28px;">
                            <h1 style="margin:0 0 16px; font-size:18px; color:#1e293b;">Olá, {{ $nome }}!</h1>
                            <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#374151;">
                                @if($convite)
                                    Seu acesso ao app da Escolinha de Futebol AACJ, no perfil <strong>{{ $rotuloPerfil }}</strong>, está liberado.
                                    Para entrar, defina a sua senha clicando no botão abaixo.
                                @else
                                    Recebemos um pedido para trocar a senha do app da Escolinha de Futebol AACJ, no perfil <strong>{{ $rotuloPerfil }}</strong>.
                                    Para criar uma senha nova, clique no botão abaixo. Se não foi você, ignore este e-mail: a senha atual continua valendo.
                                @endif
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                                <tr>
                                    <td style="border-radius:6px; background:#e31c1c;">
                                        <a href="{{ $link }}" target="_blank"
                                           style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none;">
                                            Definir minha senha
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 16px; font-size:13px; line-height:1.6; color:#374151;">
                                O link vale por <strong>{{ $horas }} horas</strong>. Se ele vencer, abra o app e use
                                <strong>"Esqueci minha senha"</strong> para receber outro.
                            </p>
                            <p style="margin:0 0 8px; font-size:12px; color:#6b7280;">
                                Se o botão não funcionar, copie e cole o link abaixo no seu navegador:
                            </p>
                            <p style="margin:0; font-size:12px; word-break:break-all;">
                                <a href="{{ $link }}" style="color:#e31c1c;">{{ $link }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px; background:#f8f9fa; border-top:1px solid #e5e7eb;">
                            <p style="margin:0; font-size:11px; color:#9ca3af; text-align:center;">
                                Este é um e-mail automático, não é necessário responder.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
