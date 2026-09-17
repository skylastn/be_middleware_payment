<?php

namespace App\Http\Middleware;

use App\Http\Helper\ResponseHelper;
use App\Interface\RedisServiceInterface;
use App\Repository\System\ProjectRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateProjectToken
{
    private ProjectRepository $projects;

    private RedisServiceInterface $redisService;

    public function __construct(
        ?ProjectRepository $projects = null,
        ?RedisServiceInterface $redisService = null
    ) {
        $this->projects = $projects ?? new ProjectRepository;
        $this->redisService = $redisService ?? app(RedisServiceInterface::class);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Token');
        if (! $token) {
            return ResponseHelper::unauthorizedResponse('Unauthorized: Missing Token header', 'Unauthorized', 401);
        }

        $cacheKey = 'project:token:'.hash('sha256', $token);
        $project = $this->redisService->remember($cacheKey, 3600, function () use ($token) {
            return $this->projects->findByToken($token);
        });

        if (! $project) {
            $this->redisService->del($cacheKey);

            return ResponseHelper::unauthorizedResponse('Unauthorized: Invalid Token', 'Unauthorized', 401);
        }

        $request->attributes->set('project', $project);

        return $next($request);
    }
}
