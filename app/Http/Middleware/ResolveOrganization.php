<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\Tenancy\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveOrganization
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $organization = $request->route('organization');

        if (! $organization instanceof Organization) {
            abort(404);
        }

        $this->currentOrganization->set($organization);

        try {
            return $next($request);
        } finally {
            $this->currentOrganization->clear();
        }
    }
}