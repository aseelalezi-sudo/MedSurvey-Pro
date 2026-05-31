<?php

namespace App\Http\Controllers\Web;

use App\Models\Survey;
use Illuminate\View\View;

class HomeController
{
    public function __invoke(): View
    {
        $surveys = Survey::query()
            ->where('isActive', true)
            ->withCount('responses')
            ->orderByDesc('createdAt')
            ->limit(6)
            ->get();

        return view('pages.home', compact('surveys'));
    }
}
