<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PowasMembers;
use Illuminate\Http\Request;

class MembersController extends Controller
{
    public function index()
    {
        $data = PowasMembers::all();
        return response()->json($data);
    }
}
