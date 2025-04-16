<?php

namespace App\Livewire\Transactions;

use App\Models\ChartOfAccounts;
use App\Models\Transactions;
use App\Models\Vouchers;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ExpensesList extends Component
{
    use WithPagination;
    public $powas;
    public $powasID;
    public $powasMembers;
    public $selectedMonthYear;
    public $noTransactions;
    public $transactions;
    public $monthYear;
    public $totalExpenses = 0;
    public $transactionsList = [];
    public $voucherLists = [];

    protected $pageName = 'expenses-list';

    public function mount($powasID, $powas)
    {
        $this->powas = $powas;
        $this->powasID = $powasID;

        $this->monthYear = Transactions::selectRaw('DATE_FORMAT(transaction_date, "%M %Y") AS month_year, transaction_date')
            ->distinct()
            ->where('powas_id', $this->powasID)
            ->orderByDesc('transaction_date')
            ->get()
            ->pluck('month_year')
            ->unique()
            ->toArray();

        if ($this->monthYear == null || count($this->monthYear) == 0) {
            $this->selectedMonthYear = Carbon::now()->format('F Y');
        } else {
            $this->selectedMonthYear = reset($this->monthYear);
        }

        $this->fetchData2();
    }

    #[On('transaction-added')]
    public function reloadList()
    {
        $this->fetchData2();
    }

    public function fetchData2Bak()
    {
        $date = Carbon::createFromFormat('F Y', $this->selectedMonthYear);

        $this->reset([
            'totalExpenses',
            'transactionsList',
        ]);

        $journalEntryNumbers = Transactions::whereYear('transaction_date', $date->year)
            ->whereMonth('transaction_date', $date->month)
            ->where('powas_id', $this->powasID)
            ->orderBy('transaction_date', 'asc')
            ->orderBy('received_from', 'asc')
            ->get('journal_entry_number');

        foreach ($journalEntryNumbers as $journalEntryNumber) {
            $transaction = Transactions::whereYear('transaction_date', $date->year)
                ->whereMonth('transaction_date', $date->month)
                ->where('journal_entry_number', $journalEntryNumber->journal_entry_number)
                ->where('powas_id', $this->powasID)
                ->where('transaction_side', 'DEBIT')
                ->orderBy('transaction_side', 'asc')
                ->orderBy('transaction_date', 'asc')
                ->orderBy('account_number', 'asc')
                ->get();

            foreach ($transaction as $key => $value) {
                if (ChartOfAccounts::find($value->account_number)->account_type == 'EXPENSE' || $value->account_number == '103' || $value->account_number == '201' || $value->account_number == '202' || $value->account_number == '203' || $value->account_number == '204' || $value->account_number == '205' || $value->account_number == '207') {
                    $voucherNumber = Vouchers::where('trxn_id', $value->trxn_id)->get();
                    // dd($voucherNumber[0]->voucher_id);
                    $this->transactionsList[$journalEntryNumber->journal_entry_number] = $transaction;
                    $this->voucherLists[$value->trxn_id] = $voucherNumber[0]->voucher_id;
                    }
            }
        }
    }

    public function fetchData2()
    {
        $date = Carbon::createFromFormat('F Y', $this->selectedMonthYear);

        $this->reset([
            'totalExpenses',
            'transactionsList',
        ]);

        // Step 1: Get journal entry numbers
        $journalEntryNumbers = Transactions::whereYear('transaction_date', $date->year)
            ->whereMonth('transaction_date', $date->month)
            ->where('powas_id', $this->powasID)
            ->orderBy('transaction_date', 'asc')
            ->orderBy('received_from', 'asc')
            ->pluck('journal_entry_number');

        // Step 2: Fetch transactions based on journal entry numbers
        $transactions = Transactions::whereYear('transaction_date', $date->year)
            ->whereMonth('transaction_date', $date->month)
            ->whereIn('journal_entry_number', $journalEntryNumbers)
            ->where('powas_id', $this->powasID)
            ->where('transaction_side', 'DEBIT')
            ->orderBy('transaction_side', 'asc')
            ->orderBy('transaction_date', 'asc')
            ->orderBy('account_number', 'asc')
            ->get();

        // Step 3: Collect account numbers to filter
        $accountNumbers = $transactions->pluck('account_number')->unique();

        // Step 4: Get the relevant accounts
        $filteredTransactions = $transactions->filter(function ($value) use ($accountNumbers) {
            return ChartOfAccounts::find($value->account_number)->account_type == 'EXPENSE' 
                || in_array($value->account_number, ['103', '201', '202', '203', '204', '205', '207']);
        });

        // Step 5: Process filtered transactions and voucher numbers
        foreach ($filteredTransactions as $value) {
            $voucherNumber = Vouchers::where('trxn_id', $value->trxn_id)->first();
            if ($voucherNumber) {
                $this->transactionsList[$value->journal_entry_number][] = $value;
                $this->voucherLists[$value->trxn_id] = $voucherNumber->voucher_id;
            }
        }
    }

    public function render()
    {
        return view('livewire.transactions.expenses-list');
    }
}
