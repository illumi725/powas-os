<?php

namespace App\Livewire\Transactions;

use App\Models\Transactions;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

class TransactionsList extends Component
{
    public $powas;
    public $powasID;
    public $powasMembers;
    public $selectedMonthYear;
    public $noTransactions;
    public $transactions;
    public $monthYear;
    public $totalDebit = 0;
    public $totalCredit = 0;
    public $transactionsList = [];

    protected $pageName = 'journal-entries';

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

    public function fetchData2()
    {
        $date = Carbon::createFromFormat('F Y', $this->selectedMonthYear);

        $this->reset([
            'totalDebit',
            'totalCredit',
            'transactionsList',
        ]);

        $journalEntryNumbers = Transactions::whereYear('transaction_date', $date->year)
            ->whereMonth('transaction_date', $date->month)
            ->where('powas_id', $this->powasID)
            ->orderBy('transaction_date', 'asc')->get('journal_entry_number');

        $journalEntryCounter = 0;

        $journalEntry = '';

        $datePart = $date->format('m');

        foreach ($journalEntryNumbers as $journalEntryNumber) {
            $transaction = Transactions::whereYear('transaction_date', $date->year)
                ->whereMonth('transaction_date', $date->month)
                ->where('journal_entry_number', $journalEntryNumber->journal_entry_number)
                ->where('powas_id', $this->powasID)
                ->orderBy('transaction_side', 'asc')
                ->orderBy('transaction_date', 'asc')
                ->orderBy('account_number', 'asc')
                ->get();

            // if ($journalEntryCounter + 1 >= 0 && $journalEntryCounter + 1 < 10) {
            //     $journalEntry = $datePart . '-000' . ($journalEntryCounter + 1);
            // } elseif ($journalEntryCounter + 1 >= 10 && $journalEntryCounter + 1 < 100) {
            //     $journalEntry = $datePart . '-00' . ($journalEntryCounter + 1);
            // } elseif ($journalEntryCounter + 1 >= 100 && $journalEntryCounter + 1 < 1000) {
            //     $journalEntry = $datePart . '-0' . ($journalEntryCounter + 1);
            // } else {
            //     $journalEntry = $datePart . ($journalEntryCounter + 1);
            // }

            // foreach ($transaction as $key => $value) {
            //     $value->journal_entry_number = $journalEntry;
            //     $value->save();
            // }

            // $journalEntryCounter++;

            $this->transactionsList[$journalEntryNumber->journal_entry_number] = $transaction;
        }
    }

    public function render()
    {
        return view('livewire.transactions.transactions-list');
    }
}
