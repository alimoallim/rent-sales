Draft charge batches — {{ config('app.name') }}

@if($createdCount > 0)
{{ $createdCount }} new draft batch(es) were generated for {{ $billingMonth }}/{{ $billingYear }}.
@else
No new batches were created for {{ $billingMonth }}/{{ $billingYear }} (existing batches may already cover this period).
@endif

There {{ $pendingCount === 1 ? 'is' : 'are' }} currently {{ $pendingCount }} batch(es) awaiting review or approval.

Sign in to the rental module and open Charge batches to review and approve tenant charges.
