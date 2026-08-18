<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalAidRequest;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $googlePayPaid = LegalAidRequest::where('status', LegalAidRequest::STATUS_PAID)
            ->where(fn ($q) => $q->whereNull('base_price')->orWhere('base_price', '>', 0))
            ->count();

        $bankConfirmed = LegalAidRequest::where('status', LegalAidRequest::STATUS_CONFIRMED)
            ->where(fn ($q) => $q->whereNull('base_price')->orWhere('base_price', '>', 0))
            ->count();

        $free = LegalAidRequest::where('base_price', 0)->count();

        return view('admin.dashboard', [
            'totalUsers' => User::count(),
            'pendingRequests' => LegalAidRequest::whereIn('status', [
                LegalAidRequest::STATUS_PENDING_PAYMENT,
                LegalAidRequest::STATUS_PENDING,
            ])->count(),
            'confirmedRequests' => LegalAidRequest::where('status', LegalAidRequest::STATUS_CONFIRMED)->count(),
            'recentRequests' => LegalAidRequest::latest()->limit(5)->get(),
            'paymentSplit' => [
                'googlePayPaid' => $googlePayPaid,
                'bankConfirmed' => $bankConfirmed,
                'free' => $free,
            ],
        ]);
    }
}
