<?php

namespace App\Livewire\Voucher;

use App\Models\Powas;
use App\Models\Transactions;
use App\Models\Vouchers;
use Livewire\Component;
use NumberFormatter;

class VoucherPrint extends Component
{
    public $powasID;
    public $powas;
    public $voucherID;
    public $voucherInfo;
    public $transactionInfo;
    public $inWords;

    public function mount($powasID, $powas, $voucherID)
    {
        $this->powasID = $powasID;
        $this->powas = $powas;
        $this->voucherID = $voucherID;

        $this->voucherInfo = Vouchers::with('voucherparticulars')->find($voucherID);

        $this->transactionInfo = Transactions::where('trxn_id', $this->voucherInfo->trxn_id)->first();

        $inWordsFormatter = new NumberFormatter('en', NumberFormatter::SPELLOUT);

        $this->inWords = strtoupper($inWordsFormatter->format($this->voucherInfo->amount)) . ' PESOS ONLY';
    }

    public function render()
    {
        return view('livewire.voucher.voucher-print');
    }
}
