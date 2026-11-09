<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    /**
     * List cities, optionally filtered by state. Public reference data used to
     * populate the city dropdown when registering a location.
     */
    public function index(Request $request)
    {
        $request->validate([
            'state_id' => 'nullable|integer|exists:states,id',
            'search'   => 'nullable|string|max:100',
        ]);

        $cities = City::query()
            ->when($request->filled('state_id'), fn ($q) => $q->where('state_id', $request->integer('state_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = strtolower((string) $request->input('search'));
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"]);
            })
            ->orderBy('name')
            ->get(['id', 'state_id', 'name']);

        return $this->success($cities, 'Cities retrieved successfully');
    }
}
