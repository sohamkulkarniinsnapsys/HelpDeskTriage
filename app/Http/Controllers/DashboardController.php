<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Redirect users to the appropriate landing page based on role.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->role->isAgent()) {
            return redirect()->route('tickets.all');
        }

        return redirect()->route('tickets.my');
    }
}
