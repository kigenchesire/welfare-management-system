@php
    $link = function (string $route, string $label, string $icon, $params = [], ?string $activePattern = null, $badge = null) {
        $active = request()->routeIs($activePattern ?? $route) ? ' active' : '';
        $b = $badge ? '<span class="badge rounded-pill text-bg-danger">'.e($badge).'</span>' : '';
        return '<a class="sidebar-link'.$active.'" href="'.e(route($route, $params)).'"><i class="bi bi-'.$icon.'"></i><span>'.e($label).'</span>'.$b.'</a>';
    };
    $pendingCount = $u->can('members.approve') ? \App\Models\Member::where('status', 'pending')->count() : 0;
@endphp
<nav class="app-sidebar">
    <a href="{{ route('dashboard') }}" class="brand">
        @if($logo = \App\Support\Settings::image('logo'))
            <img src="{{ $logo }}" alt="Logo">
        @else
            <span class="bg-brand rounded d-inline-flex align-items-center justify-content-center fw-bold" style="width:36px;height:36px;">{{ mb_substr($s['short_name'] ?: $s['group_name'], 0, 2) }}</span>
        @endif
        <span class="brand-name">{{ $s['group_name'] }}</span>
    </a>

    <div class="py-2">
        {!! $link('dashboard', 'Dashboard', 'speedometer2') !!}

        @if($u->member)
            <div class="sidebar-section">My Account</div>
            {!! $link('member.wallet', 'My Wallet', 'wallet2') !!}
            {!! $link('member.invoices', 'My Invoices', 'receipt', [], 'member.invoice*') !!}
            {!! $link('member.payments', 'My Payments', 'cash-coin', [], 'member.payments|member.receipt') !!}
            {!! $link('member.beneficiaries', 'My Beneficiaries', 'people') !!}
            {!! $link('member.cases', 'Welfare Cases', 'heart', [], 'member.cases') !!}
            {!! $link('member.profile', 'My Profile', 'person') !!}
        @endif

        @can('members.view')
            <div class="sidebar-section">Membership</div>
            {!! $link('members.index', 'All Members', 'people', [], 'members.index|members.show|members.edit|members.create') !!}
            {!! $link('members.pending', 'Pending Registrations', 'person-plus', [], null, $pendingCount ?: null) !!}
            {!! $link('beneficiaries.index', 'Beneficiaries', 'diagram-3') !!}
        @endcan

        @canany(['cases.view', 'invoices.view'])
            <div class="sidebar-section">Welfare Management</div>
            @can('cases.view'){!! $link('cases.index', 'Welfare Cases', 'heart', [], 'cases.*') !!}@endcan
            @can('reports.welfare'){!! $link('reports.show', 'Case Contributions', 'list-check', ['case-contributions']) !!}@endcan
            @can('invoices.view'){!! $link('invoices.index', 'Invoices', 'receipt', ['type' => 'welfare'], 'invoices.index|invoices.show') !!}@endcan
        @endcanany

        @canany(['wallets.view', 'deposits.record', 'finance.view', 'invoices.manage', 'office.view'])
            <div class="sidebar-section">Finance</div>
            @can('wallets.view'){!! $link('wallets.index', 'Member Wallets', 'wallet2', [], 'wallets.*') !!}@endcan
            @can('deposits.record'){!! $link('payments.create', 'Record Deposit', 'plus-circle') !!}@endcan
            @can('wallets.view'){!! $link('payments.index', 'Deposits', 'cash-coin', [], 'payments.index|payments.show') !!}@endcan
            @can('wallets.view'){!! $link('transactions.index', 'Transactions', 'arrow-left-right') !!}@endcan
            @can('invoices.manage'){!! $link('invoices.create', 'Custom Invoices', 'file-earmark-plus') !!}@endcan
            @can('office.view'){!! $link('office.index', 'Office Account', 'building') !!}@endcan
            @can('finance.view'){!! $link('finance.summary', 'Financial Overview', 'graph-up') !!}@endcan
        @endcanany

        @canany(['announcements.manage', 'sms.view'])
            <div class="sidebar-section">Communication</div>
            @can('announcements.manage'){!! $link('announcements.index', 'Announcements', 'megaphone') !!}@endcan
            {!! $link('notifications', 'Notifications', 'bell') !!}
            @can('sms.view'){!! $link('sms.logs', 'SMS Logs', 'chat-left-text') !!}@endcan
        @endcanany

        @canany(['reports.members', 'reports.welfare', 'reports.financial'])
            <div class="sidebar-section">Reports</div>
            {!! $link('reports.index', 'All Reports', 'file-earmark-bar-graph', [], 'reports.*') !!}
        @endcanany

        @canany(['users.manage', 'roles.manage', 'audit.view', 'settings.manage', 'website.manage'])
            <div class="sidebar-section">Administration</div>
            @can('users.manage'){!! $link('admin.users', 'Users', 'person-gear', [], 'admin.users*') !!}@endcan
            @can('roles.manage'){!! $link('admin.roles', 'Roles & Permissions', 'shield-lock') !!}@endcan
            @can('audit.view'){!! $link('admin.audit', 'Audit Logs', 'journal-text', [], 'admin.audit*') !!}@endcan
            @can('settings.manage'){!! $link('admin.settings', 'System Settings', 'gear') !!}@endcan
            @can('website.manage'){!! $link('admin.website', 'Website Management', 'globe') !!}@endcan
        @endcanany
    </div>
</nav>
