<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead>
        <tr>
            <th>Date</th><th>Reference</th>
            @if($showMember)<th>Member</th>@endif
            <th>Type</th><th>Description</th>
            <th class="text-end">Amount</th><th class="text-end">Balance</th>
            @if($showMember)<th>By</th>@endif
        </tr>
        </thead>
        <tbody>
        @forelse($transactions as $t)
            <tr>
                <td class="text-nowrap">{{ $t->transaction_date->format('d M Y H:i') }}</td>
                <td class="small">{{ $t->reference }}</td>
                @if($showMember)<td><a href="{{ route('wallets.show', $t->member_id) }}">{{ $t->member->full_name }}</a></td>@endif
                <td>{{ $t->type_label }}</td>
                <td class="small">{{ $t->description }}</td>
                <td class="text-end amount {{ $t->direction }}">{{ $t->direction === 'credit' ? '+' : '-' }}{{ number_format($t->amount, 2) }}</td>
                <td class="text-end amount">{{ number_format($t->balance_after, 2) }}</td>
                @if($showMember)<td class="small">{{ $t->recorder?->name ?? 'System' }}</td>@endif
            </tr>
        @empty
            <tr><td colspan="8" class="empty-state">No transactions yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if(method_exists($transactions, 'links') && $transactions->hasPages())
    <div class="card-footer bg-white">{{ $transactions->links() }}</div>
@endif
