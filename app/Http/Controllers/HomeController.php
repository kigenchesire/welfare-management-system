<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\Member;
use App\Models\WebsiteImage;
use App\Services\MemberService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The single public page and the online registration form on it. */
class HomeController extends Controller
{
    public function index()
    {
        return view('public.home', [
            'images' => WebsiteImage::where('is_visible', true)->orderBy('sort_order')->get(),
            'categories' => Beneficiary::CATEGORIES,
        ]);
    }

    public function register(Request $request, MemberService $members)
    {
        $data = $request->validate(MemberController::memberRules() + [
            'beneficiaries' => ['array'],
            'beneficiaries.*.name' => ['nullable', 'string', 'max:150'],
            'beneficiaries.*.category' => ['nullable', Rule::in(array_keys(Beneficiary::CATEGORIES))],
            'agree' => ['accepted'],
        ], [
            'national_id.unique' => 'This National ID is already registered. Please contact the Secretary.',
            'agree.accepted' => 'Please confirm that the information is correct.',
        ]);

        $beneficiaries = BeneficiaryController::cleanList($request->input('beneficiaries', []));
        $members->register($data, $beneficiaries, 'online');

        return redirect()->to(route('home').'#register')
            ->with('registered', 'Thank you! Your registration has been received and is awaiting approval by the Secretary. You will receive your login details once approved.');
    }
}
