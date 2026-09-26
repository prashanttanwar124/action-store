<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Display the customer account dashboard.
     */
    public function index(): Response
    {
        return Inertia::render('Account');
    }

    /**
     * Display the customer account with reorder section active.
     */
    public function reorder(): Response
    {
        return Inertia::render('Account', [
            'activeSection' => 'reorder',
        ]);
    }
}
