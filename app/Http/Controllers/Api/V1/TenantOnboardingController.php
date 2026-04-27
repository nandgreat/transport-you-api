<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\TenantOnboardingRequest;
use App\Services\TenantService;

class TenantOnboardingController extends Controller
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    public function onboard(TenantOnboardingRequest $request)
    {
        $this->tenantService->onboardTenant($request->validated());

        return $this->created(null, 'Tenant onboarding successful. An OTP has been sent to the admin email.');
    }
}
