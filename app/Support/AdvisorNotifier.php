<?php

namespace App\Support;

use App\Mail\LegalAidAdvisorNotificationMail;
use App\Models\LegalAidRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class AdvisorNotifier
{
    public static function caseReady(LegalAidRequest $legalAidRequest): void
    {
        $advisors = User::where('role', 'advisor')->pluck('email')->values()->all();

        if ($advisors === []) {
            return;
        }

        Mail::to($advisors)
            ->locale('en')
            ->queue(new LegalAidAdvisorNotificationMail($legalAidRequest));
    }
}
