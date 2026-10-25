<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class FieldAppController extends Controller
{
    public function __invoke(): View
    {
        return view('field.app');
    }
}
