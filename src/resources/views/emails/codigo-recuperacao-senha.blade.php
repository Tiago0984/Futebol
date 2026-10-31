<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Código de recuperação de senha</title></head>
<body style="font-family: Arial, sans-serif; color: #1e293b;">
    <h1>Recuperação de senha do app AACJ</h1>
    <p>Olá, {{ $nome }}!</p>
    <p>Use este código no aplicativo para recuperar a senha do perfil <strong>{{ $perfil === 'responsavel' ? 'responsável' : 'atleta' }}</strong>:</p>
    <p style="font-size: 32px; font-weight: bold; letter-spacing: 8px;">{{ $codigo }}</p>
    <p>O código vale por <strong>15 minutos</strong> e é invalidado após 5 tentativas erradas.</p>
    <p>Se não foi você quem solicitou, ignore este e-mail. Sua senha atual continua valendo. Não compartilhe o código.</p>
</body>
</html>
