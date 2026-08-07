<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarocLoi</title>
</head>
<body style="margin:0;padding:0;background-color:#f5f6f8;font-family:'Segoe UI',Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f6f8;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">
                    <tr>
                        <td align="center" style="padding-bottom:20px;">
                            <span style="font-size:22px;font-weight:700;color:#1e3a8a;letter-spacing:.5px;">Maroc<span style="color:#2563eb;">Loi</span></span>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#ffffff;border-radius:12px;border:1px solid #e5e7eb;padding:32px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding-top:20px;color:#9ca3af;font-size:12px;line-height:1.6;">
                            {{ __('emails.footer_line') }}<br>
                            contact@marocloi.com
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
