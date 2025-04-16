<?php

namespace App\Livewire\Powas;

use App\Events\ActionLogger;
use App\Factory\CustomNumberFactory;
use App\Livewire\Transactions\TransactionsList;
use App\Models\Billings;
use App\Models\BillsPayments;
use App\Models\ChartOfAccounts;
use App\Models\MicroSavings;
use App\Models\Powas;
use App\Models\PowasMembers;
use App\Models\PowasSettings;
use App\Models\Readings;
use App\Models\ReconnectionFees;
use App\Models\Transactions;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PowasBillings extends Component
{
    use WithPagination;

    public $pagination = 10;
    public $powasID;
    public $powas;
    public $showingBillingMonthSelector = false;
    public $membersList;
    public $powasSettings;
    public $billingMonth;
    public $isMinimum = false;
    public $search = '';
    public $startDate = '';
    public $endDate = '';
    public $powasSettingsChanges = [];
    public $billingMonths;
    public $billingMonthSelections;
    public $showingAddPaymentModal = false;
    public $showingConfirmSaveModal = false;
    public $showingConfirmPrintModal = false;
    public $printAllReceipts = false;
    public $selectedBill;
    public $selectedMember;
    public $paymentDate;
    public $afterDuePenalty = 0;
    public $reconnectionFee = 0;
    public $billCutOffEnd = '';
    public $filterBillingMonth = 'All';

    public $existingBillingCount = 0;
    public $notExistingBillingCount = 0;
    public $daysPassedAfterDueDate = 0;
    public $withReconnectionFee = false;
    public $amountToPay = 0;
    public $paymentAmount = 0;
    public $excessPaymentFromDB = 0;
    public $withExcessPayments = false;

    public $validReadings = [];

    public $isReconnectionFeeExists = false;
    public $isExcessPaymentsExists = false;
    public $isMicroSavingsExists = false;
    public $isReceivablesExists = false;
    public $isPenaltiesExists = false;
    public $saveError = [];

    public $toPrintReceipts = [];

    protected $pageName = 'billings';
    protected $listeners = ['reloadBillings' => '$refresh'];

    protected $rules = [
        'startDate' => 'required|date|before_or_equal:endDate',
        'endDate' => 'required|date|after_or_equal:startDate',
    ];

    protected $messages = [
        'startDate.before_or_equal' => 'Start Date must be before or the same as the End Date!',
        'endDate.after_or_equal' => 'Start Date must be before or the same as the End Date!',
    ];

    public function showBillingMonthSelector()
    {
        $this->billingMonthSelections = Billings::select(DB::raw('DISTINCT(billing_month)'))
            ->where('powas_id', $this->powasID)
            ->orderBy('billing_month', 'desc')
            ->limit(24)
            ->get();

        $this->showingBillingMonthSelector = true;
    }

    public function showAddPaymentModal($selectedBillID)
    {
        $this->reset([
            'selectedBill',
            'selectedMember',
            'afterDuePenalty',
            'reconnectionFee',
            'withReconnectionFee',
            'withExcessPayments',
            'amountToPay',
            'excessPaymentFromDB',
            'saveError',
            'isReconnectionFeeExists',
            'isMicroSavingsExists',
            'isReceivablesExists',
            'isPenaltiesExists',
            'isExcessPaymentsExists',
        ]);

        $this->selectedBill = Billings::find($selectedBillID);
        $this->selectedMember = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')->where('powas_members.member_id', $this->selectedBill->member_id)->first();

        $this->isReconnectionFeeExists = ChartOfAccounts::where('account_type', 'REVENUE')->where('account_name', 'LIKE', '%' . 'RECONNECTION FEE' . '%')->exists();
        $this->isMicroSavingsExists = ChartOfAccounts::where('account_type', 'LIABILITY')->where('account_name', 'LIKE', '%' . 'MICRO-SAVINGS' . '%')->exists();
        $this->isReceivablesExists = ChartOfAccounts::where('account_type', 'ASSET')->where('account_name', 'LIKE', '%' . 'BILLS RECEIVABLES' . '%')->exists();
        $this->isPenaltiesExists = ChartOfAccounts::where('account_type', 'REVENUE')->where('account_name', 'LIKE', '%' . 'PENALTIES' . '%')->exists();
        $this->isExcessPaymentsExists = ChartOfAccounts::where('account_type', 'LIABILITY')->where('account_name', 'LIKE', '%' . 'EXCESS PAYMENTS' . '%')->exists();

        if ($this->isReconnectionFeeExists == false) {
            $this->saveError[] = 'reconnection fee';
        }

        if ($this->isMicroSavingsExists == false) {
            $this->saveError[] = 'member\'s micro-savings';
        }

        if ($this->isReceivablesExists == false) {
            $this->saveError[] = 'bills receivables';
        }

        if ($this->isPenaltiesExists == false) {
            $this->saveError[] = 'penalties';
        }

        if ($this->isExcessPaymentsExists == false) {
            $this->saveError[] = 'excess payment';
        }

        $this->paymentDate = Carbon::parse(now())->format('Y-m-d');

        $this->billCutOffEnd = $this->selectedBill->cut_off_end;

        $this->daysPassedAfterDueDate = Carbon::parse($this->selectedBill->due_date)->diffInDays(Carbon::parse($this->paymentDate), false);

        if ($this->powasSettings->penalty_per_day > 0 || $this->powasSettings->penalty_per_day != null) {
            if ($this->powasSettings->days_before_disconnection > 0 || $this->powasSettings->days_before_disconnection != null) {
                if ($this->daysPassedAfterDueDate < $this->powasSettings->days_before_disconnection && $this->daysPassedAfterDueDate > 0) {
                    $this->afterDuePenalty = number_format($this->powasSettings->penalty_per_day * $this->daysPassedAfterDueDate, 2);
                } elseif ($this->daysPassedAfterDueDate >= $this->powasSettings->days_before_disconnection) {
                    $this->afterDuePenalty = number_format(($this->powasSettings->days_before_disconnection - 1) * $this->powasSettings->penalty_per_day, 2);
                } else {
                    $this->afterDuePenalty = number_format(0, 2);
                }
            } else {
                $this->afterDuePenalty = number_format(0, 2);
            }
        } else {
            $this->afterDuePenalty = number_format(0, 2);
        }

        if ($this->powasSettings->reconnection_fee > 0 || $this->powasSettings->reconnection_fee != null) {
            if ($this->powasSettings->days_before_disconnection > 0 || $this->powasSettings->days_before_disconnection != null) {
                if ($this->daysPassedAfterDueDate >= $this->powasSettings->days_before_disconnection) {
                    $this->reconnectionFee = $this->powasSettings->reconnection_fee;
                    $this->withReconnectionFee = true;
                } else {
                    $this->reconnectionFee = number_format(0, 2);
                    $this->withReconnectionFee = false;
                }
            } else {
                $this->reconnectionFee = number_format(0, 2);
                $this->withReconnectionFee = false;
            }
        } else {
            $this->reconnectionFee = number_format(0, 2);
            $this->withReconnectionFee = false;
        }

        $microSavings = 0;

        if ($this->powasSettings->members_micro_savings > 0 || $this->powasSettings->members_micro_savings != null) {
            $microSavings = $this->powasSettings->members_micro_savings;
        }

        $isExistsPreviousBill = Billings::where('member_id', $this->selectedBill->member_id)
            ->where('billings.billing_month', Carbon::parse($this->selectedBill->billing_month)->subMonth()->format('Y-m-01'))->exists();

        if ($isExistsPreviousBill == true) {
            $previousBillID = Billings::where('member_id', $this->selectedBill->member_id)
                ->where('billings.billing_month', Carbon::parse($this->selectedBill->billing_month)->subMonth()->format('Y-m-01'))->first()->billing_id;

            $isExistsExcessPayment = Transactions::where('paid_to', $previousBillID)
                ->where('account_number', '208')
                ->where('transaction_side', 'CREDIT')
                ->exists();

            if ($isExistsExcessPayment == true) {
                $this->excessPaymentFromDB = Transactions::where('paid_to', $previousBillID)
                    ->where('account_number', '208')
                    ->where('transaction_side', 'CREDIT')
                    ->first()->amount;
                $this->withExcessPayments = true;
            }
        }

        $this->amountToPay = ($this->selectedBill->billing_amount + $this->selectedBill->penalty + $microSavings - $this->selectedBill->discount_amount - $this->excessPaymentFromDB) + ($this->afterDuePenalty + $this->reconnectionFee);

        $this->paymentAmount = number_format($this->amountToPay, 2, '.', '');

        $this->showingAddPaymentModal = true;
    }

    public function updatedPaymentDate()
    {
        $this->resetErrorBag('paymentDate');
        $this->validate([
            'paymentDate' => ['required', 'before_or_equal:today', 'after_or_equal:' . $this->billCutOffEnd],
        ], [
            'paymentDate.before_or_equal' => 'Payment date must be equal or before ' . Carbon::parse(now())->format('m/d/y') . '.',
            'paymentDate.after_or_equal' => 'Payment date must be equal or after ' . Carbon::parse($this->billCutOffEnd)->format('m/d/Y') . '.',
        ], [
            'paymentDate' => 'payment date',
        ]);

        $this->reset([
            'afterDuePenalty',
        ]);

        $this->daysPassedAfterDueDate = Carbon::parse($this->selectedBill->due_date)->diffInDays(Carbon::parse($this->paymentDate), false);

        if ($this->powasSettings->penalty_per_day > 0 || $this->powasSettings->penalty_per_day != null) {
            if ($this->powasSettings->days_before_disconnection > 0 || $this->powasSettings->days_before_disconnection != null) {
                if ($this->daysPassedAfterDueDate < $this->powasSettings->days_before_disconnection && $this->daysPassedAfterDueDate > 0) {
                    $this->afterDuePenalty = number_format($this->powasSettings->penalty_per_day * $this->daysPassedAfterDueDate, 2);
                } elseif ($this->daysPassedAfterDueDate >= $this->powasSettings->days_before_disconnection) {
                    $this->afterDuePenalty = number_format(($this->powasSettings->days_before_disconnection - 1) * $this->powasSettings->penalty_per_day, 2);
                } else {
                    $this->afterDuePenalty = 0;
                }
            } else {
                $this->afterDuePenalty = 0;
            }
        } else {
            $this->afterDuePenalty = 0;
        }

        if ($this->powasSettings->reconnection_fee > 0 || $this->powasSettings->reconnection_fee != null) {
            if ($this->powasSettings->days_before_disconnection > 0 || $this->powasSettings->days_before_disconnection != null) {
                if ($this->daysPassedAfterDueDate >= $this->powasSettings->days_before_disconnection) {
                    $this->reconnectionFee = $this->powasSettings->reconnection_fee;
                    $this->withReconnectionFee = true;
                } else {
                    $this->reconnectionFee = 0;
                    $this->withReconnectionFee = false;
                }
            } else {
                $this->reconnectionFee = 0;
                $this->withReconnectionFee = false;
            }
        } else {
            $this->reconnectionFee = 0;
            $this->withReconnectionFee = false;
        }

        $microSavings = 0;

        if ($this->powasSettings->members_micro_savings > 0 || $this->powasSettings->members_micro_savings != null) {
            $microSavings = $this->powasSettings->members_micro_savings;
        }

        $this->amountToPay = ($this->selectedBill->billing_amount + $this->selectedBill->penalty + $microSavings - $this->selectedBill->discount_amount - $this->excessPaymentFromDB) + ($this->afterDuePenalty + $this->reconnectionFee);

        $this->paymentAmount = number_format($this->amountToPay, 2, '.', '');
    }

    public function updatedReconnectionFee()
    {
        $this->resetErrorBag('reconnectionFee');

        $this->validate([
            'reconnectionFee' => ['required', 'numeric', 'min:0'],
        ], [], [
            'reconnectionFee' => 'reconnection fee',
        ]);
    }

    public function updatedAfterDuePenalty()
    {
        $this->resetErrorBag('afterDuePenalty');

        $this->validate([
            'afterDuePenalty' => ['required', 'numeric', 'min:0'],
        ], [], [
            'afterDuePenalty' => 'after due date penalty',
        ]);
    }

    public function updatedPaymentAmount()
    {
        $this->resetErrorBag('paymentAmount');

        $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:0', 'gte:' . $this->amountToPay],
        ], [], [
            'paymentAmount' => 'payment amount',
        ]);

        // dd($this->withReconnectionFee);
    }

    public function confirmSave()
    {
        $this->validate([
            'paymentDate' => ['required', 'before_or_equal:today', 'after_or_equal:' . $this->selectedBill->cut_off_end],
            'afterDuePenalty' => ['required', 'numeric', 'min:0'],
            'reconnectionFee' => ['required', 'numeric', 'min:0'],
            'paymentAmount' => ['required', 'numeric', 'min:0', 'gte:' . $this->amountToPay],
        ], [
            'paymentDate.before_or_equal' => 'Payment date must be equal or before ' . Carbon::parse(now())->format('m/d/y') . '.',
            'paymentDate.after_or_equal' => 'Payment date must be equal or after ' . Carbon::parse($this->selectedBill->cut_off_end)->format('m/d/Y') . '.',
        ], [
            'paymentDate' => 'payment date',
            'afterDuePenalty' => 'after due date penalty',
            'reconnectionFee' => 'reconnection fee',
            'paymentAmount' => 'payment amount',
        ]);

        $this->reset([
            'toPrintReceipts',
        ]);

        $this->showingConfirmSaveModal = true;
    }

    public function printAllReceipt()
    {
        $this->reset([
            'toPrintReceipts',
        ]);

        $this->printAllReceipts = true;
    }

    public function savePayment()
    {
        $cashOnHandAccount = ChartOfAccounts::where('account_type', 'ASSET')->where('account_name', 'LIKE', '%' . 'CASH' . '%')->first();

        $newPenalty = 0;
        $microSavingsAmount = 0;

        $previous_reading_id = $this->selectedBill->previous_reading_id;
        $present_reading_id = $this->selectedBill->present_reading_id;

        $present_reading = Readings::find($present_reading_id);
        $previous_reading = Readings::find($previous_reading_id);

        $cubic_meter_used = $present_reading->reading - $previous_reading->reading;

        $this->toPrintReceipts[] = $this->selectedBill->billing_id;

        $this->selectedMember = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')->where('powas_members.member_id', $this->selectedBill->member_id)->first();

        if ($this->withReconnectionFee == true) {
            // ReconnectionFees::create([
            //     'reconnection_id' => CustomNumberFactory::getRandomID(),
            //     'powas_id' => $this->powasID,
            //     'member_id' => $this->selectedBill->member_id,
            //     'recorded_by' => Auth::user()->user_id,
            //     'billing_id' => $this->selectedBill->billing_id,
            //     'amount' => $this->reconnectionFee,
            //     'date_recorded' => $this->paymentDate,
            // ]);

            // $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created reconnection fee amounting to <b><i>₱' . number_format($this->reconnectionFee, 2) . '</i></b> for billing id <b>' . $this->selectedBill->billing_id . '</b>.';

            // ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transanctions', $this->powasID);

            $reconnectionFeeAccount = ChartOfAccounts::where('account_type', 'REVENUE')->where('account_name', 'LIKE', '%' . 'RECONNECTION FEE' . '%')->first();

            $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->paymentDate);

            // For Reconnection Fee
            Transactions::create([
                'trxn_id' => CustomNumberFactory::getRandomID(),
                'account_number' => $reconnectionFeeAccount->account_number,
                'description' => 'Reconnection fee received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                'journal_entry_number' => $journalEntryNumber,
                'amount' => $this->reconnectionFee,
                'transaction_side' => $reconnectionFeeAccount->normal_balance,
                'received_from' => $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                'paid_to' => $this->selectedBill->billing_id,
                'member_id' => $this->selectedBill->member_id,
                'powas_id' => $this->selectedBill->powas_id,
                'recorded_by_id' => Auth::user()->user_id,
                'transaction_date' => $this->paymentDate,
            ]);

            $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper($reconnectionFeeAccount->account_name) . '</i></b> with description <b>"' . 'Reconnection Fee received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . '"</b> amounting to <b>&#8369;' . number_format($this->reconnectionFee, 2) . '</b>.';

            ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

            // For Cash
            Transactions::create([
                'trxn_id' => CustomNumberFactory::getRandomID(),
                'account_number' => $cashOnHandAccount->account_number,
                'description' => 'Cash received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . ' for Reconnection Fee',
                'journal_entry_number' => $journalEntryNumber,
                'amount' => $this->reconnectionFee,
                'transaction_side' => $cashOnHandAccount->normal_balance,
                'received_from' => $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                'paid_to' => $this->selectedBill->billing_id,
                'member_id' => $this->selectedBill->member_id,
                'powas_id' => $this->selectedBill->powas_id,
                'recorded_by_id' => Auth::user()->user_id,
                'transaction_date' => $this->paymentDate,
            ]);

            $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper($cashOnHandAccount->account_name) . '</i></b> with description <b>"' . 'Cash received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . ' for Reconnection Fee"</b> amounting to <b>&#8369;' . number_format($this->reconnectionFee, 2) . '</b>.';

            ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);
        }

        if ($this->afterDuePenalty > 0) {
            $oldPenalty = $this->selectedBill->penalty;
            $newPenalty = $oldPenalty + $this->afterDuePenalty;
            $this->selectedBill->penalty = $newPenalty;
            $this->selectedBill->save();

            $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> updated penalty from <b><i>' . number_format($oldPenalty, 2) . '</i></b> to <b><i>' . number_format($newPenalty, 2) . '</i></b> for with reference number <i><u>' . $this->selectedBill->billing_id . '</u></i> and POWAS ID <b>' . $this->powasID . '</b>.';

            ActionLogger::dispatch('update', $log_message, Auth::user()->user_id, 'billings', $this->powasID);

            $penaltiesAccount = ChartOfAccounts::where('account_type', 'REVENUE')->where('account_name', 'LIKE', '%' . 'PENALTIES' . '%')->first();

            $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->paymentDate);

            // For Penalties
            Transactions::create([
                'trxn_id' => CustomNumberFactory::getRandomID(),
                'account_number' => $penaltiesAccount->account_number,
                'description' => 'Penalty payment received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                'journal_entry_number' => $journalEntryNumber,
                'amount' => $newPenalty,
                'transaction_side' => $penaltiesAccount->normal_balance,
                'received_from' => $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                'paid_to' => $this->selectedBill->billing_id,
                'member_id' => $this->selectedBill->member_id,
                'powas_id' => $this->selectedBill->powas_id,
                'recorded_by_id' => Auth::user()->user_id,
                'transaction_date' => $this->paymentDate,
            ]);

            $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper($penaltiesAccount->account_name) . '</i></b> with description <b>"' . 'Penalty payment received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . '"</b> amounting to <b>&#8369;' . number_format($newPenalty, 2) . '</b>.';

            ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

            // For Cash
            Transactions::create([
                'trxn_id' => CustomNumberFactory::getRandomID(),
                'account_number' => $cashOnHandAccount->account_number,
                'description' => 'Cash received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . ' for Penalty',
                'journal_entry_number' => $journalEntryNumber,
                'amount' => $newPenalty,
                'transaction_side' => $cashOnHandAccount->normal_balance,
                'received_from' => $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                'paid_to' => $this->selectedBill->billing_id,
                'member_id' => $this->selectedBill->member_id,
                'powas_id' => $this->selectedBill->powas_id,
                'recorded_by_id' => Auth::user()->user_id,
                'transaction_date' => $this->paymentDate,
            ]);

            $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper($cashOnHandAccount->account_name) . '</i></b> with description <b>"' . 'Cash received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . ' for Penalty"</b> amounting to <b>&#8369;' . number_format($newPenalty, 2) . '</b>.';

            ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);
        } else {
            $oldPenalty = $this->selectedBill->penalty;
            if ($oldPenalty > 0) {
                $newPenalty = $oldPenalty + $this->afterDuePenalty;
                $this->selectedBill->penalty = $newPenalty;
                $this->selectedBill->save();

                $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> updated penalty from <b><i>' . number_format($oldPenalty, 2) . '</i></b> to <b><i>' . number_format($newPenalty, 2) . '</i></b> for with reference number <i><u>' . $this->selectedBill->billing_id . '</u></i> and POWAS ID <b>' . $this->powasID . '</b>.';

                ActionLogger::dispatch('update', $log_message, Auth::user()->user_id, 'billings', $this->powasID);

                $penaltiesAccount = ChartOfAccounts::where('account_type', 'REVENUE')->where('account_name', 'LIKE', '%' . 'PENALTIES' . '%')->first();

                $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->paymentDate);

                // For Penalties
                Transactions::create([
                    'trxn_id' => CustomNumberFactory::getRandomID(),
                    'account_number' => $penaltiesAccount->account_number,
                    'description' => 'Penalty payment received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                    'journal_entry_number' => $journalEntryNumber,
                    'amount' => $newPenalty,
                    'transaction_side' => $penaltiesAccount->normal_balance,
                    'received_from' => $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                    'paid_to' => $this->selectedBill->billing_id,
                    'member_id' => $this->selectedBill->member_id,
                    'powas_id' => $this->selectedBill->powas_id,
                    'recorded_by_id' => Auth::user()->user_id,
                    'transaction_date' => $this->paymentDate,
                ]);

                $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper($penaltiesAccount->account_name) . '</i></b> with description <b>"' . 'Penalty payment received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . '"</b> amounting to <b>&#8369;' . number_format($newPenalty, 2) . '</b>.';

                ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

                // For Cash
                Transactions::create([
                    'trxn_id' => CustomNumberFactory::getRandomID(),
                    'account_number' => $cashOnHandAccount->account_number,
                    'description' => 'Cash received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . ' for Penalty',
                    'journal_entry_number' => $journalEntryNumber,
                    'amount' => $newPenalty,
                    'transaction_side' => $cashOnHandAccount->normal_balance,
                    'received_from' => $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                    'paid_to' => $this->selectedBill->billing_id,
                    'member_id' => $this->selectedBill->member_id,
                    'powas_id' => $this->selectedBill->powas_id,
                    'recorded_by_id' => Auth::user()->user_id,
                    'transaction_date' => $this->paymentDate,
                ]);

                $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper($cashOnHandAccount->account_name) . '</i></b> with description <b>"' . 'Cash received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . ' for Penalty"</b> amounting to <b>&#8369;' . number_format($newPenalty, 2) . '</b>.';

                ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);
            }
        }

        if ($this->powasSettings->members_micro_savings > 0 && $this->powasSettings->members_micro_savings != null) {
            $microSavingsAmount = floatval($this->powasSettings->members_micro_savings);

            $microSavings = MicroSavings::where('member_id', $this->selectedBill->member_id)
                ->orderByDesc('date_recorded')->first();

            if ($microSavings != null) {
                $msBalance = $microSavings->balance + $this->powasSettings->members_micro_savings;
            } else {
                $msBalance = 0;
            }

            MicroSavings::create([
                'savings_id' => CustomNumberFactory::getRandomID(),
                'powas_id' => $this->powasID,
                'member_id' => $this->selectedBill->member_id,
                'recorded_by' => Auth::user()->user_id,
                'billing_id' => $this->selectedBill->billing_id,
                'deposit' => $this->powasSettings->members_micro_savings,
                'balance' => $msBalance + $this->powasSettings->members_micro_savings,
                'date_recorded' => $this->paymentDate,
            ]);

            $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created micro-savings deposit amounting to <b><i>₱' . number_format($this->powasSettings->members_micro_savings, 2) . '</i></b> for member id <b>' . $this->selectedBill->member_id . '</b>.';

            ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transanctions', $this->powasID);

            $microSavingsAccount = ChartOfAccounts::where('account_type', 'LIABILITY')->where('account_name', 'LIKE', '%' . 'MICRO-SAVINGS' . '%')->first();

            $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->paymentDate);

            // For Micro-Savings
            Transactions::create([
                'trxn_id' => CustomNumberFactory::getRandomID(),
                'account_number' => $microSavingsAccount->account_number,
                'description' => 'Micro-savings deposit from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                'journal_entry_number' => $journalEntryNumber,
                'amount' => $this->powasSettings->members_micro_savings,
                'transaction_side' => $microSavingsAccount->normal_balance,
                'received_from' => $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                'paid_to' => $this->selectedBill->billing_id,
                'member_id' => $this->selectedBill->member_id,
                'powas_id' => $this->selectedBill->powas_id,
                'recorded_by_id' => Auth::user()->user_id,
                'transaction_date' => $this->paymentDate,
            ]);

            $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper($microSavingsAccount->account_name) . '</i></b> with description <b>"' . 'Micro-Savings Deposit from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . '"</b> amounting to <b>&#8369;' . number_format($this->powasSettings->members_micro_savings, 2) . '</b>.';

            ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

            // For Cash
            Transactions::create([
                'trxn_id' => CustomNumberFactory::getRandomID(),
                'account_number' => $cashOnHandAccount->account_number,
                'description' => 'Cash received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . ' for Micro-Savings Deposit',
                'journal_entry_number' => $journalEntryNumber,
                'amount' => $this->powasSettings->members_micro_savings,
                'transaction_side' => $cashOnHandAccount->normal_balance,
                'received_from' => $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                'paid_to' => $this->selectedBill->billing_id,
                'member_id' => $this->selectedBill->member_id,
                'powas_id' => $this->selectedBill->powas_id,
                'recorded_by_id' => Auth::user()->user_id,
                'transaction_date' => $this->paymentDate,
            ]);

            $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper($cashOnHandAccount->account_name) . '</i></b> with description <b>"' . 'Cash received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . ' for Micro-Savings Deposit"</b> amounting to <b>&#8369;' . number_format($this->powasSettings->members_micro_savings, 2) . '</b>.';

            ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);
        }

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->paymentDate);

        if ($this->selectedBill->discount_amount > 0) {
            $discountsAccount = ChartOfAccounts::where('account_type', 'REVENUE')->where('account_name', 'LIKE', '%' . 'DISCOUNT' . '%')->first();

            // For Discount
            Transactions::create([
                'trxn_id' => CustomNumberFactory::getRandomID(),
                'account_number' => $discountsAccount->account_number,
                'description' => 'Discount for ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                'journal_entry_number' => $journalEntryNumber,
                'amount' => $this->selectedBill->discount_amount,
                'transaction_side' => $discountsAccount->normal_balance,
                'received_from' => $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                'paid_to' => $this->selectedBill->billing_id,
                'member_id' => $this->selectedBill->member_id,
                'powas_id' => $this->selectedBill->powas_id,
                'recorded_by_id' => Auth::user()->user_id,
                'transaction_date' => $this->paymentDate,
            ]);

            $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper($discountsAccount->account_name) . '</i></b> with description <b>"' . 'Discount for ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . '"</b> amounting to <b>&#8369;' . number_format($this->selectedBill->discount_amount, 2) . '</b>.';

            ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);
        }

        if ($this->withExcessPayments == true) {
            $excessPaymentAccount = ChartOfAccounts::where('account_type', 'LIABILITY')->where('account_name', 'LIKE', '%' . 'EXCESS PAYMENTS' . '%')->first();

            // For Excess Payment
            Transactions::create([
                'trxn_id' => CustomNumberFactory::getRandomID(),
                'account_number' => $excessPaymentAccount->account_number,
                'description' => 'Excess Payments debited from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                'journal_entry_number' => $journalEntryNumber,
                'amount' => $this->excessPaymentFromDB,
                'transaction_side' => 'DEBIT',
                'received_from' => $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                'paid_to' => $this->selectedBill->billing_id,
                'member_id' => $this->selectedBill->member_id,
                'powas_id' => $this->selectedBill->powas_id,
                'recorded_by_id' => Auth::user()->user_id,
                'transaction_date' => $this->paymentDate,
            ]);

            $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper($excessPaymentAccount->account_name) . '</i></b> with description <b>"' . 'Excess Payments debited from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . '"</b> amounting to <b>&#8369;' . number_format($this->selectedBill->discount_amount, 2) . '</b>.';

            ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);
        }

        $amountPaid = $this->paymentAmount - ($newPenalty + $microSavingsAmount + $this->reconnectionFee) + $this->selectedBill->discount_amount;

        // BillsPayments::create([
        //     'payment_id' => CustomNumberFactory::getRandomID(),
        //     'powas_id' => $this->powasID,
        //     'member_id' => $this->selectedBill->member_id,
        //     'recorded_by' => Auth::user()->user_id,
        //     'billing_id' => $this->selectedBill->billing_id,
        //     'amount_paid' => $amountPaid,
        //     'date_paid' => $this->paymentDate,
        // ]); Please take note for the possibility of having excess payments which shall be observed

        // $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created bills payment amounting to <b><i>₱' . number_format($amountPaid, 2) . '</i></b> for billing id <b>' . $this->selectedBill->billing_id . '</b>.';

        // ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $billsReceivableAccount = ChartOfAccounts::where('account_type', 'ASSET')->where('account_name', 'LIKE', '%' . 'BILLS RECEIVABLES' . '%')->first();

        // For Bills Receivables
        Transactions::create([
            'trxn_id' => CustomNumberFactory::getRandomID(),
            'account_number' => $billsReceivableAccount->account_number,
            'description' => 'Bills Receivables received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->selectedBill->billing_amount,
            'transaction_side' => 'CREDIT',
            'received_from' => $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
            'paid_to' => $this->selectedBill->billing_id,
            'member_id' => $this->selectedBill->member_id,
            'powas_id' => $this->selectedBill->powas_id,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->paymentDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper($billsReceivableAccount->account_name) . '</i></b> with description <b>"' . 'Bills Receivables received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . '"</b> amounting to <b>&#8369;' . number_format($amountPaid, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        if ($this->withReconnectionFee == false) {
            $this->reconnectionFee = 0;
        } else {
            if ($this->powasSettings->reconnection_fee > 0 || $this->powasSettings->reconnection_fee != null) {
                $this->reconnectionFee = $this->powasSettings->reconnection_fee;
            } else {
                $this->reconnectionFee = 0;
            }
        }

        // For Cash
        Transactions::create([
            'trxn_id' => CustomNumberFactory::getRandomID(),
            'account_number' => $cashOnHandAccount->account_number,
            'description' => 'Cash received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . ' for Bills Receivables',
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $this->paymentAmount - $microSavingsAmount - $this->reconnectionFee - $newPenalty,
            'transaction_side' => $cashOnHandAccount->normal_balance,
            'received_from' => $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
            'paid_to' => $this->selectedBill->billing_id,
            'member_id' => $this->selectedBill->member_id,
            'powas_id' => $this->selectedBill->powas_id,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->paymentDate,
        ]);

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper($cashOnHandAccount->account_name) . '</i></b> with description <b>"' . 'Cash received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . ' for Bills Receivables"</b> amounting to <b>&#8369;' . number_format($amountPaid, 2) . '</b>.';

        ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);

        $excessPayment = $this->paymentAmount - $this->amountToPay;

        if ($excessPayment > 0) {
            $excessPaymentAccount = ChartOfAccounts::where('account_type', 'LIABILITY')->where('account_name', 'LIKE', '%' . 'EXCESS PAYMENTS' . '%')->first();

            // For Excess Payment
            Transactions::create([
                'trxn_id' => CustomNumberFactory::getRandomID(),
                'account_number' => $excessPaymentAccount->account_number,
                'description' => 'Excess Payments received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                'journal_entry_number' => $journalEntryNumber,
                'amount' => $excessPayment,
                'transaction_side' => $excessPaymentAccount->normal_balance,
                'received_from' => $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
                'paid_to' => $this->selectedBill->billing_id,
                'member_id' => $this->selectedBill->member_id,
                'powas_id' => $this->selectedBill->powas_id,
                'recorded_by_id' => Auth::user()->user_id,
                'transaction_date' => $this->paymentDate,
            ]);

            $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> created transaction for <b><i>' . strtoupper($excessPaymentAccount->account_name) . '</i></b> with description <b>"' . 'Excess Payments received from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . '"</b> amounting to <b>&#8369;' . number_format($excessPayment, 2) . '</b>.';

            ActionLogger::dispatch('create', $log_message, Auth::user()->user_id, 'transactions', $this->powasID);
        }

        $oldBillStat = '"' . $this->selectedBill->bill_status . '"';
        $newBillStat =  '"PAID"';

        $this->selectedBill->bill_status = 'PAID';
        $this->selectedBill->save();

        $log_message = '<b><u>' . Auth::user()->userinfo->lastname . ', ' . Auth::user()->userinfo->firstname . '</u></b> updated bill status from <b><i>' . $oldBillStat . '</i></b> to <b><i>' . $newBillStat . '</i></b> for with billing id <i><u>' . $this->selectedBill->billing_id . '</u></i> and POWAS ID <b>' . $this->powasID . '</b>.';

        ActionLogger::dispatch('update', $log_message, Auth::user()->user_id, 'billings', $this->powasID);

        $journalEntryNumber = CustomNumberFactory::journalEntryNumber($this->powasID, $this->paymentDate);

        // For Central Fund
        $centralFundAccount = ChartOfAccounts::where('account_type', 'LIABILITY')->where('account_name', 'LIKE', '%' . 'CENTRAL FUND' . '%')->first();

        Transactions::create([
            'trxn_id' => CustomNumberFactory::getRandomID(),
            'account_number' => $centralFundAccount->account_number,
            'description' => 'Debited from Revenues from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . ' for Central Fund',
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $cubic_meter_used,
            'transaction_side' => $centralFundAccount->normal_balance,
            'received_from' => $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
            'paid_to' => $this->selectedBill->billing_id,
            'member_id' => $this->selectedBill->member_id,
            'powas_id' => $this->selectedBill->powas_id,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->paymentDate,
        ]);

        // For Gross Revenue from Receivables
        $revenueAccount = ChartOfAccounts::where('account_type', 'REVENUE')->where('account_name', 'LIKE', '%' . 'GROSS REVENUE FROM RECEIVABLES' . '%')->first();

        Transactions::create([
            'trxn_id' => CustomNumberFactory::getRandomID(),
            'account_number' => $revenueAccount->account_number,
            'description' => 'Cash debited from ' . $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename . ' for Central Fund',
            'journal_entry_number' => $journalEntryNumber,
            'amount' => $cubic_meter_used,
            'transaction_side' => 'DEBIT',
            'received_from' => $this->selectedMember->lastname . ', ' . $this->selectedMember->firstname . ' ' . $this->selectedMember->middlename,
            'paid_to' => $this->selectedBill->billing_id,
            'member_id' => $this->selectedBill->member_id,
            'powas_id' => $this->selectedBill->powas_id,
            'recorded_by_id' => Auth::user()->user_id,
            'transaction_date' => $this->paymentDate,
        ]);

        $this->dispatch('alert', [
            'message' => 'Payment successfully saved!',
            'messageType' => 'info',
            'position' => 'top-right',
        ]);

        $this->dispatch('transaction-added')->to(TransactionsList::class);

        $this->reset([
            'paymentDate',
            'afterDuePenalty',
            'reconnectionFee',
            'paymentAmount',
        ]);
        $this->showingAddPaymentModal = false;
        $this->showingConfirmSaveModal = false;

        if ($this->printAllReceipts == false) {
            $this->showingConfirmPrintModal = true;
        } else {
            $this->printAllReceipts = false;
        }
    }

    public function printReceipt()
    {
        $this->showingConfirmPrintModal = false;
    }

    public function updatedStartDate()
    {
        $this->validateOnly('startDate');
    }

    public function updatedEndDate()
    {
        $this->validateOnly('endDate');
    }

    public function clearFilter()
    {
        $this->reset([
            'search',
            'startDate',
            'endDate',
            'pagination',
        ]);

        $this->resetErrorBag();

        $this->resetPage();

        $this->dispatch('alert', [
            'message' => 'All filters cleared!',
            'messageType' => 'info',
            'position' => 'top-right',
        ]);
    }

    public function updatingSearch()
    {
        $this->resetPage('billings');
    }

    public function mount($powasID)
    {
        $this->powas = Powas::find($powasID);
        $this->powasID = $powasID;
        $this->powasSettings = PowasSettings::where('powas_id', $powasID)->first();
        $this->paymentDate = Carbon::parse(now())->format('Y-m-d');
    }

    public function render()
    {
        $users = User::all();
        $this->powas = Powas::find($this->powasID);
        $this->powasSettings = PowasSettings::where('powas_id', $this->powasID)->first();

        $this->membersList = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
            ->where('powas_applications.powas_id', $this->powasID)
            ->where('powas_members.member_status', 'ACTIVE')
            ->orderBy('powas_applications.lastname', 'asc')
            ->orderBy('powas_applications.firstname', 'asc')
            ->orderBy('powas_applications.middlename', 'asc')
            ->get();

        $this->billingMonths = Billings::select(DB::raw('DISTINCT(billing_month)'))
            ->where('powas_id', $this->powasID)
            ->orderBy('billing_month', 'desc')
            ->limit(24)
            ->get();

        if ($this->search == '') {
            $powasBillings = Billings::join('powas_members', 'billings.member_id', '=', 'powas_members.member_id')
                ->join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->where('billings.powas_id', $this->powasID)
                ->orderBy('billings.billing_month', 'desc')
                ->orderBy('billings.bill_status', 'desc')
                ->orderBy('powas_applications.lastname', 'asc')
                ->orderBy('powas_applications.firstname', 'asc')
                ->orderBy('powas_applications.middlename', 'asc')
                ->paginate($this->pagination, ['*'], 'billings');

            if ($this->filterBillingMonth != 'All') {
                $powasBillings = Billings::join('powas_members', 'billings.member_id', '=', 'powas_members.member_id')
                    ->join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                    ->where('billings.powas_id', $this->powasID)
                    ->where('billings.billing_month', $this->filterBillingMonth)
                    ->orderBy('billings.billing_month', 'desc')
                    ->orderBy('billings.bill_status', 'desc')
                    ->orderBy('powas_applications.lastname', 'asc')
                    ->orderBy('powas_applications.firstname', 'asc')
                    ->orderBy('powas_applications.middlename', 'asc')
                    ->paginate($this->pagination, ['*'], 'billings');
            }
        } else {
            $powasBillings = Billings::join('powas_members', 'billings.member_id', '=', 'powas_members.member_id')
                ->join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                ->where('billings.powas_id', $this->powasID)
                ->where(function ($query) {
                    $query->where('powas_applications.lastname', 'like', '%' . strtoupper($this->search) . '%')
                        ->orWhere('powas_applications.firstname', 'like', '%' . strtoupper($this->search) . '%')
                        ->orWhere('powas_applications.middlename', 'like', '%' . strtoupper($this->search) . '%')
                        ->orWhere('billings.billing_id', 'like', '%' . strtoupper($this->search) . '%')
                        ->orWhere('billings.bill_status', 'like', strtoupper($this->search) . '%')
                        ->orWhere('powas_members.member_id', 'like', '%' . strtoupper($this->search) . '%');
                })
                ->orderBy('billings.billing_month', 'desc')
                ->orderBy('billings.bill_status', 'desc')
                ->orderBy('powas_applications.lastname', 'asc')
                ->orderBy('powas_applications.firstname', 'asc')
                ->orderBy('powas_applications.middlename', 'asc')
                ->paginate($this->pagination, ['*'], 'billings');
            if ($this->filterBillingMonth != 'All') {
                $powasBillings = Billings::join('powas_members', 'billings.member_id', '=', 'powas_members.member_id')
                    ->join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
                    ->where('billings.powas_id', $this->powasID)
                    ->where('billings.billing_month', $this->filterBillingMonth)
                    ->where(function ($query) {
                        $query->where('powas_applications.lastname', 'like', '%' . strtoupper($this->search) . '%')
                            ->orWhere('powas_applications.firstname', 'like', '%' . strtoupper($this->search) . '%')
                            ->orWhere('powas_applications.middlename', 'like', '%' . strtoupper($this->search) . '%')
                            ->orWhere('billings.billing_id', 'like', '%' . strtoupper($this->search) . '%')
                            ->orWhere('billings.bill_status', 'like', strtoupper($this->search) . '%')
                            ->orWhere('powas_members.member_id', 'like', '%' . strtoupper($this->search) . '%');
                    })
                    ->orderBy('billings.billing_month', 'desc')
                    ->orderBy('billings.bill_status', 'desc')
                    ->orderBy('powas_applications.lastname', 'asc')
                    ->orderBy('powas_applications.firstname', 'asc')
                    ->orderBy('powas_applications.middlename', 'asc')
                    ->paginate($this->pagination, ['*'], 'billings');
            }
        }

        $usersList = [];
        $readingsList = [];

        $transacted = [];
        $billsReceivablesAccount = ChartOfAccounts::where('account_type', 'ASSET')->where('account_name', 'LIKE', '%' . 'BILLS RECEIVABLES' . '%')->first();

        foreach ($users as $key => $value) {
            $usersList[$value->user_id] = $value->userinfo->lastname . ', ' . $value->userinfo->firstname;
        }

        foreach ($powasBillings as $value) {
            $readingsList[$value->billing_id] = [
                'previous_reading' => number_format(Readings::find($value->previous_reading_id)->reading, 2),
                'present_reading' => number_format(Readings::find($value->present_reading_id)->reading, 2),
            ];
            $isTransacted = Transactions::where('transaction_side', 'CREDIT')
                ->where('paid_to', $value->billing_id)
                ->where('account_number', $billsReceivablesAccount->account_number)
                ->exists();

            if ($isTransacted == true) {
                $transacted[$value->billing_id] = 'YES';
            } else {
                $transacted[$value->billing_id] = 'NO';
            }
        }

        $this->reset([
            'existingBillingCount',
            'notExistingBillingCount',
        ]);

        $this->reset([
            'powasSettingsChanges',
        ]);

        $readingIDs = [];
        $existingBills = [];

        foreach ($this->membersList as $member) {
            $readingExists = Readings::where('member_id', $member->member_id)->exists();
            if ($readingExists == true) {
                $readingIDs[] = Readings::where('member_id', $member->member_id)
                    ->orderBy('reading_date', 'desc')
                    ->first()->reading_id;
            }
        }

        foreach ($readingIDs as $readID) {
            $reading = Readings::find($readID);

            if ($reading->reading_count > 1) {
                $billQuery = Billings::where('present_reading_id', $readID)->exists();
                if ($billQuery == true) {
                    $existingBills[] = $readID;
                    $bill = Billings::where('present_reading_id', $readID)->first();

                    $prevRead = Readings::where('reading_id', $bill->previous_reading_id)->first()->reading;
                    $presRead = Readings::where('reading_id', $bill->present_reading_id)->first()->reading;

                    $new_cm_used = $presRead - $prevRead;
                    $new_billing_amount = $new_cm_used * $this->powasSettings->water_rate;

                    $due_date_day = $this->powasSettings->due_date_day;

                    if ($this->powasSettings->due_date_day < 10) {
                        $due_date_day = '0' . $this->powasSettings->due_date_day;
                    }

                    $new_due_date = Carbon::parse($reading->reading_date)->addMonth()->format('Y-m-' . $due_date_day);
                    $new_due_date = Carbon::parse($new_due_date)->format('Y-m-d');

                    if ($new_cm_used <= 5) {
                        $new_billing_amount = $this->powasSettings->minimum_payment;
                        $this->isMinimum = true;
                    } else {
                        $new_billing_amount = $new_cm_used * $this->powasSettings->water_rate;
                        $this->isMinimum = false;
                    }

                    if ($bill->cubic_meter_used != number_format($new_cm_used, 2)) {
                        $this->powasSettingsChanges['reading'] = 'reading';
                    }

                    if (number_format($bill->billing_amount, 2) != number_format($new_billing_amount, 2)) {
                        $this->powasSettingsChanges['water_rate'] = 'water rate';
                    }
                }
            }
        }

        $baseMember = PowasMembers::join('powas_applications', 'powas_members.application_id', '=', 'powas_applications.application_id')
            ->where('powas_members.member_status', 'ACTIVE')
            ->where('powas_applications.powas_id', $this->powasID)
            ->orderBy('powas_members.membership_date', 'asc')
            ->first();

        $baseReading = Readings::where('member_id', $baseMember->member_id)->orderBy('reading_date', 'desc')->first();

        return view('livewire.powas.powas-billings', [
            'powasBillings' => $powasBillings,
            'usersList' => $usersList,
            'readingsList' => $readingsList,
            'existingBills' => $existingBills,
            'readingIDs' => $readingIDs,
            'baseReading' => $baseReading,
            'transacted' => $transacted,
        ]);
    }
}
