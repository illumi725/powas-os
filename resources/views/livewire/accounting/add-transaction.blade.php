<div class="inline">
    <x-button class="block w-full md:w-auto md:inline mt-2 md:mt-0" type="button" wire:click="showAddTransactionModal"
        wire:loading.attr="disabled">
        <span>&nbsp;{{ __('Add Transaction') }}</span>
    </x-button>

    <x-dialog-modal wire:model.live="showingAddTransactionModal" maxWidth="sm">
        @slot('title')
            {{ __('Add Transaction') }}
        @endslot
        @slot('content')
            <form wire:submit="showConfirmSaveTransaction" method="POST">
                @csrf
                <div>
                    <x-label for="transactionType" value="{{ __('Transaction Type') }}" />
                    <x-combobox wire:model.live="transactionType" class="w-full" wire:change="updateAccountTypeSelection">
                        @slot('options')
                            <option value="" selected disabled>{{ __('-Select Transaction Type-') }}</option>
                            <option value="bank">{{ __('Bank Transaction') }}</option>
                            <option value="receipts">{{ __('Receipts') }}</option>
                            <option value="payments">{{ __('Payments') }}</option>
                            <option value="expenses">{{ __('Expenses') }}</option>
                        @endslot
                    </x-combobox>
                    <x-input-error class="text-sm" for="transactionType" />
                </div>
                <div class="mt-4">
                    <x-label for="accountName" value="{{ __('Account Name') }}" />
                    <x-combobox wire:model.live="accountName" class="w-full">
                        @slot('options')
                            <option value="" selected disabled>{{ __('-Select Account Name-') }}</option>

                            @if ($transactionType == 'bank')
                                <option value="deposit">{{ __('DEPOSIT') }}</option>
                                <option value="withdraw">{{ __('WITHDRAW') }}</option>
                            @else
                                @foreach ($accountNameList as $accountNum => $account)
                                    <option value="{{ $accountNum }}">{{ $account['account_name'] }}</option>
                                @endforeach
                            @endif
                        @endslot
                    </x-combobox>
                    <x-input-error class="text-sm" for="accountName" />
                </div>
                <div class="mt-4">
                    <x-label for="transactionDate" value="{{ __('Transaction Date') }}" />
                    <x-input class="w-full" type="date" wire:model.live="transactionDate" />
                    <x-input-error class="text-sm" for="transactionDate" />
                </div>
                <div class="mt-4">
                    <x-label for="transactionAmount" value="{{ __('Transaction Amount') }}" />
                    <x-input class="w-full" type="number" wire:model.live="transactionAmount" />
                    <x-input-error class="text-sm" for="transactionAmount" />
                </div>
                <div class="mt-4">
                    <x-label for="receiveFromOrPaidTo" value="{{ __('Received From or Paid To') }}" />
                    <x-input class="w-full" type="text" wire:model.live="receiveFromOrPaidTo" list="membersName" />
                    <datalist id="membersName">
                        @foreach ($members as $key => $value)
                            <option value="{{ $value->lastname . ', ' . $value->firstname . ' ' . $value->middlename }}">
                            </option>
                        @endforeach
                    </datalist>
                    <x-input-error class="text-sm" for="receiveFromOrPaidTo" />
                </div>

                <div class="mt-4">
                    <x-label for="transactionDescription" value="{{ __('Transaction Description') }}" />
                    <x-textarea class="w-full" type="text" wire:model.live="transactionDescription" />
                    <x-input-error class="text-sm" for="transactionDescription" />
                </div>

                @if ($transactionType == 'payments' || $transactionType == 'expenses')
                    <div class="mt-4">
                        <x-label for="receiptImage" value="{{ __('Upload Receipt Image') }}" />
                        <x-input id="receiptImage" class="block mt-1 w-full" type="file" wire:model="receiptImage"
                            accept="image/*" />
                        @if ($receiptImage)
                            <div class="mt-3 w-full text-center">
                                <img class="w-96" src="{{ $receiptImage->temporaryUrl() }}" alt="Preview">
                            </div>
                        @endif
                        <x-input-error for="receiptImage" class="mt-1" />
                    </div>
                @endif
            </form>
        @endslot
        @slot('footer')
            <x-button type="button" wire:click="showConfirmAddTransactionModal" wire:loading.attr="disabled">
                {{ __('Save') }}
            </x-button>
            <x-danger-button class="ms-3" wire:click="$toggle('showingAddTransactionModal')" wire:loading.attr="disabled">
                {{ __('Close') }}
            </x-danger-button>
        @endslot
    </x-dialog-modal>

    <x-confirmation-modal wire:model.live="showingConfirmAddTrasactionModal" maxWidth="sm">
        @slot('title')
            <span>
                {{ __('Confirm Add Transaction') }}
            </span>
        @endslot
        @slot('content')
            <div>
                {{ __('Are you sure to want to save transaction?') }}
            </div>

            @if (!Auth::user()->hasRole('admin'))
                <div class="mt-2">
                    {{ __('Please note that this action is irreversible and any changes would require administrative approval.') }}
                </div>
            @endif
        @endslot
        @slot('footer')
            <x-secondary-button type="button" wire:click="transact{{ $accountName }}" wire:loading.attr="disabled">
                {{ __('Yes') }}
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="$toggle('showingConfirmAddTrasactionModal')"
                wire:loading.attr="disabled">
                {{ __('No') }}
            </x-danger-button>
        @endslot
    </x-confirmation-modal>

    <x-confirmation-modal wire:model.live="showingPrintVoucherConfirmation" maxWidth="sm">
        @slot('title')
            <span>
                {{ __('Confirm Print Voucher') }}
            </span>
        @endslot
        @slot('content')
            <div>
                {{ __('Do you want to print voucher for this transaction?') }}
            </div>
        @endslot
        @slot('footer')
            <x-button-link id="voucherPrint" onclick="return openPopup('voucherPrint');"
                wire:click="$toggle('showingPrintVoucherConfirmation')"
                href="{{ route('print-voucher', ['powasID' => $powasID, 'voucherID' => $toPrintVoucher]) }}"
                wire:loading.attr="disabled">
                {{ __('Yes') }}
            </x-button-link>

            <x-danger-button class="ms-3" wire:click="$toggle('showingPrintVoucherConfirmation')"
                wire:loading.attr="disabled">
                {{ __('No') }}
            </x-danger-button>
        @endslot
    </x-confirmation-modal>

    @if ($printIDs != null && $trxnIDs != null)
        {{-- Print Receipt --}}
        <x-confirmation-modal wire:model.live="printing" maxWidth="sm">
            <x-slot name="title">
                {{ __('Print Receipt') }}
            </x-slot>

            <x-slot name="content">
                {{ __('Do you want to print receipt?') }}
            </x-slot>

            <x-slot name="footer">
                @if (count($trxnIDs))
                    <x-button-link id="receiptLink" wire:click="$toggle('printing')"
                        href="{{ route('other-receipt.view', ['trxnID' => json_encode($trxnIDs), 'printID' => json_encode($printIDs), 'receiptNumber' => $receiptNumber, 'powasID' => $powasID]) }}"
                        wire:loading.attr="disabled" onclick="return openPopup('receiptLink');"
                        wire:loading.attr="disabled">
                        <i class="fa-solid fa-check"></i>&nbsp;
                        {{ __('Yes') }}
                    </x-button-link>
                @endif

                <x-danger-button class="ms-3" wire:click="$toggle('printing')" wire:loading.attr="disabled">
                    <i class="fa-solid fa-circle-xmark"></i>&nbsp;
                    {{ __('No') }}
                </x-danger-button>
            </x-slot>
        </x-confirmation-modal>
    @endif
</div>
