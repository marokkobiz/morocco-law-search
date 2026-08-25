@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827;">
        Hello {{ $advisor->name }},
    </p>

    <p style="margin:0 0 20px;font-size:14px;color:#4b5563;line-height:1.7;">
        An advisor account has been created for you on MarocLoi. Use the credentials below to log in to the advisor portal and start working on cases.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
        <tr>
            <td style="padding:14px 16px;background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding:8px 0;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;width:140px;vertical-align:top;">Login link</td>
                        <td style="padding:8px 0 8px 16px;font-size:14px;color:#111827;vertical-align:top;">{{ route('login') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;width:140px;vertical-align:top;">Email</td>
                        <td style="padding:8px 0 8px 16px;font-size:14px;color:#111827;vertical-align:top;">{{ $advisor->email }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;width:140px;vertical-align:top;">Temporary password</td>
                        <td style="padding:8px 0 8px 16px;font-size:14px;font-weight:700;color:#111827;font-family:ui-monospace,Consolas,monospace;vertical-align:top;">{{ $temporaryPassword }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 20px;">
        <a href="{{ route('login') }}"
           style="display:inline-block;padding:12px 24px;background-color:#111827;color:#ffffff;font-size:14px;font-weight:600;text-decoration:none;border-radius:10px;">
            Log in to the advisor portal
        </a>
    </p>

    <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.7;">
        For security, please change your password after your first login. If you did not expect this email, you can ignore it — an administrator created this account for you.
    </p>
@endsection