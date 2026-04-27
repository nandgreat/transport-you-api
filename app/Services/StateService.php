<?php

namespace App\Services;

use App\Models\State;

class StateService
{
    public function getStates(int $perPage = 40)
    {
        return State::paginate($perPage);
    }

    public function createState(array $data)
    {
        return State::create($data);
    }

    public function getState(string $id)
    {
        return State::findOrFail($id);
    }

    public function updateState(string $id, array $data)
    {
        $state = $this->getState($id);
        $state->update($data);
        return $state;
    }

    public function deleteState(string $id)
    {
        $state = $this->getState($id);
        $state->delete();
        return true;
    }
}
