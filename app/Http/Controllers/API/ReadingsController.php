<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReadingsAPIController extends Controller
{
    public function readingsIndex($powasID = '') {
        $data = 0;

        return response()->json($data);
    }
}
