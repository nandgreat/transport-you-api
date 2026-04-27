<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\State\StoreStateRequest;
use App\Http\Requests\State\UpdateStateRequest;
use App\Services\StateService;
use Illuminate\Http\Request;

class StateController extends Controller
{
    protected $stateService;

    public function __construct(StateService $stateService)
    {
        $this->stateService = $stateService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 40);
        return $this->success($this->stateService->getStates($perPage), 'States retrieved successfully');
    }

    public function store(StoreStateRequest $request)
    {
        $state = $this->stateService->createState($request->validated());
        return $this->created($state, 'State created successfully');
    }

    public function show(string $id)
    {
        return $this->success($this->stateService->getState($id), 'State retrieved successfully');
    }

    public function update(UpdateStateRequest $request, string $id)
    {
        $state = $this->stateService->updateState($id, $request->validated());
        return $this->success($state, 'State updated successfully');
    }

    public function destroy(string $id)
    {
        if (!auth()->user()->isSuperAdmin()) {
            return $this->badRequest('Unauthorized. Only super admins can delete states.');
        }

        $this->stateService->deleteState($id);
        return $this->success(null, 'State deleted successfully');
    }
}
