<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WelfareCase;
use App\Services\FinanceService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, FinanceService $finance)
    {
        $user = $request->user();

        if (! $user->isStaff()) {
            return app(MemberPortalController::class)->dashboard($request);
        }

        $data = ['user' => $user];

        if ($user->hasAnyRole(['Administrator', 'Chairperson'])) {
            $data['overview'] = [
                'members' => Member::whereIn('status', ['active', 'suspended', 'inactive'])->count(),
                'active' => Member::active()->count(),
                'pending' => Member::where('status', 'pending')->count(),
                'cases' => WelfareCase::where('status', '!=', 'cancelled')->count(),
                'open_cases' => WelfareCase::whereIn('status', ['open', 'in_progress', 'fully_collected'])->count(),
                'closed_cases' => WelfareCase::where('status', 'closed')->count(),
                'wallets' => (float) Wallet::sum('balance'),
                'outstanding' => (float) Invoice::open()->sum(\DB::raw('amount - amount_paid')),
                'deposits' => (float) Payment::where('status', 'confirmed')->sum('amount'),
            ];
            $data['trend'] = $finance->monthlyTrend();
            $data['activities'] = AuditLog::with('user')->latest('created_at')->limit(10)->get();
        }

        if ($user->hasRole('Secretary')) {
            $data['secretary'] = [
                'pending' => Member::where('status', 'pending')->latest()->limit(8)->get(),
                'pending_count' => Member::where('status', 'pending')->count(),
                'members' => Member::active()->count(),
                'open_cases' => WelfareCase::whereIn('status', ['open', 'in_progress', 'fully_collected'])->count(),
                'beneficiaries' => Beneficiary::with('member')->latest('updated_at')->limit(8)->get(),
            ];
        }

        if ($user->hasAnyRole(['Treasurer'])) {
            $data['treasurer'] = [
                'wallets' => (float) Wallet::sum('balance'),
                'outstanding_invoices' => Invoice::open()->count(),
                'unpaid_welfare' => (float) Invoice::open()->where('type', 'welfare')->sum(\DB::raw('amount - amount_paid')),
                'unpaid_custom' => (float) Invoice::open()->where('type', 'custom')->sum(\DB::raw('amount - amount_paid')),
                'deposits' => Payment::with('member')->latest()->limit(8)->get(),
                'transactions' => WalletTransaction::with('member')->latest('id')->limit(8)->get(),
                'month' => $finance->summary(now()->startOfMonth()->toDateString(), now()->toDateString()),
            ];
        }

        $data['recentCases'] = WelfareCase::with('member')->latest()->limit(6)->get();

        return view('dashboard.staff', $data);
    }
}
