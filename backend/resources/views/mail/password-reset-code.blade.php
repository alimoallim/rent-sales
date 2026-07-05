@php
    $ttl = (int) config('auth.password_reset_code_ttl', 15);
    $digits = str_split($code);
    $grouped = strlen($code) === 6
        ? substr($code, 0, 3).' '.substr($code, 3)
        : $code;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password reset code</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#18181b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f5;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:480px;background-color:#ffffff;border:1px solid #e4e4e7;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:28px 28px 8px 28px;">
                            <p style="margin:0 0 8px 0;font-size:13px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:#71717a;">
                                {{ config('app.name') }}
                            </p>
                            <h1 style="margin:0;font-size:22px;line-height:1.3;font-weight:700;color:#18181b;">
                                Password reset code
                            </h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 0 28px;">
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#52525b;">
                                Hello {{ $user->name }},
                            </p>
                            <p style="margin:12px 0 0 0;font-size:15px;line-height:1.6;color:#52525b;">
                                Use this verification code to reset your password. Enter it exactly as shown below.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:24px 28px 8px 28px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto;">
                                <tr>
                                    @foreach ($digits as $digit)
                                        <td style="padding:0 4px;">
                                            <div style="width:44px;height:52px;line-height:52px;text-align:center;font-family:'SF Mono',SFMono-Regular,Consolas,'Liberation Mono',Menlo,Monaco,'Courier New',monospace;font-size:28px;font-weight:700;color:#18181b;background-color:#f4f4f5;border:1px solid #d4d4d8;border-radius:8px;">
                                                {{ $digit }}
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            </table>
                            <p style="margin:14px 0 0 0;font-family:'SF Mono',SFMono-Regular,Consolas,'Liberation Mono',Menlo,Monaco,'Courier New',monospace;font-size:18px;font-weight:600;letter-spacing:0.35em;color:#3f3f46;">
                                {{ $grouped }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 24px 28px;">
                            <p style="margin:0;font-size:13px;line-height:1.5;color:#71717a;text-align:center;">
                                This code expires in {{ $ttl }} {{ $ttl === 1 ? 'minute' : 'minutes' }}.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 28px 28px;">
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#a1a1aa;">
                                If you did not request a password reset, you can safely ignore this email. Your password will not change.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
