<?php

namespace App\Http\Middleware;

use App\Http\Helper\ResponseHelper;
use App\Repository\System\ProjectRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateProjectToken
{
    public function __construct(private ProjectRepository $projects = new ProjectRepository()) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (auth('sanctum')->check() && auth('sanctum')->user()?->isAdmin()) {
            return $next($request);
        }

        $token = $request->header('Token');
        if (! $token) {
            return ResponseHelper::failedResponse('Unauthorized: Missing Token header', 'Unauthorized', 401);
        }

        $project = $this->projects->findByToken($token);
        if (! $project) {
            return ResponseHelper::failedResponse('Unauthorized: Invalid Token', 'Unauthorized', 401);
        }

        $request->attributes->set('project', $project);

        return $next($request);
    }
}
