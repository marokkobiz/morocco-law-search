<?php

namespace App\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use App\Models\LegalAidCaseNote;
use App\Models\LegalAidRequest;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $visible = LegalAidRequest::visibleToAdvisors();

        $total = (clone $visible)->count();
        $open = (clone $visible)->caseStatus(LegalAidRequest::CASE_OPEN)->count();
        $closed = (clone $visible)->caseStatus(LegalAidRequest::CASE_CLOSED)->count();
        $unclaimed = (clone $visible)->whereNull('advisor_id')->count();

        $mine = (clone $visible)->where('advisor_id', auth()->id());
        $myCases = (clone $mine)->count();
        $myOpen = (clone $mine)->caseStatus(LegalAidRequest::CASE_OPEN)->count();

        $requests = $visible
            ->with(['services', 'service', 'advisor'])
            ->orderByDesc('created_at')
            ->get();

        $tasksTotal = $requests->sum(fn (LegalAidRequest $r) => $r->selectedServices->count());
        $tasksDone = $requests->sum(fn (LegalAidRequest $r) => $r->completedServices()->count());

        $notesCount = LegalAidCaseNote::count();

        $recent = $requests->take(5);

        return view('advisor.dashboard', [
            'stats' => [
                'total' => $total,
                'open' => $open,
                'closed' => $closed,
                'unclaimed' => $unclaimed,
                'myCases' => $myCases,
                'myOpen' => $myOpen,
                'tasksTotal' => $tasksTotal,
                'tasksDone' => $tasksDone,
                'notesCount' => $notesCount,
            ],
            'recent' => $recent,
        ]);
    }
}