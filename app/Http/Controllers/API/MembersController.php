<?php

namespace App\Http\Controllers\API;

use App\Http\Resources\MembersResource;
use App\Models\Powas;
use App\Models\PowasMembers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembersController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $powasID = $request->query('powas-id');
        $perPage = $request->query('per-page', 10);

        $powas = Powas::find($powasID);

        if (is_null($powas)) {
            return $this->sendError('Not Found', ['error' => 'POWAS not found!']);
        }

        $membersQuery = PowasMembers::where('powas_applications.powas_id', $powasID)
        ->join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
        ->orderBy('powas_applications.lastname', 'asc');

        $members = $membersQuery->paginate($perPage);

        return $this->sendResponse(["members" => MembersResource::collection($members), "totalMembers" => $members->total()], 'Members list retrieved successfully!');
    }
}
