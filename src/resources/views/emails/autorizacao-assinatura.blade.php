<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autorização de Cadastro</title>
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
                            <h1 style="margin:0 0 16px; font-size:18px; color:#1e293b;">Olá, {{ $responsavel->nome_responsavel }}!</h1>
                            <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#374151;">
                                Recebemos o cadastro de <strong>{{ $atleta->nome_atleta }}</strong> na Escolinha de Futebol AACJ.
                                Para concluir a matrícula, é necessário assinar a autorização do atleta clicando no botão abaixo.
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                                <tr>
                                    <td style="border-radius:6px; background:#e31c1c;">
                                        <a href="{{ $linkAssinatura }}" target="_blank"
                                           style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:bold; color:#ffffff; text-decoration:none;">
                                            Assinar autorização
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 8px; font-size:12px; color:#6b7280;">
                                Se o botão não funcionar, copie e cole o link abaixo no seu navegador:
                            </p>
                            <p style="margin:0; font-size:12px; word-break:break-all;">
                                <a href="{{ $linkAssinatura }}" style="color:#e31c1c;">{{ $linkAssinatura }}</a>
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
