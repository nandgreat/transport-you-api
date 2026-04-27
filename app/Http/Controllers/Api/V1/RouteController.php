<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Route\StoreRouteRequest;
use App\Http\Requests\Route\UpdateRouteRequest;
use App\Services\RouteService;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    protected $routeService;

    public function __construct(RouteService $routeService)
    {
        $this->routeService = $routeService;

        $this->middleware(function ($request, $next) {
            if (!$request->user()) {
                return $this->notFound('Unauthorized.');
            }
            if (!$request->user()->isSuperAdmin() && !$request->user()->tenant_id) {
                return $this->badRequest('Unauthorized. Must belong to a tenant.');
            }
            return $next($request);
        });
    }

    private function resolveTenantId(Request $request, ?string $inputKey = 'tenant_id'): ?int
    {
        if ($request->user()->isSuperAdmin()) {
            return $request->input($inputKey) ? (int) $request->input($inputKey) : null;
        }
        return $request->user()->tenant_id;
    }

    public function index(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);
        return $this->success($this->routeService->getRoutes($tenantId), 'Routes retrieved successfully');
    }

    public function store(StoreRouteRequest $request)
    {
        $tenantId = $this->resolveTenantId($request);
        $route = $this->routeService->createRoute($tenantId, $request->validated());
        return $this->created($route, 'Route created successfully');
    }

    public function show(Request $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        return $this->success($this->routeService->getRoute($tenantId, $id), 'Route retrieved successfully');
    }

    public function update(UpdateRouteRequest $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $route = $this->routeService->updateRoute($tenantId, $id, $request->validated());
        return $this->success($route, 'Route updated successfully');
    }

    public function destroy(Request $request, string $id)
    {
        $tenantId = $this->resolveTenantId($request);
        $this->routeService->deleteRoute($tenantId, $id);
        return $this->success(null, 'Route deleted successfully');
    }
}
