<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Powas;
use Illuminate\Http\Request;

class POWASController extends Controller
{
    public function index(){
        $data = Powas::all();
        return response()->json($data);
    }

    public function show($id)
    {
        $data = Powas::find($id);
        return response()->json($data);
    }
}
