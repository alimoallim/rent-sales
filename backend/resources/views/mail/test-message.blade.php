This is a test email from {{ config('app.name') }}.

If you received this message, outbound mail (SMTP) is configured correctly.

Sent at: {{ now()->toIso8601String() }}
