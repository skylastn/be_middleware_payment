<?php

namespace App\Http\Middleware;

use App\Http\Helper\ResponseHelper;
use App\Repository\System\ProjectRepository;
use App\Services\System\RedisService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateClientPaymentToken
{
    public function __construct(
        private RedisService $redisService = new RedisService(),
        private ProjectRepository $projects = new ProjectRepository()
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Token');
        if (! $token) {
            return ResponseHelper::failedResponse('Unauthorized: Missing Token header', 'Unauthorized', 401);
        }

        $paymentTokenData = $this->redisService->getPaymentToken($token);
        if (! $paymentTokenData || empty($paymentTokenData['project_id'])) {
            return ResponseHelper::failedResponse('Token expired or invalid', 'Unauthorized', 401);
        }

        $reference = $request->query('reference') ?? $request->input('reference');
        if ($reference && ! empty($paymentTokenData['reference']) && $paymentTokenData['reference'] !== $reference) {
            return ResponseHelper::failedResponse('Token reference mismatch', 'Forbidden', 403);
        }

        $project = $this->projects->find($paymentTokenData['project_id']);
        if (! $project) {
            return ResponseHelper::failedResponse('Project not found', 'Unauthorized', 401);
        }

        $request->attributes->set('project', $project);

        return $next($request);
    }
}
