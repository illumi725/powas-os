<?php

namespace App\Livewire\Accounting;

use App\Events\ActionLogger;
use App\Factory\CustomNumberFactory;
use App\Models\BankSlipPictures;
use App\Models\ChartOfAccounts;
use App\Models\IssuedReceipts;
use App\Models\PowasMembers;
use App\Models\Transactions;
use App\Models\User;
use App\Models\VoucherExpenseReceipts;
use App\Models\Vouchers;
use App\Models\VouchersParticulars;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class AddTransaction extends Component
{
    use WithFileUploads;
    public $powasID;
    public $powas;
    public $showingAddTransactionModal = false;
    public $showingConfirmAddTrasactionModal = false;
    public $transactionType = '';
    public $accountName = '';
    public $transactionAmount = '';
    public $transactionDescription = '';
    public $receiptImage;
    public $transactionDate;
    public $receiveFromOrPaidTo;
    public $powasOfficers;
    public $accountNameList = [];
    public $preparedBy = null;
    public $checkedBy = null;
    public $approvedBy = null;
    public $toPrintVoucher;
    public $showingPrintVoucherConfirmation = false;
    public $lockAmountField = false;
    public $trxnIDs = [];
    public $printIDs = [];
    public $printing = false;
    public $receiptNumber;

    public function mount($powasID, $powas)
    {
        $this->powas = $powas;
        $this->powasID = $powasID;

        $this->powasOfficers = User::with('roles')
            ->join('user_infos', 'users.user_id', '=', 'user_infos.user_id')
            ->where('users.powas_id', $this->powasID)
            ->where(function ($query) {
                $query->where('account_status', 'ACTIVE')
                    ->orWhere('account_status', 'INACTIVE');
            })
            ->get();

        if (count($this->powasOfficers) != 0) {
            foreach ($this->powasOfficers as $key => $value) {
                if ($value->hasRole('secretary')) {
                    $this->preparedBy = $value->user_id;
                }
                if ($value->hasRole('treasurer')) {
                    $this->checkedBy = $value->user_id;
                }
                if ($value->hasRole('president')) {
                    $this->approvedBy = $value->user_id;
                }
            }
        }
    }

    public function showAddTransactionModal()
    {
        $this->reset([
            'transactionType',
            'accountName',
            'transactionAmount',
            'transactionDescription',
            'receiptImage',
            'transactionDate',
            'receiveFromOrPaidTo',
            'lockAmountField',
        ]);
        $this->transactionDate = Carbon::now()->format('Y-m-d');
        $this->showingAddTransactionModal = true;
    }

    public function showConfirmAddTransactionModal()
    {
        $this->validate([
            'transactionType' => 'required',
            'accountName' => 'required',
            'transactionAmount' => 'required',
            'transactionDate' => 'required',
            'transactionDescription' => 'required',
        ]);

        if ($this->transactionType == 'expenses' || $this->transactionType == 'payments') {
            $this->validate([
                'transactionDescription' => 'required',
                'receiptImage' => 'required|image|max:2048',
            ]);
        };

        $this->showingConfirmAddTrasactionModal = true;
        $this->showingAddTransactionModal = false;
    }

    public function updateAccountTypeSelection()
    {
        $this->reset([
            'accountName',
            'accountNameList',
        ]);

        $accountNames = [];

        if ($this->transactionType == 'receipts') {
            $accountNumbers = [202, 203, 204, 205, 206, 207, 304, 406, 408];
            foreach ($accountNumbers as $value) {
                $chartOfAccount =  ChartOfAccounts::find($value);
                $accountNames[$value] = [
                    'account_name' => $chartOfAccount->account_name,
                    'alias' => $chartOfAccount->alias,
                ];
            }

            $this->accountNameList = $accountNames;
        }

        if ($this->transactionType == 'payments') {
            $accountNumbers = [103, 201, 202, 203, 204, 205, 206, 207, 208, 303, 304, 305];
            foreach ($accountNumbers as $value) {
                $chartOfAccount =  ChartOfAccounts::find($value);
                $accountNames[$value] = [
                    'account_name' => $chartOfAccount->account_name,
                    'alias' => $chartOfAccount->alias,
                ];
            }

            $this->accountNameList = $accountNames;
        }

        if ($this->transactionType == 'expenses') {
            $accountNumbers = [501, 502, 503, 504, 505, 506, 507, 508, 509, 510, 511, 512];
            foreach ($accountNumbers as $value) {
                $chartOfAccount =  ChartOfAccounts::find($value);
                $accountNames[$value] = [
                    'account_name' => $chartOfAccount->account_name,
                    'alias' => $chartOfAccount->alias,
                ];
            }

            $this->accountNameList = $accountNames;
        }
    }

    // Miscellaneous Income
    public function transact406()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();
        $normalBalance = '';

        $description = 'MISCELLANEOUS INCOME received from ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
        $normalBalance = 'CREDIT';

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // Miscellaneous Income
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '406',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $normalBalance = '';
        $description = 'Cash received from ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);
        $normalBalance = 'DEBIT';

        $this->reset([
            'trxnIDs',
            'printIDs',
        ]);

        $this->trxnIDs[] = $newTransactionID;
        $printNewID = CustomNumberFactory::getRandomID();
        $this->receiptNumber = CustomNumberFactory::receipt($this->powasID, $this->transactionDate);

        IssuedReceipts::create([
            'print_id' => $printNewID,
            'receipt_number' => $this->receiptNumber,
            'trxn_id' => $newTransactionID,
            'powas_id' => $this->powasID,
            'description' => strtoupper($this->transactionDescription),
            'transaction_date' => $this->transactionDate,
        ]);

        $this->printIDs[] = $printNewID;

        $this->printing = true;

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        $this->dispatch('transaction-added');
    }

    // Past Due Collection
    public function transact408()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();
        $normalBalance = '';

        $description = 'PAST DUE COLLECTION received from ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
        $normalBalance = 'CREDIT';

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // Past Due Collection
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '408',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $normalBalance = '';
        $description = 'Cash received from ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);
        $normalBalance = 'DEBIT';

        $this->reset([
            'trxnIDs',
            'printIDs',
        ]);

        $this->trxnIDs[] = $newTransactionID;
        $printNewID = CustomNumberFactory::getRandomID();
        $this->receiptNumber = CustomNumberFactory::receipt($this->powasID, $this->transactionDate);

        IssuedReceipts::create([
            'print_id' => $printNewID,
            'receipt_number' => $this->receiptNumber,
            'trxn_id' => $newTransactionID,
            'powas_id' => $this->powasID,
            'description' => strtoupper($this->transactionDescription),
            'transaction_date' => $this->transactionDate,
        ]);

        $this->printIDs[] = $printNewID;

        $this->printing = true;

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        $this->dispatch('transaction-added');
    }

    // Accounts Payable
    public function transact202()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();
        $normalBalance = '';

        if ($this->transactionType == 'receipts') {
            $description = 'Accounts Payable received from ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
            $normalBalance = 'CREDIT';
        } elseif ($this->transactionType == 'payments') {
            $description = 'Accounts Payable paid to ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
            $normalBalance = 'DEBIT';
        }

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // Accounts Payable
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '202',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $normalBalance = '';

        if ($this->transactionType == 'receipts') {
            $description = 'Cash received from ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);
            $normalBalance = 'DEBIT';

            $this->reset([
                'trxnIDs',
                'printIDs',
            ]);

            $this->trxnIDs[] = $newTransactionID;
            $printNewID = CustomNumberFactory::getRandomID();
            $this->receiptNumber = CustomNumberFactory::receipt($this->powasID, $this->transactionDate);

            IssuedReceipts::create([
                'print_id' => $printNewID,
                'receipt_number' => $this->receiptNumber,
                'trxn_id' => $newTransactionID,
                'powas_id' => $this->powasID,
                'description' => strtoupper($this->transactionDescription),
                'transaction_date' => $this->transactionDate,
            ]);

            $this->printIDs[] = $printNewID;

            $this->printing = true;
        } elseif ($this->transactionType == 'payments') {
            $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);
            $normalBalance = 'CREDIT';

            // For Voucher
            $voucherID = CustomNumberFactory::getRandomID();
            $this->toPrintVoucher = $voucherID;
            $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

            Vouchers::create([
                'voucher_id' => $voucherID,
                'voucher_number' => $voucherNumber,
                'powas_id' => $this->powasID,
                'recorded_by' => Auth::user()->user_id,
                'trxn_id' => $newTransactionID,
                'amount' => $this->transactionAmount,
                'received_by' => strtoupper($this->receiveFromOrPaidTo),
                'prepared_by' => $this->preparedBy,
                'checked_by' => $this->checkedBy,
                'approved_by' => $this->approvedBy,
                'voucher_date' => $this->transactionDate,
            ]);

            // For Voucher Particulars
            VouchersParticulars::create([
                'voucher_id' => $voucherID,
                'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
                'description' => strtoupper($this->transactionDescription),
            ]);

            $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

            // For Voucher Receipt Image
            VoucherExpenseReceipts::create([
                'voucher_id' => $voucherID,
                'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
            ]);
        }

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        if ($this->transactionType == 'payments' || $this->transactionType == 'expenses') {
            $this->showingPrintVoucherConfirmation = true;
        }
        $this->dispatch('transaction-added');
    }

    // Notes Payable
    public function transact203()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();
        $normalBalance = '';

        if ($this->transactionType == 'receipts') {
            $description = 'Notes Payable received from ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
            $normalBalance = 'CREDIT';
        } elseif ($this->transactionType == 'payments') {
            $description = 'Notes Payable paid to ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
            $normalBalance = 'DEBIT';
        }

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // Notes Payable
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '203',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $normalBalance = '';

        if ($this->transactionType == 'receipts') {
            $description = 'Cash received from ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);
            $normalBalance = 'DEBIT';

            $this->reset([
                'trxnIDs',
                'printIDs',
            ]);

            $this->trxnIDs[] = $newTransactionID;
            $printNewID = CustomNumberFactory::getRandomID();
            $this->receiptNumber = CustomNumberFactory::receipt($this->powasID, $this->transactionDate);

            IssuedReceipts::create([
                'print_id' => $printNewID,
                'receipt_number' => $this->receiptNumber,
                'trxn_id' => $newTransactionID,
                'powas_id' => $this->powasID,
                'description' => strtoupper($this->transactionDescription),
                'transaction_date' => $this->transactionDate,
            ]);

            $this->printIDs[] = $printNewID;

            $this->printing = true;
        } elseif ($this->transactionType == 'payments') {
            $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);
            $normalBalance = 'CREDIT';

            // For Voucher
            $voucherID = CustomNumberFactory::getRandomID();
            $this->toPrintVoucher = $voucherID;
            $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

            Vouchers::create([
                'voucher_id' => $voucherID,
                'voucher_number' => $voucherNumber,
                'powas_id' => $this->powasID,
                'recorded_by' => Auth::user()->user_id,
                'trxn_id' => $newTransactionID,
                'amount' => $this->transactionAmount,
                'received_by' => strtoupper($this->receiveFromOrPaidTo),
                'prepared_by' => $this->preparedBy,
                'checked_by' => $this->checkedBy,
                'approved_by' => $this->approvedBy,
                'voucher_date' => $this->transactionDate,
            ]);

            // For Voucher Particulars
            VouchersParticulars::create([
                'voucher_id' => $voucherID,
                'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
                'description' => strtoupper($this->transactionDescription),
            ]);

            $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

            // For Voucher Receipt Image
            VoucherExpenseReceipts::create([
                'voucher_id' => $voucherID,
                'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
            ]);
        }

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        if ($this->transactionType == 'payments' || $this->transactionType == 'expenses') {
            $this->showingPrintVoucherConfirmation = true;
        }
        $this->dispatch('transaction-added');
    }

    // Utilities Payable
    public function transact204()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();
        $normalBalance = '';

        if ($this->transactionType == 'receipts') {
            $description = 'Utilities Payable received from ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
            $normalBalance = 'CREDIT';
        } elseif ($this->transactionType == 'payments') {
            $description = 'Utilities Payable paid to ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
            $normalBalance = 'DEBIT';
        }

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // Utilities Payable
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '204',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        if ($this->transactionType == 'receipts') {
            $description = ChartOfAccounts::find($this->accountName)->account_name . ' for ' . strtoupper($this->transactionDescription);

            $newTransactionID = CustomNumberFactory::getRandomID();

            // For Utilities Expense
            Transactions::create([
                'trxn_id' => $newTransactionID,
                'account_number' => '506',
                'description' => $description,
                'journal_entry_number' => $journalEntryNumber,
                'amount' => $this->transactionAmount,
                'transaction_side' => 'DEBIT',
                'received_from' => strtoupper($this->receiveFromOrPaidTo),
                'paid_to' => strtoupper($this->receiveFromOrPaidTo),
                'member_id' => $memberID,
                'powas_id' => $this->powasID,
                'recorded_by_id' => Auth::user()->user_id,
                'transaction_date' => $this->transactionDate,
            ]);

            $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

            ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);
        } elseif ($this->transactionType == 'payments') {
            $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);

            $newTransactionID = CustomNumberFactory::getRandomID();

            // For Voucher
            $voucherID = CustomNumberFactory::getRandomID();
            $this->toPrintVoucher = $voucherID;
            $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

            Vouchers::create([
                'voucher_id' => $voucherID,
                'voucher_number' => $voucherNumber,
                'powas_id' => $this->powasID,
                'recorded_by' => Auth::user()->user_id,
                'trxn_id' => $newTransactionID,
                'amount' => $this->transactionAmount,
                'received_by' => strtoupper($this->receiveFromOrPaidTo),
                'prepared_by' => $this->preparedBy,
                'checked_by' => $this->checkedBy,
                'approved_by' => $this->approvedBy,
                'voucher_date' => $this->transactionDate,
            ]);

            // For Voucher Particulars
            VouchersParticulars::create([
                'voucher_id' => $voucherID,
                'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
                'description' => strtoupper($this->transactionDescription),
            ]);

            $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

            // For Voucher Receipt Image
            VoucherExpenseReceipts::create([
                'voucher_id' => $voucherID,
                'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
            ]);

            Transactions::create([
                'trxn_id' => $newTransactionID,
                'account_number' => '101',
                'description' => $description,
                'journal_entry_number' => $journalEntryNumber,
                'amount' => $this->transactionAmount,
                'transaction_side' => 'CREDIT',
                'received_from' => strtoupper($this->receiveFromOrPaidTo),
                'paid_to' => strtoupper($this->receiveFromOrPaidTo),
                'member_id' => $memberID,
                'powas_id' => $this->powasID,
                'recorded_by_id' => Auth::user()->user_id,
                'transaction_date' => $this->transactionDate,
            ]);

            $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

            ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);
        }

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        if ($this->transactionType == 'payments' || $this->transactionType == 'expenses') {
            $this->showingPrintVoucherConfirmation = true;
        }
        $this->dispatch('transaction-added');
    }

    // Tax Payable
    public function transact205()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();
        $normalBalance = '';

        if ($this->transactionType == 'receipts') {
            $description = 'Tax Payable received from ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
            $normalBalance = 'CREDIT';
        } elseif ($this->transactionType == 'payments') {
            $description = 'Tax Payable paid to ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
            $normalBalance = 'DEBIT';
        }

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // Tax Payable
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '205',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        if ($this->transactionType == 'receipts') {
            $description = ChartOfAccounts::find($this->accountName)->account_name . ' for ' . strtoupper($this->transactionDescription);

            $newTransactionID = CustomNumberFactory::getRandomID();

            // For Taxes and Licenses Expense
            Transactions::create([
                'trxn_id' => $newTransactionID,
                'account_number' => '504',
                'description' => $description,
                'journal_entry_number' => $journalEntryNumber,
                'amount' => $this->transactionAmount,
                'transaction_side' => 'DEBIT',
                'received_from' => strtoupper($this->receiveFromOrPaidTo),
                'paid_to' => strtoupper($this->receiveFromOrPaidTo),
                'member_id' => $memberID,
                'powas_id' => $this->powasID,
                'recorded_by_id' => Auth::user()->user_id,
                'transaction_date' => $this->transactionDate,
            ]);

            $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

            ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);
        } elseif ($this->transactionType == 'payments') {
            $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);

            $newTransactionID = CustomNumberFactory::getRandomID();

            // For Voucher
            $voucherID = CustomNumberFactory::getRandomID();
            $this->toPrintVoucher = $voucherID;
            $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

            Vouchers::create([
                'voucher_id' => $voucherID,
                'voucher_number' => $voucherNumber,
                'powas_id' => $this->powasID,
                'recorded_by' => Auth::user()->user_id,
                'trxn_id' => $newTransactionID,
                'amount' => $this->transactionAmount,
                'received_by' => strtoupper($this->receiveFromOrPaidTo),
                'prepared_by' => $this->preparedBy,
                'checked_by' => $this->checkedBy,
                'approved_by' => $this->approvedBy,
                'voucher_date' => $this->transactionDate,
            ]);

            // For Voucher Particulars
            VouchersParticulars::create([
                'voucher_id' => $voucherID,
                'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
                'description' => strtoupper($this->transactionDescription),
            ]);

            $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

            // For Voucher Receipt Image
            VoucherExpenseReceipts::create([
                'voucher_id' => $voucherID,
                'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
            ]);

            Transactions::create([
                'trxn_id' => $newTransactionID,
                'account_number' => '101',
                'description' => $description,
                'journal_entry_number' => $journalEntryNumber,
                'amount' => $this->transactionAmount,
                'transaction_side' => 'CREDIT',
                'received_from' => strtoupper($this->receiveFromOrPaidTo),
                'paid_to' => strtoupper($this->receiveFromOrPaidTo),
                'member_id' => $memberID,
                'powas_id' => $this->powasID,
                'recorded_by_id' => Auth::user()->user_id,
                'transaction_date' => $this->transactionDate,
            ]);

            $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

            ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);
        }

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        if ($this->transactionType == 'payments' || $this->transactionType == 'expenses') {
            $this->showingPrintVoucherConfirmation = true;
        }
        $this->dispatch('transaction-added');
    }

    // Member's Advance Payment
    public function transact206()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();
        $normalBalance = '';

        if ($this->transactionType == 'receipts') {
            $description = 'MEMBER\'S ADVANCED PAYMENT received from ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
            $normalBalance = 'CREDIT';
        } elseif ($this->transactionType == 'payments') {
            $description = 'Debited MEMBER\'S ADVANCED PAYMENT to ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
            $normalBalance = 'DEBIT';
        }

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // Member's Advanced Payment
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '206',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $normalBalance = '';

        if ($this->transactionType == 'receipts') {
            $description = 'Cash received from ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);
            $normalBalance = 'DEBIT';

            $this->reset([
                'trxnIDs',
                'printIDs',
            ]);

            $this->trxnIDs[] = $newTransactionID;
            $printNewID = CustomNumberFactory::getRandomID();
            $this->receiptNumber = CustomNumberFactory::receipt($this->powasID, $this->transactionDate);

            IssuedReceipts::create([
                'print_id' => $printNewID,
                'receipt_number' => $this->receiptNumber,
                'trxn_id' => $newTransactionID,
                'powas_id' => $this->powasID,
                'description' => strtoupper($this->transactionDescription),
                'transaction_date' => $this->transactionDate,
            ]);

            $this->printIDs[] = $printNewID;

            $this->printing = true;
        } elseif ($this->transactionType == 'payments') {
            $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);
            $normalBalance = 'CREDIT';

            // For Voucher
            $voucherID = CustomNumberFactory::getRandomID();
            $this->toPrintVoucher = $voucherID;
            $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

            Vouchers::create([
                'voucher_id' => $voucherID,
                'voucher_number' => $voucherNumber,
                'powas_id' => $this->powasID,
                'recorded_by' => Auth::user()->user_id,
                'trxn_id' => $newTransactionID,
                'amount' => $this->transactionAmount,
                'received_by' => strtoupper($this->receiveFromOrPaidTo),
                'prepared_by' => $this->preparedBy,
                'checked_by' => $this->checkedBy,
                'approved_by' => $this->approvedBy,
                'voucher_date' => $this->transactionDate,
            ]);

            // For Voucher Particulars
            VouchersParticulars::create([
                'voucher_id' => $voucherID,
                'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
                'description' => strtoupper($this->transactionDescription),
            ]);

            $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

            // For Voucher Receipt Image
            VoucherExpenseReceipts::create([
                'voucher_id' => $voucherID,
                'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
            ]);
        }

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        if ($this->transactionType == 'payments' || $this->transactionType == 'expenses') {
            $this->showingPrintVoucherConfirmation = true;
        }
        $this->dispatch('transaction-added');
    }

    // Damayan
    public function transact207()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();
        $normalBalance = '';

        if ($this->transactionType == 'receipts') {
            $description = 'Damayan Collection received from ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
            $normalBalance = 'CREDIT';
        } elseif ($this->transactionType == 'payments') {
            $description = 'Damayan Disbursement to ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
            $normalBalance = 'DEBIT';
        }

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // Damayan
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '207',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $normalBalance = '';

        if ($this->transactionType == 'receipts') {
            $description = 'Cash received from ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);
            $normalBalance = 'DEBIT';

            $this->reset([
                'trxnIDs',
                'printIDs',
            ]);

            $this->trxnIDs[] = $newTransactionID;
            $printNewID = CustomNumberFactory::getRandomID();
            $this->receiptNumber = CustomNumberFactory::receipt($this->powasID, $this->transactionDate);

            IssuedReceipts::create([
                'print_id' => $printNewID,
                'receipt_number' => $this->receiptNumber,
                'trxn_id' => $newTransactionID,
                'powas_id' => $this->powasID,
                'description' => strtoupper($this->transactionDescription),
                'transaction_date' => $this->transactionDate,
            ]);

            $this->printIDs[] = $printNewID;

            $this->printing = true;
        } elseif ($this->transactionType == 'payments') {
            $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);
            $normalBalance = 'CREDIT';

            // For Voucher
            $voucherID = CustomNumberFactory::getRandomID();
            $this->toPrintVoucher = $voucherID;
            $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

            Vouchers::create([
                'voucher_id' => $voucherID,
                'voucher_number' => $voucherNumber,
                'powas_id' => $this->powasID,
                'recorded_by' => Auth::user()->user_id,
                'trxn_id' => $newTransactionID,
                'amount' => $this->transactionAmount,
                'received_by' => strtoupper($this->receiveFromOrPaidTo),
                'prepared_by' => $this->preparedBy,
                'checked_by' => $this->checkedBy,
                'approved_by' => $this->approvedBy,
                'voucher_date' => $this->transactionDate,
            ]);

            // For Voucher Particulars
            VouchersParticulars::create([
                'voucher_id' => $voucherID,
                'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
                'description' => strtoupper($this->transactionDescription),
            ]);

            $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

            // For Voucher Receipt Image
            VoucherExpenseReceipts::create([
                'voucher_id' => $voucherID,
                'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
            ]);
        }

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        if ($this->transactionType == 'payments' || $this->transactionType == 'expenses') {
            $this->showingPrintVoucherConfirmation = true;
        }
        $this->dispatch('transaction-added');
    }

    // Fund Transfer
    public function transact304()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();
        $normalBalance = '';

        if ($this->transactionType == 'receipts') {
            $description = 'Fund Transfer received from ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
            $normalBalance = 'CREDIT';
        } elseif ($this->transactionType == 'payments') {
            $description = 'Fund Transfer paid to ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
            $normalBalance = 'DEBIT';
        }

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // Fund Transfer
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '304',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $normalBalance = '';

        if ($this->transactionType == 'receipts') {
            $description = 'Cash Fund Transfer received from ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);
            $normalBalance = 'DEBIT';

            $this->reset([
                'trxnIDs',
                'printIDs',
            ]);

            $this->trxnIDs[] = $newTransactionID;
            $printNewID = CustomNumberFactory::getRandomID();
            $this->receiptNumber = CustomNumberFactory::receipt($this->powasID, $this->transactionDate);

            IssuedReceipts::create([
                'print_id' => $printNewID,
                'receipt_number' => $this->receiptNumber,
                'trxn_id' => $newTransactionID,
                'powas_id' => $this->powasID,
                'description' => strtoupper($this->transactionDescription),
                'transaction_date' => $this->transactionDate,
            ]);

            $this->printIDs[] = $printNewID;

            $this->printing = true;
        } elseif ($this->transactionType == 'payments') {
            $description = 'Cash Fund Transfer paid to ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);
            $normalBalance = 'CREDIT';

            // For Voucher
            $voucherID = CustomNumberFactory::getRandomID();
            $this->toPrintVoucher = $voucherID;
            $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

            Vouchers::create([
                'voucher_id' => $voucherID,
                'voucher_number' => $voucherNumber,
                'powas_id' => $this->powasID,
                'recorded_by' => Auth::user()->user_id,
                'trxn_id' => $newTransactionID,
                'amount' => $this->transactionAmount,
                'received_by' => strtoupper($this->receiveFromOrPaidTo),
                'prepared_by' => $this->preparedBy,
                'checked_by' => $this->checkedBy,
                'approved_by' => $this->approvedBy,
                'voucher_date' => $this->transactionDate,
            ]);

            // For Voucher Particulars
            VouchersParticulars::create([
                'voucher_id' => $voucherID,
                'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
                'description' => strtoupper($this->transactionDescription),
            ]);

            $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

            // For Voucher Receipt Image
            VoucherExpenseReceipts::create([
                'voucher_id' => $voucherID,
                'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
            ]);
        }

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        if ($this->transactionType == 'payments' || $this->transactionType == 'expenses') {
            $this->showingPrintVoucherConfirmation = true;
        }
        $this->dispatch('transaction-added');
    }

    // Other Payments
    public function transact303()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();
        $normalBalance = '';

        if ($this->transactionType == 'receipts') {
            $description = 'Other Payments received from ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
            $normalBalance = 'CREDIT';
        } elseif ($this->transactionType == 'payments') {
            $description = 'Other Payments paid to ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;
            $normalBalance = 'DEBIT';
        }

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // Other Payments
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '303',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $normalBalance = '';

        if ($this->transactionType == 'receipts') {
            $description = 'Cash Other Payments received from ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);
            $normalBalance = 'DEBIT';

            $this->reset([
                'trxnIDs',
                'printIDs',
            ]);

            $this->trxnIDs[] = $newTransactionID;
            $printNewID = CustomNumberFactory::getRandomID();
            $this->receiptNumber = CustomNumberFactory::receipt($this->powasID, $this->transactionDate);

            IssuedReceipts::create([
                'print_id' => $printNewID,
                'receipt_number' => $this->receiptNumber,
                'trxn_id' => $newTransactionID,
                'powas_id' => $this->powasID,
                'description' => strtoupper($this->transactionDescription),
                'transaction_date' => $this->transactionDate,
            ]);

            $this->printIDs[] = $printNewID;

            $this->printing = true;
        } elseif ($this->transactionType == 'payments') {
            $description = 'Cash Other Payments paid to ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);
            $normalBalance = 'CREDIT';

            // For Voucher
            $voucherID = CustomNumberFactory::getRandomID();
            $this->toPrintVoucher = $voucherID;
            $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

            Vouchers::create([
                'voucher_id' => $voucherID,
                'voucher_number' => $voucherNumber,
                'powas_id' => $this->powasID,
                'recorded_by' => Auth::user()->user_id,
                'trxn_id' => $newTransactionID,
                'amount' => $this->transactionAmount,
                'received_by' => strtoupper($this->receiveFromOrPaidTo),
                'prepared_by' => $this->preparedBy,
                'checked_by' => $this->checkedBy,
                'approved_by' => $this->approvedBy,
                'voucher_date' => $this->transactionDate,
            ]);

            // For Voucher Particulars
            VouchersParticulars::create([
                'voucher_id' => $voucherID,
                'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
                'description' => strtoupper($this->transactionDescription),
            ]);

            $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

            // For Voucher Receipt Image
            VoucherExpenseReceipts::create([
                'voucher_id' => $voucherID,
                'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
            ]);
        }

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => $normalBalance,
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        if ($this->transactionType == 'payments' || $this->transactionType == 'expenses') {
            $this->showingPrintVoucherConfirmation = true;
        }
        $this->dispatch('transaction-added');
    }

    // Member's Refund
    public function transact305()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();

        $description = 'Member\'s Refund paid to ' . $this->receiveFromOrPaidTo . ' for ' . $this->transactionDescription;

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // Member's Refund
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '305',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'DEBIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $normalBalance = '';

        // For Voucher
        $voucherID = CustomNumberFactory::getRandomID();
        $this->toPrintVoucher = $voucherID;
        $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

        Vouchers::create([
            'voucher_id' => $voucherID,
            'voucher_number' => $voucherNumber,
            'powas_id' => $this->powasID,
            'recorded_by' => Auth::user()->user_id,
            'trxn_id' => $newTransactionID,
            'amount' => $this->transactionAmount,
            'received_by' => strtoupper($this->receiveFromOrPaidTo),
            'prepared_by' => $this->preparedBy,
            'checked_by' => $this->checkedBy,
            'approved_by' => $this->approvedBy,
            'voucher_date' => $this->transactionDate,
        ]);

        // For Voucher Particulars
        VouchersParticulars::create([
            'voucher_id' => $voucherID,
            'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
            'description' => strtoupper($this->transactionDescription),
        ]);

        $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

        // For Voucher Receipt Image
        VoucherExpenseReceipts::create([
            'voucher_id' => $voucherID,
            'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
        ]);

        // For Cash
        $description = 'Cash refunded to ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);
        $newTransactionID = CustomNumberFactory::getRandomID();

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'CREDIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $memberInfo = PowasMembers::find($memberID);

        $memberInfo->member_status = 'REFUNDED';
        $memberInfo->save();

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        if ($this->transactionType == 'payments' || $this->transactionType == 'expenses') {
            $this->showingPrintVoucherConfirmation = true;
        }
        $this->dispatch('transaction-added');
    }

    // Member's Micro-Savings Debit
    public function transact201()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();

        $description = 'Debit from ' . strtoupper(ChartOfAccounts::find(201)->account_name) . ' for ' . strtoupper($this->transactionDescription);

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // For Member's Micro-Savings
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '201',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'DEBIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        // For Voucher
        $voucherID = CustomNumberFactory::getRandomID();
        $this->toPrintVoucher = $voucherID;
        $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

        Vouchers::create([
            'voucher_id' => $voucherID,
            'voucher_number' => $voucherNumber,
            'powas_id' => $this->powasID,
            'recorded_by' => Auth::user()->user_id,
            'trxn_id' => $newTransactionID,
            'amount' => $this->transactionAmount,
            'received_by' => strtoupper($this->receiveFromOrPaidTo),
            'prepared_by' => $this->preparedBy,
            'checked_by' => $this->checkedBy,
            'approved_by' => $this->approvedBy,
            'voucher_date' => $this->transactionDate,
        ]);

        // For Voucher Particulars
        VouchersParticulars::create([
            'voucher_id' => $voucherID,
            'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
            'description' => strtoupper($this->transactionDescription),
        ]);

        $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

        // For Voucher Receipt Image
        VoucherExpenseReceipts::create([
            'voucher_id' => $voucherID,
            'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
        ]);

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();
        $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for '  . strtoupper($this->transactionDescription);

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'CREDIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->showingConfirmAddTrasactionModal = false;

        if ($this->transactionType == 'payments' || $this->transactionType == 'expenses') {
            $this->showingPrintVoucherConfirmation = true;
        }

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);

        $this->dispatch('transaction-added');
    }

    // Properties and Equipment
    public function transact103()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();

        $description = 'For Asset Acquisation from ' . strtoupper($this->receiveFromOrPaidTo);

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // For Assets Acquisation
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '103',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'DEBIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        // For Voucher
        $voucherID = CustomNumberFactory::getRandomID();
        $this->toPrintVoucher = $voucherID;
        $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

        Vouchers::create([
            'voucher_id' => $voucherID,
            'voucher_number' => $voucherNumber,
            'powas_id' => $this->powasID,
            'recorded_by' => Auth::user()->user_id,
            'trxn_id' => $newTransactionID,
            'amount' => $this->transactionAmount,
            'received_by' => strtoupper($this->receiveFromOrPaidTo),
            'prepared_by' => $this->preparedBy,
            'checked_by' => $this->checkedBy,
            'approved_by' => $this->approvedBy,
            'voucher_date' => $this->transactionDate,
        ]);

        // For Voucher Particulars
        VouchersParticulars::create([
            'voucher_id' => $voucherID,
            'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
            'description' => strtoupper($this->transactionDescription),
        ]);

        $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

        // For Voucher Receipt Image
        VoucherExpenseReceipts::create([
            'voucher_id' => $voucherID,
            'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
        ]);

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();
        $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for Assets Acquisation';

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'CREDIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        if ($this->transactionType == 'payments' || $this->transactionType == 'expenses') {
            $this->showingPrintVoucherConfirmation = true;
        }
        $this->dispatch('transaction-added');
    }

    // Utilities Expense
    public function transact506()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();

        $description = ChartOfAccounts::find($this->accountName)->account_name . ' for ' . strtoupper($this->transactionDescription);

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // For Utilities Expense
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '506',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'DEBIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        // For Voucher
        $voucherID = CustomNumberFactory::getRandomID();
        $this->toPrintVoucher = $voucherID;
        $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

        Vouchers::create([
            'voucher_id' => $voucherID,
            'voucher_number' => $voucherNumber,
            'powas_id' => $this->powasID,
            'recorded_by' => Auth::user()->user_id,
            'trxn_id' => $newTransactionID,
            'amount' => $this->transactionAmount,
            'received_by' => strtoupper($this->receiveFromOrPaidTo),
            'prepared_by' => $this->preparedBy,
            'checked_by' => $this->checkedBy,
            'approved_by' => $this->approvedBy,
            'voucher_date' => $this->transactionDate,
        ]);

        // For Voucher Particulars
        VouchersParticulars::create([
            'voucher_id' => $voucherID,
            'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
            'description' => strtoupper($this->transactionDescription),
        ]);

        $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

        // For Voucher Receipt Image
        VoucherExpenseReceipts::create([
            'voucher_id' => $voucherID,
            'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
        ]);

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();
        $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for ' . ChartOfAccounts::find($this->accountName)->account_name .  ' - ' . strtoupper($this->transactionDescription);

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'CREDIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        $this->showingPrintVoucherConfirmation = true;
        $this->dispatch('transaction-added');
    }

    // Supplies and Materials
    public function transact501()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();

        $description = ChartOfAccounts::find($this->accountName)->account_name . ' for ' . strtoupper($this->transactionDescription);

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // For Supplies and Materials
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '501',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'DEBIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        // For Voucher
        $voucherID = CustomNumberFactory::getRandomID();
        $this->toPrintVoucher = $voucherID;
        $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

        Vouchers::create([
            'voucher_id' => $voucherID,
            'voucher_number' => $voucherNumber,
            'powas_id' => $this->powasID,
            'recorded_by' => Auth::user()->user_id,
            'trxn_id' => $newTransactionID,
            'amount' => $this->transactionAmount,
            'received_by' => strtoupper($this->receiveFromOrPaidTo),
            'prepared_by' => $this->preparedBy,
            'checked_by' => $this->checkedBy,
            'approved_by' => $this->approvedBy,
            'voucher_date' => $this->transactionDate,
        ]);

        // For Voucher Particulars
        VouchersParticulars::create([
            'voucher_id' => $voucherID,
            'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
            'description' => strtoupper($this->transactionDescription),
        ]);

        $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

        // For Voucher Receipt Image
        VoucherExpenseReceipts::create([
            'voucher_id' => $voucherID,
            'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
        ]);

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();
        $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for ' . ChartOfAccounts::find($this->accountName)->account_name .  ' - ' . strtoupper($this->transactionDescription);

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'CREDIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        $this->showingPrintVoucherConfirmation = true;
        $this->dispatch('transaction-added');
    }

    // Meter Installer Allowances
    public function transact502()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();

        $description = ChartOfAccounts::find($this->accountName)->account_name . ' for ' . strtoupper($this->transactionDescription);

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // For Meter Installer Allowances
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '502',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'DEBIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        // For Voucher
        $voucherID = CustomNumberFactory::getRandomID();
        $this->toPrintVoucher = $voucherID;
        $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

        Vouchers::create([
            'voucher_id' => $voucherID,
            'voucher_number' => $voucherNumber,
            'powas_id' => $this->powasID,
            'recorded_by' => Auth::user()->user_id,
            'trxn_id' => $newTransactionID,
            'amount' => $this->transactionAmount,
            'received_by' => strtoupper($this->receiveFromOrPaidTo),
            'prepared_by' => $this->preparedBy,
            'checked_by' => $this->checkedBy,
            'approved_by' => $this->approvedBy,
            'voucher_date' => $this->transactionDate,
        ]);

        // For Voucher Particulars
        VouchersParticulars::create([
            'voucher_id' => $voucherID,
            'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
            'description' => strtoupper($this->transactionDescription),
        ]);

        $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

        // For Voucher Receipt Image
        VoucherExpenseReceipts::create([
            'voucher_id' => $voucherID,
            'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
        ]);

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();
        $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for ' . ChartOfAccounts::find($this->accountName)->account_name .  ' - ' . strtoupper($this->transactionDescription);

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'CREDIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        $this->showingPrintVoucherConfirmation = true;
        $this->dispatch('transaction-added');
    }

    // Collector/Reader/Tank Cleaner Allowances
    public function transact503()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();

        $description = ChartOfAccounts::find($this->accountName)->account_name . ' for ' . strtoupper($this->transactionDescription);

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // For Collector/Reader/Tank Cleaner Allowances
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '503',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'DEBIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        // For Voucher
        $voucherID = CustomNumberFactory::getRandomID();
        $this->toPrintVoucher = $voucherID;
        $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

        Vouchers::create([
            'voucher_id' => $voucherID,
            'voucher_number' => $voucherNumber,
            'powas_id' => $this->powasID,
            'recorded_by' => Auth::user()->user_id,
            'trxn_id' => $newTransactionID,
            'amount' => $this->transactionAmount,
            'received_by' => strtoupper($this->receiveFromOrPaidTo),
            'prepared_by' => $this->preparedBy,
            'checked_by' => $this->checkedBy,
            'approved_by' => $this->approvedBy,
            'voucher_date' => $this->transactionDate,
        ]);

        // For Voucher Particulars
        VouchersParticulars::create([
            'voucher_id' => $voucherID,
            'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
            'description' => strtoupper($this->transactionDescription),
        ]);

        $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

        // For Voucher Receipt Image
        VoucherExpenseReceipts::create([
            'voucher_id' => $voucherID,
            'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
        ]);

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();
        $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for ' . ChartOfAccounts::find($this->accountName)->account_name .  ' - ' . strtoupper($this->transactionDescription);

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'CREDIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        $this->showingPrintVoucherConfirmation = true;
        $this->dispatch('transaction-added');
    }

    // Taxes and Licenses
    public function transact504()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();

        $description = ChartOfAccounts::find($this->accountName)->account_name . ' for ' . strtoupper($this->transactionDescription);

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // For Taxes and Licenses
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '504',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'DEBIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        // For Voucher
        $voucherID = CustomNumberFactory::getRandomID();
        $this->toPrintVoucher = $voucherID;
        $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

        Vouchers::create([
            'voucher_id' => $voucherID,
            'voucher_number' => $voucherNumber,
            'powas_id' => $this->powasID,
            'recorded_by' => Auth::user()->user_id,
            'trxn_id' => $newTransactionID,
            'amount' => $this->transactionAmount,
            'received_by' => strtoupper($this->receiveFromOrPaidTo),
            'prepared_by' => $this->preparedBy,
            'checked_by' => $this->checkedBy,
            'approved_by' => $this->approvedBy,
            'voucher_date' => $this->transactionDate,
        ]);

        // For Voucher Particulars
        VouchersParticulars::create([
            'voucher_id' => $voucherID,
            'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
            'description' => strtoupper($this->transactionDescription),
        ]);

        $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

        // For Voucher Receipt Image
        VoucherExpenseReceipts::create([
            'voucher_id' => $voucherID,
            'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
        ]);

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();
        $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for ' . ChartOfAccounts::find($this->accountName)->account_name .  ' - ' . strtoupper($this->transactionDescription);

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'CREDIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        $this->showingPrintVoucherConfirmation = true;
        $this->dispatch('transaction-added');
    }

    // Office Supplies and Prints
    public function transact505()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();

        $description = ChartOfAccounts::find($this->accountName)->account_name . ' for ' . strtoupper($this->transactionDescription);

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // For Office Supplies and Prints
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '505',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'DEBIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        // For Voucher
        $voucherID = CustomNumberFactory::getRandomID();
        $this->toPrintVoucher = $voucherID;
        $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

        Vouchers::create([
            'voucher_id' => $voucherID,
            'voucher_number' => $voucherNumber,
            'powas_id' => $this->powasID,
            'recorded_by' => Auth::user()->user_id,
            'trxn_id' => $newTransactionID,
            'amount' => $this->transactionAmount,
            'received_by' => strtoupper($this->receiveFromOrPaidTo),
            'prepared_by' => $this->preparedBy,
            'checked_by' => $this->checkedBy,
            'approved_by' => $this->approvedBy,
            'voucher_date' => $this->transactionDate,
        ]);

        // For Voucher Particulars
        VouchersParticulars::create([
            'voucher_id' => $voucherID,
            'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
            'description' => strtoupper($this->transactionDescription),
        ]);

        $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

        // For Voucher Receipt Image
        VoucherExpenseReceipts::create([
            'voucher_id' => $voucherID,
            'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
        ]);

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();
        $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for ' . ChartOfAccounts::find($this->accountName)->account_name .  ' - ' . strtoupper($this->transactionDescription);

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'CREDIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        $this->showingPrintVoucherConfirmation = true;
        $this->dispatch('transaction-added');
    }

    // Officer's Honoraria
    public function transact507()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();

        $description = ChartOfAccounts::find($this->accountName)->account_name . ' for ' . strtoupper($this->transactionDescription);

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // For Officer's Honoraria
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '507',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'DEBIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        // For Voucher
        $voucherID = CustomNumberFactory::getRandomID();
        $this->toPrintVoucher = $voucherID;
        $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

        Vouchers::create([
            'voucher_id' => $voucherID,
            'voucher_number' => $voucherNumber,
            'powas_id' => $this->powasID,
            'recorded_by' => Auth::user()->user_id,
            'trxn_id' => $newTransactionID,
            'amount' => $this->transactionAmount,
            'received_by' => strtoupper($this->receiveFromOrPaidTo),
            'prepared_by' => $this->preparedBy,
            'checked_by' => $this->checkedBy,
            'approved_by' => $this->approvedBy,
            'voucher_date' => $this->transactionDate,
        ]);

        // For Voucher Particulars
        VouchersParticulars::create([
            'voucher_id' => $voucherID,
            'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
            'description' => strtoupper($this->transactionDescription),
        ]);

        $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

        // For Voucher Receipt Image
        VoucherExpenseReceipts::create([
            'voucher_id' => $voucherID,
            'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
        ]);

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();
        $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for ' . ChartOfAccounts::find($this->accountName)->account_name .  ' - ' . strtoupper($this->transactionDescription);

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'CREDIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        $this->showingPrintVoucherConfirmation = true;
        $this->dispatch('transaction-added');
    }

    // Repairing and Maintenance
    public function transact508()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();

        $description = ChartOfAccounts::find($this->accountName)->account_name . ' for ' . strtoupper($this->transactionDescription);

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // For Repairing and Maintenance
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '508',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'DEBIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        // For Voucher
        $voucherID = CustomNumberFactory::getRandomID();
        $this->toPrintVoucher = $voucherID;
        $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

        Vouchers::create([
            'voucher_id' => $voucherID,
            'voucher_number' => $voucherNumber,
            'powas_id' => $this->powasID,
            'recorded_by' => Auth::user()->user_id,
            'trxn_id' => $newTransactionID,
            'amount' => $this->transactionAmount,
            'received_by' => strtoupper($this->receiveFromOrPaidTo),
            'prepared_by' => $this->preparedBy,
            'checked_by' => $this->checkedBy,
            'approved_by' => $this->approvedBy,
            'voucher_date' => $this->transactionDate,
        ]);

        // For Voucher Particulars
        VouchersParticulars::create([
            'voucher_id' => $voucherID,
            'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
            'description' => strtoupper($this->transactionDescription),
        ]);

        $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

        // For Voucher Receipt Image
        VoucherExpenseReceipts::create([
            'voucher_id' => $voucherID,
            'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
        ]);

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();
        $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for ' . ChartOfAccounts::find($this->accountName)->account_name .  ' - ' . strtoupper($this->transactionDescription);

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'CREDIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        $this->showingPrintVoucherConfirmation = true;
        $this->dispatch('transaction-added');
    }

    // Donations and Cash Gifts
    public function transact509()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();

        $description = ChartOfAccounts::find($this->accountName)->account_name . ' for ' . strtoupper($this->transactionDescription);

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // For Donations and Cash Gifts
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '509',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'DEBIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        // For Voucher
        $voucherID = CustomNumberFactory::getRandomID();
        $this->toPrintVoucher = $voucherID;
        $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

        Vouchers::create([
            'voucher_id' => $voucherID,
            'voucher_number' => $voucherNumber,
            'powas_id' => $this->powasID,
            'recorded_by' => Auth::user()->user_id,
            'trxn_id' => $newTransactionID,
            'amount' => $this->transactionAmount,
            'received_by' => strtoupper($this->receiveFromOrPaidTo),
            'prepared_by' => $this->preparedBy,
            'checked_by' => $this->checkedBy,
            'approved_by' => $this->approvedBy,
            'voucher_date' => $this->transactionDate,
        ]);

        // For Voucher Particulars
        VouchersParticulars::create([
            'voucher_id' => $voucherID,
            'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
            'description' => strtoupper($this->transactionDescription),
        ]);

        $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

        // For Voucher Receipt Image
        VoucherExpenseReceipts::create([
            'voucher_id' => $voucherID,
            'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
        ]);

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();
        $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for ' . ChartOfAccounts::find($this->accountName)->account_name .  ' - ' . strtoupper($this->transactionDescription);

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'CREDIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        $this->showingPrintVoucherConfirmation = true;
        $this->dispatch('transaction-added');
    }

    // Miscellaneous Expense
    public function transact510()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();

        $description = ChartOfAccounts::find($this->accountName)->account_name . ' for ' . strtoupper($this->transactionDescription);

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // For Miscellaneous Expense
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '510',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'DEBIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        // For Voucher
        $voucherID = CustomNumberFactory::getRandomID();
        $this->toPrintVoucher = $voucherID;
        $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

        Vouchers::create([
            'voucher_id' => $voucherID,
            'voucher_number' => $voucherNumber,
            'powas_id' => $this->powasID,
            'recorded_by' => Auth::user()->user_id,
            'trxn_id' => $newTransactionID,
            'amount' => $this->transactionAmount,
            'received_by' => strtoupper($this->receiveFromOrPaidTo),
            'prepared_by' => $this->preparedBy,
            'checked_by' => $this->checkedBy,
            'approved_by' => $this->approvedBy,
            'voucher_date' => $this->transactionDate,
        ]);

        // For Voucher Particulars
        VouchersParticulars::create([
            'voucher_id' => $voucherID,
            'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
            'description' => strtoupper($this->transactionDescription),
        ]);

        $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

        // For Voucher Receipt Image
        VoucherExpenseReceipts::create([
            'voucher_id' => $voucherID,
            'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
        ]);

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();
        $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for ' . ChartOfAccounts::find($this->accountName)->account_name .  ' - ' . strtoupper($this->transactionDescription);

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'CREDIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        $this->showingPrintVoucherConfirmation = true;
        $this->dispatch('transaction-added');
    }

    // Transportation Expense
    public function transact511()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();

        $description = ChartOfAccounts::find($this->accountName)->account_name . ' for ' . strtoupper($this->transactionDescription);

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // For Transportation Expense
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '511',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'DEBIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        // For Voucher
        $voucherID = CustomNumberFactory::getRandomID();
        $this->toPrintVoucher = $voucherID;
        $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

        Vouchers::create([
            'voucher_id' => $voucherID,
            'voucher_number' => $voucherNumber,
            'powas_id' => $this->powasID,
            'recorded_by' => Auth::user()->user_id,
            'trxn_id' => $newTransactionID,
            'amount' => $this->transactionAmount,
            'received_by' => strtoupper($this->receiveFromOrPaidTo),
            'prepared_by' => $this->preparedBy,
            'checked_by' => $this->checkedBy,
            'approved_by' => $this->approvedBy,
            'voucher_date' => $this->transactionDate,
        ]);

        // For Voucher Particulars
        VouchersParticulars::create([
            'voucher_id' => $voucherID,
            'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
            'description' => strtoupper($this->transactionDescription),
        ]);

        $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

        // For Voucher Receipt Image
        VoucherExpenseReceipts::create([
            'voucher_id' => $voucherID,
            'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
        ]);

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();
        $description = 'Cash paid to ' . $this->receiveFromOrPaidTo . ' for ' . ChartOfAccounts::find($this->accountName)->account_name .  ' - ' . strtoupper($this->transactionDescription);

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'CREDIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        $this->showingPrintVoucherConfirmation = true;
        $this->dispatch('transaction-added');
    }

    // Depreciation Expense
    public function transact512()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();

        $description = ChartOfAccounts::find($this->accountName)->account_name . ' for ' . strtoupper($this->transactionDescription);

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // For Depreciation Expense
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '512',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'DEBIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find($this->accountName)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        // For Voucher
        $voucherID = CustomNumberFactory::getRandomID();
        $this->toPrintVoucher = $voucherID;
        $voucherNumber = CustomNumberFactory::voucher($this->powasID, $this->transactionDate);

        Vouchers::create([
            'voucher_id' => $voucherID,
            'voucher_number' => $voucherNumber,
            'powas_id' => $this->powasID,
            'recorded_by' => Auth::user()->user_id,
            'trxn_id' => $newTransactionID,
            'amount' => $this->transactionAmount,
            'received_by' => strtoupper($this->receiveFromOrPaidTo),
            'prepared_by' => $this->preparedBy,
            'checked_by' => $this->checkedBy,
            'approved_by' => $this->approvedBy,
            'voucher_date' => $this->transactionDate,
        ]);

        // For Voucher Particulars
        VouchersParticulars::create([
            'voucher_id' => $voucherID,
            'particulars' => strtoupper(ChartOfAccounts::find($this->accountName)->account_name),
            'description' => strtoupper($this->transactionDescription),
        ]);

        $this->receiptImage->storeAs('voucher_receipts', $voucherID . '.' . $this->receiptImage->extension(), 'public');

        // For Voucher Receipt Image
        VoucherExpenseReceipts::create([
            'voucher_id' => $voucherID,
            'receipt_path' => $voucherID . '.' . $this->receiptImage->extension(),
        ]);

        // For Less:Accumulated Depreciation
        $newTransactionID = CustomNumberFactory::getRandomID();
        $description = 'Accumulated Depreciation for Properties and Equipment';

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '104',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'CREDIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(104)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        $this->showingPrintVoucherConfirmation = true;
        $this->dispatch('transaction-added');
    }

    // Cash in Bank
    public function transactdeposit()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();

        $description = ChartOfAccounts::find(102)->account_name . ' deposit by ' . strtoupper($this->receiveFromOrPaidTo);

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // For Cash in Bank
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '102',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'DEBIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find(102)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        // For Bank Slip
        $this->receiptImage->storeAs('bank_slips', $newTransactionID . '.' . $this->receiptImage->extension(), 'public');

        BankSlipPictures::create([
            'trxn_id' => $newTransactionID,
            'transaction_type' => 'deposit',
            'bank_slip_image' => $this->receiptImage . '.' . $this->receiptImage->extension(),
        ]);

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();
        $description = 'Cash deposit in bank by ' . strtoupper($this->receiveFromOrPaidTo);

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'CREDIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        $this->dispatch('transaction-added');
    }

    // Cash in Bank
    public function transactwithdraw()
    {
        $newTransactionID = CustomNumberFactory::getRandomID();

        $description = ChartOfAccounts::find(102)->account_name . ' withdrawal by ' . strtoupper($this->receiveFromOrPaidTo);

        $memberID = null;
        $memberFullNames = [];

        if (strlen($this->receiveFromOrPaidTo) != 0 || $this->receiveFromOrPaidTo != '') {
            $queryMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->selectRaw('CONCAT(powas_applications.lastname, ", ", powas_applications.firstname, " ", powas_applications.middlename) AS fullName, powas_members.member_id')->get();

            foreach ($queryMembers as $key => $value) {
                $memberFullNames[$value->fullName] = $value->member_id;
            }

            if (isset($memberFullNames[$this->receiveFromOrPaidTo])) {
                $memberID = $memberFullNames[$this->receiveFromOrPaidTo];
            }
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->transactionDate);

        // For Cash in Bank
        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '102',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'CREDIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper(ChartOfAccounts::find(102)->account_name) . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        // For Bank Slip
        $this->receiptImage->storeAs('bank_slips', $newTransactionID . '.' . $this->receiptImage->extension(), 'public');

        BankSlipPictures::create([
            'trxn_id' => $newTransactionID,
            'transaction_type' => 'deposit',
            'bank_slip_image' => $this->receiptImage . '.' . $this->receiptImage->extension(),
        ]);

        // For Cash
        $newTransactionID = CustomNumberFactory::getRandomID();
        $description = 'Cash deposit in bank by ' . strtoupper($this->receiveFromOrPaidTo);

        Transactions::create([
            'trxn_id' => $newTransactionID,
            'account_number' => '101',
            'description' => $description,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->transactionAmount,
            'transaction_side' => 'DEBIT',
            'received_from' => strtoupper($this->receiveFromOrPaidTo),
            'paid_to' => strtoupper($this->receiveFromOrPaidTo),
            'member_id' => $memberID,
            'powas_id' => $this->powasID,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->transactionDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . ChartOfAccounts::find(101)->account_name . '</i></b> with description <b>"' . $description . '"</b> amounting to <b>&#8369;' . number_format($this->transactionAmount, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $this->dispatch('alert', [
            'message' => 'Transaction successfully saved!',
            'messageType' => 'success',
            'position' => 'top-right',
        ]);
        $this->showingConfirmAddTrasactionModal = false;
        $this->dispatch('transaction-added');
    }

    public function render()
    {
        $powasMembers = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
            ->where('powas_applications.powas_id', $this->powasID)
            ->orderBy('powas_applications.lastname', 'asc')
            ->orderBy('powas_applications.firstname', 'asc')
            ->orderBy('powas_applications.middlename', 'asc')
            ->get();

        return view('livewire.accounting.add-transaction', [
            'members' => $powasMembers,
        ]);
    }
}
