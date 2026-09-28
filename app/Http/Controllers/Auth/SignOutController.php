<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\OkResource;
use Illuminate\Http\Request;

final class SignOutController extends Controller
{
    public function __invoke(Request $request): OkResource
    {
        auth('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return new OkResource(null);
    }
}
