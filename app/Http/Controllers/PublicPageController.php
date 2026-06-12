<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PublicPageController extends Controller
{
    public function home(Request $request)
    {
        $locale = $request->query('lang', 'ar');
        if (!in_array($locale, ['en', 'fr', 'ar'], true)) {
            $locale = 'ar';
        }
        app()->setLocale($locale);

        return view('landing');
    }

    public function comingSoon()
    {
        return view('coming-soon');
    }

    public function app()
    {
        return view('react-law-search');
    }

    public function search()
    {
        return view('react-law-search');
    }

    public function corpusStatus()
    {
        return app()->call([app(CorpusStatusController::class), 'show']);
    }
}
