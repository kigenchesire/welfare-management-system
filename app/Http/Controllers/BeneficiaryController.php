<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\Member;
use App\Services\MemberService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Only users with beneficiaries.manage (the Secretary) can change beneficiaries. */
class BeneficiaryController extends Controller
{
    public function __construct(private MemberService $members)
    {
    }

    public function index(Request $request)
    {
        $beneficiaries = Beneficiary::with('member')
            ->when($request->input('category'), fn ($q, $c) => $q->where('category', $c))
            ->latest('updated_at')->get();

        return view('beneficiaries.index', ['beneficiaries' => $beneficiaries, 'categories' => Beneficiary::CATEGORIES]);
    }

    public function store(Request $request, Member $member)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(Beneficiary::CATEGORIES))],
        ]);
        $this->members->addBeneficiary($member, $data['name'], $data['category']);

        return redirect()->to(route('members.show', $member).'#beneficiaries')->with('success', 'Beneficiary added.');
    }

    public function update(Request $request, Beneficiary $beneficiary)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(Beneficiary::CATEGORIES))],
        ]);
        $this->members->updateBeneficiary($beneficiary, $data['name'], $data['category']);

        return redirect()->to(route('members.show', $beneficiary->member_id).'#beneficiaries')->with('success', 'Beneficiary updated.');
    }

    public function destroy(Beneficiary $beneficiary)
    {
        $memberId = $beneficiary->member_id;
        $this->members->removeBeneficiary($beneficiary);

        return redirect()->to(route('members.show', $memberId).'#beneficiaries')->with('success', 'Beneficiary removed.');
    }

    /**
     * Turn the registration form's beneficiary rows into a clean list, dropping
     * empty rows and enforcing the per-category limits.
     */
    public static function cleanList(array $rows): array
    {
        $list = [];
        $counts = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $cat = $row['category'] ?? null;
            if ($name === '' || ! isset(Beneficiary::CATEGORIES[$cat])) {
                continue;
            }
            $counts[$cat] = ($counts[$cat] ?? 0) + 1;
            $limit = Beneficiary::limit($cat);
            if ($limit !== null && $counts[$cat] > $limit) {
                throw ValidationException::withMessages(['beneficiaries' => 'Only '.$limit.' '.strtolower(Beneficiary::label($cat)).' beneficiar'.($limit === 1 ? 'y is' : 'ies are').' allowed.']);
            }
            $list[] = ['name' => $name, 'category' => $cat];
        }

        return $list;
    }
}
