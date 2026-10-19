<?php

namespace App\Http\Controllers;

use App\Models\Plan;

class LegalController extends Controller
{
    public function home()
    {
        return view('landing', ['plans' => Plan::query()->orderBy('monthly_fee')->get()]);
    }

    public function privacy()
    {
        return view('legal.privacy');
    }

    public function terms()
    {
        return view('legal.terms');
    }

    public function accountDeletion()
    {
        return view('legal.account-deletion');
    }
}
