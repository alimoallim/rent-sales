@php
    $ttl = (int) config('auth.password_reset_code_ttl', 15);
    $grouped = strlen($code) === 6
        ? substr($code, 0, 3).' '.substr($code, 3)
        : $code;
@endphp
Hello {{ $user->name }},

You requested a password reset for your {{ config('app.name') }} account.

Your verification code is:

  {{ $grouped }}

Enter the 6 digits exactly as shown above.

This code expires in {{ $ttl }} {{ $ttl === 1 ? 'minute' : 'minutes' }}.

If you did not request this, you can ignore this email. Your password will stay unchanged.

Thanks,
{{ config('app.name') }}
