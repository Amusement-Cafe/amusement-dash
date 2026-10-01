<?php

namespace App\Http\Middleware;

use App\Models\PageToggle;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePageEnabled
{
    public function handle(Request $request, Closure $next, string $page): Response
    {
        abort_unless(PageToggle::isEnabled($page), 403, 'This page is currently unavailable.');

        return $next($request);
    }
}
