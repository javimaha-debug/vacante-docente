<!DOCTYPE html>
<html lang="es">
<body style="font-family: Inter, Arial, sans-serif; color: #1e293b; background:#f1f5f9; padding:24px;">
    <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;padding:28px;border:1px solid #e2e8f0;">
        <h1 style="font-size:18px;margin:0 0 4px;">Vacante<span style="color:#1f52e3;">Docente</span></h1>
        <p style="color:#64748b;font-size:14px;margin:0 0 20px;">Nuevo mensaje en tu anuncio del tablón</p>

        <p style="font-size:13px;color:#94a3b8;margin:0;">{{ strtoupper($categoria) }}</p>
        <p style="font-size:16px;font-weight:600;margin:2px 0 16px;">{{ $titulo }}</p>

        <div style="background:#f8fafc;border-radius:12px;padding:16px;font-size:14px;line-height:1.5;">
            {{ $mensaje }}
        </div>

        <a href="{{ $replyUrl }}"
           style="display:inline-block;margin-top:20px;background:#1f52e3;color:#fff;text-decoration:none;
                  padding:12px 20px;border-radius:10px;font-weight:600;font-size:14px;">
            Responder
        </a>

        <p style="margin-top:20px;font-size:12px;color:#94a3b8;line-height:1.5;">
            El usuario que te contacta permanece anónimo hasta que decidas compartir tu información.
            Este enlace de respuesta caduca en 7 días.
        </p>
    </div>
</body>
</html>
