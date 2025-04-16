<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Billings;
use Illuminate\Http\Request;

class BillingsController extends Controller
{
    public function unpaidBills($powasID = '') {
        $data = 0;

        if ($powasID == '') {
            $data = Billings::join('powas_members', 'billings.member_id', '=', 'powas_members.member_id')
                ->join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->where('billings.bill_status', 'UNPAID')
                ->orderBy('powas_applications.lastname', 'asc')
                ->orderBy('powas_applications.firstname', 'asc')
                ->orderBy('powas_applications.middlename', 'asc')
                ->get();
        } else {
            $data = Billings::join('powas_members', 'billings.member_id', '=', 'powas_members.member_id')
                ->join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->where('billings.bill_status', 'UNPAID')
                ->where('billings.powas_id', $powasID)
                ->orderBy('powas_applications.lastname', 'asc')
                ->orderBy('powas_applications.firstname', 'asc')
                ->orderBy('powas_applications.middlename', 'asc')
                ->get();
        }

        return response()->json($data);
    }
}
