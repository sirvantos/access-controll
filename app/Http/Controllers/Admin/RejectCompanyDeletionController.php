<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

final class RejectCompanyDeletionController extends Controller
{
    public function __invoke(): never
    {
        abort(405);
    }
}
