<div class="py-4 px-4 space-y-4" x-data="{ expanded: '' }" id="billing-container">
    <x-alert-message class="me-3" on="alert" />

    {{-- Filter --}}
    <div class="w-full grid grid-cols-3">
        <div class="col-span-2">
            <span
                class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Billing Records') }}</span>
            <div class="inline ml-4">
                <span class="font-bold cursor-pointer uppercase dark:text-white"
                    @click="expanded = ('filter' === expanded) ? '' : 'filter'">
                    {{ __('Filter') }}
                    &nbsp;
                    <span x-show="expanded !== 'filter'"><i class="fa-solid fa-chevron-right"></i></span>
                    <span x-show="expanded === 'filter'"><i class="fa-solid fa-chevron-down"></i></span>
                </span>
            </div>
        </div>
        {{-- @can('create billing') --}}
            <div class="inline w-full">
                <div class="flex justify-end">
                    <div class="ms-3 relative">
                        <x-dropdown align="right" width="56">
                            <x-slot name="trigger" class="text-right">
                                <button
                                    class="py-1 px-2 text-xs rounded-xl bg-blue-300 md:text-blue-800 hover:bg-blue-400 shadow font-bold">
                                    {{ __('ACTIONS') }}
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <div class="not-italic font-normal">
                                    {{-- @dd(count($existingBills)) --}}
                                    @if (count($readingIDs) > 0 && $baseReading != null)
                                        <x-dropdown-link
                                            href="{{ route('powas.add.billing', ['powasID' => $powasID, 'regen' => 'false']) }}"
                                            class="text-xs py-1 my-0 uppercase">
                                            @if (count($existingBills) == 0)
                                                {{ __('Generate billing for the month of ') .Carbon\Carbon::parse($baseReading->reading_date)->subDays(14)->format('F Y') }}
                                            @else
                                                {{ __('Manage/Edit billing for the month of ') .Carbon\Carbon::parse($baseReading->reading_date)->subDays(14)->format('F Y') }}
                                            @endif
                                        </x-dropdown-link>
                                    @endif

                                    @if (count($powasSettingsChanges) > 0)
                                        <x-dropdown-link
                                            href="{{ route('powas.add.billing', ['powasID' => $powasID, 'regen' => 'true']) }}"
                                            class="text-xs py-1 my-0 uppercase">
                                            {{ __('Regenerate Billing') }}
                                        </x-dropdown-link>
                                    @endif
                                    @if ($billingMonths != null)
                                        <x-dropdown-link class="text-xs py-1 my-0 uppercase" href="#billing-container"
                                            wire:click="showBillingMonthSelector">
                                            {{ __('Print Collection Sheet') }}
                                        </x-dropdown-link>
                                    @endif
                                </div>
                            </x-slot>
                        </x-dropdown>

                        @if ($billingMonths != null)
                            <x-dialog-modal wire:model.live="showingBillingMonthSelector" maxWidth="sm">
                                @slot('title')
                                    {{ __('Select Billing Month') }}
                                @endslot
                                @slot('content')
                                    <div>
                                        <x-combobox class="w-full block" id="billing_month" name="billing_month"
                                            wire:model.live="billingMonth">
                                            <x-slot name="options">
                                                @foreach ($billingMonths as $item)
                                                    <option value="{{ $item->billing_month }}">
                                                        {{ Carbon\Carbon::parse($item->billing_month)->format('F Y') }}
                                                    </option>
                                                @endforeach
                                            </x-slot>
                                        </x-combobox>
                                    </div>
                                @endslot
                                @slot('footer')
                                    <x-button-link id="collectionSheet" wire:click="$toggle('showingBillingMonthSelector')"
                                        href="{{ route('powas.collection-sheet', ['powasID' => $powasID, 'billingMonth' => $billingMonth]) }}"
                                        wire:loading.attr="disabled" onclick="return openPopup('collectionSheet');">
                                        {{ __('Print') }}
                                    </x-button-link>

                                    <x-danger-button class="ms-3" wire:click="$toggle('showingBillingMonthSelector')"
                                        wire:loading.attr="disabled">
                                        {{ __('Close') }}
                                    </x-danger-button>
                                @endslot
                            </x-dialog-modal>
                        @endif
                    </div>
                </div>
            </div>
        {{-- @endcan --}}
    </div>

    <div x-show="expanded === 'filter'" class="grid grid-cols-1 md:grid-cols-3 gap-2 overflow-hidden" x-collapse>
        <div class="w-full block mt-2 md:mt-0 gap-2">
            <x-label class="inline" for="search" value="{{ __('Search:') }}" />
            <x-input class="w-full block" id="search" name="search" wire:model.live="search" autocomplete="off"
                placeholder="Search..." />
        </div>

        <div class="inline">
            <x-label class="inline" for="pagination" value="{{ __('# of rows per page: ') }}" />
            <x-combobox class="w-full block" id="pagination" name="pagination" wire:model.live="pagination">
                <x-slot name="options">
                    @for ($i = 10; $i <= 1000; $i = $i + 10)
                        <option value="{{ $i }}">{{ $i }}</option>
                    @endfor
                </x-slot>
            </x-combobox>
        </div>

        <div class="w-full block mt-2 md:mt-0 gap-2">
            <x-label class="inline" value="{{ __('Billing Month (last 24 months):') }}" />
            @if ($billingMonths != null)
                <div>
                    <x-combobox class="w-full block" id="billing_month" name="billing_month"
                        wire:model.live="filterBillingMonth">
                        <x-slot name="options">
                            <option value="All">All</option>
                            @foreach ($billingMonths as $item)
                                <option value="{{ $item->billing_month }}">
                                    {{ Carbon\Carbon::parse($item->billing_month)->format('F Y') }}
                                </option>
                            @endforeach
                        </x-slot>
                    </x-combobox>
                </div>
            @else
                <div>
                    {{ __('There is no billing record in the database yet!') }}
                </div>
            @endif
        </div>

        <div class="md:col-span-3 flex justify-end">
            <button x-show="expanded === 'filter'" type="button" wire:click="clearFilter"
                class="uppercase text-xs py-1 px-2 rounded-xl font-bold shadow bg-gray-400 text-gray">{{ __('Clear Filter') }}</button>
        </div>
    </div>

    @if (count($powasSettingsChanges) > 0)
        <div class="w-full text-red-500 ">
            <span class="font-bold">
                {{ __('NOTE: Changes have been made either to POWAS Settings or Reading Details! Please regenerate the billing for the month of ') . Carbon\Carbon::parse($powasBillings[0]['billing_month'])->format('F Y') . '! Go to ACTIONS > REGENERATE BILLING.' }}
            </span>
            <ul class="mt-2 list-disc ml-6">
                @foreach ($powasSettingsChanges as $key => $value)
                    <li class="list-item">{{ $value }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="w-full">
        @if (count($powasBillings) == 0)
            <div class="my-2 text-center">
                <x-label class="text-xl font-black my-16" value="{{ __('No records found!') }}" />
            </div>
        @else
            <div class="shadow-lg p-2 border rounded-lg border-slate-600 dark:border-slate-400">
                <div class="overflow-x-auto overflow-y-auto">
                    <x-table.table class="text-xs md:text-sm table-auto min-w-max md:w-full">
                        <x-slot name="thead">
                            <x-table.thead-tr>
                                <x-table.thead-th class="px-2">{{ __('SL#') }}</x-table.thead-th>
                                <x-table.thead-th class="px-2">{{ __('BILLING REFERENCE') }}</x-table.thead-th>
                                <x-table.thead-th class="px-2">{{ __('MEMBER NAME') }}</x-table.thead-th>
                                {{-- <x-table.thead-th class="px-2">{{ __('PREV. READ.') }}</x-table.thead-th>
                                <x-table.thead-th class="px-2">{{ __('PRES. READ.') }}</x-table.thead-th> --}}
                                <x-table.thead-th class="px-2">{!! __('M<sup>3</sup> USED') !!}</x-table.thead-th>
                                <x-table.thead-th class="px-2">{{ __('BILL AMOUNT') }}</x-table.thead-th>
                                <x-table.thead-th class="px-2">{{ __('DISCOUNT') }}</x-table.thead-th>
                                <x-table.thead-th class="px-2">{{ __('PENALTY') }}</x-table.thead-th>
                                @if ($powasSettings->members_micro_savings > 0)
                                    <x-table.thead-th class="px-2">{{ __('MMS') }}</x-table.thead-th>
                                @endif
                                <x-table.thead-th class="px-2">{{ __('TOTAL DUE') }}</x-table.thead-th>
                                <x-table.thead-th class="px-2">{{ __('BILL #') }}</x-table.thead-th>
                                <x-table.thead-th class="px-2">{{ __('BILLING MONTH') }}</x-table.thead-th>
                                <x-table.thead-th class="px-2">{{ __('DUE DATE') }}</x-table.thead-th>
                                <x-table.thead-th class="px-2">{{ __('BILLING PERIOD') }}</x-table.thead-th>
                                <x-table.thead-th class="px-2">{{ __('BILL STATUS') }}</x-table.thead-th>
                                {{-- @canany(['create bill payment', 'edit bill payment'])
                                    <x-table.thead-th class="px-2">{{ __('ACTION') }}</x-table.thead-th>
                                @endcanany --}}
                                <x-table.thead-th class="px-2">{{ __('RECORDED BY') }}</x-table.thead-th>
                            </x-table.thead-tr>
                        </x-slot>
                        <x-slot name="tbody">
                            @php
                                $readingCounter = 0;
                            @endphp
                            @can('create bill payment')
                                <div>
                                    <x-label
                                        value="{{ __('Note: RED colored Billing Reference Numbers are UNPAID, GREEN are PAID. Click on the reference number to ADD PAYMENT. ') }}" />
                                </div>
                            @endcan
                            <x-table.tbody>
                                @foreach ($powasBillings as $item)
                                    @php
                                        $readingCounter++;
                                    @endphp
                                    <x-table.tbody-tr wire:key="{{ $item->reading_id }}">
                                        <x-table.tbody-td class="text-center px-2">
                                            {{ $readingCounter }}
                                            {{-- {{ $item->reading_id }} --}}
                                        </x-table.tbody-td>

                                        @php
                                            if ($item->bill_status == 'PAID') {
                                                $style = 'bg-green-300 text-green md:text-green-800';
                                            } elseif ($item->bill_status == 'UNPAID') {
                                                $style = 'bg-red-300 text-red md:text-red-800';
                                            } elseif ($item->bill_status == 'PARTIAL') {
                                                $style = 'bg-blue-300 text-blue md:text-blue-800';
                                            }
                                        @endphp

                                        <x-table.tbody-td class="px-2">
                                            @can('create bill payment')
                                                @if ($item->bill_status == 'PAID' || $item->bill_status == 'UNPAID')
                                                    <span
                                                        class="jetbrains shadow-md rounded-full text-xs font-bold p-1 {{ $style }}"
                                                        wire:click="showAddPaymentModal('{{ $item->billing_id }}')">
                                                        {{ $item->billing_id }}
                                                    </span>
                                                @else
                                                    <span class="jetbrains">
                                                        {{ $item->billing_id }}
                                                    </span>
                                                @endif
                                            @endcan

                                            @cannot('create bill payment')
                                                <span class="jetbrains">
                                                    {{ $item->billing_id }}
                                                </span>
                                            @endcannot
                                        </x-table.tbody-td>

                                        <x-table.tbody-td class="px-2">
                                            {{ $item->lastname . ', ' . $item->firstname }}
                                        </x-table.tbody-td>

                                        {{-- <x-table.tbody-td class="px-2 text-right">
                                            {{ $readingsList[$item->billing_id]['previous_reading'] }}
                                        </x-table.tbody-td>

                                        <x-table.tbody-td class="px-2 text-right">
                                            {{ $readingsList[$item->billing_id]['present_reading'] }}
                                        </x-table.tbody-td> --}}

                                        <x-table.tbody-td class="px-2 text-right">
                                            {{ $item->cubic_meter_used }}
                                        </x-table.tbody-td>

                                        <x-table.tbody-td class="px-2 text-right">
                                            &#8369;{{ $item->billing_amount }}
                                        </x-table.tbody-td>

                                        <x-table.tbody-td class="px-2 text-right">
                                            &#8369;{{ $item->discount_amount }}
                                        </x-table.tbody-td>

                                        <x-table.tbody-td class="px-2 text-right">
                                            &#8369;{{ $item->penalty }}
                                        </x-table.tbody-td>

                                        @if ($powasSettings->members_micro_savings > 0)
                                            <x-table.tbody-td class="px-2 text-right">
                                                &#8369;{{ $powasSettings->members_micro_savings }}
                                            </x-table.tbody-td>
                                        @endif

                                        @php
                                            $totalDue = number_format(
                                                $item->billing_amount +
                                                    $item->penalty +
                                                    $powasSettings->members_micro_savings -
                                                    $item->discount_amount,
                                                2,
                                            );
                                        @endphp

                                        <x-table.tbody-td class="px-2 text-right">
                                            <span class="font-bold text-red-700 italic">
                                                &#8369;{{ $totalDue }}
                                            </span>
                                        </x-table.tbody-td>

                                        <x-table.tbody-td class="px-2 text-center">
                                            {{ $item->bill_number }}
                                        </x-table.tbody-td>

                                        <x-table.tbody-td class="px-2 text-center">
                                            {{ Carbon\Carbon::parse($item->billing_month)->format('F Y') }}
                                        </x-table.tbody-td>

                                        <x-table.tbody-td class="px-2 text-center">
                                            {{ Carbon\Carbon::parse($item->due_date)->format('Y-m-d') }}
                                        </x-table.tbody-td>

                                        <x-table.tbody-td class="px-2 text-center">
                                            {{ Carbon\Carbon::parse($item->cut_off_start)->format('M d, Y') . ' - ' . Carbon\Carbon::parse($item->cut_off_end)->format('M d, Y') }}
                                        </x-table.tbody-td>

                                        <x-table.tbody-td class="px-2 text-center">
                                            <span
                                                class="shadow-md rounded-full text-xs font-bold p-1 {{ $style }}">
                                                {{ $item->bill_status }}
                                            </span>
                                        </x-table.tbody-td>

                                        <x-table.tbody-td class="px-2">
                                            {{ $usersList[$item->recorded_by] }}
                                        </x-table.tbody-td>
                                    </x-table.tbody-tr>
                                @endforeach
                            </x-table.tbody>
                        </x-slot>
                    </x-table.table>
                </div>
            </div>

            <div class="mt-2">
                {{ $powasBillings->links() }}
            </div>

            @if (isset($selectedBill))
                <x-dialog-modal wire:model.live="showingAddPaymentModal" maxWidth="sm">
                    @slot('title')
                        <span>
                            {{ __('Add Payment') }}
                        </span>
                    @endslot
                    @slot('content')
                        @if (count($saveError) > 0)
                            <div class="text-sm text-red-600 dark:text-red-400">
                                <div class="block w-full">
                                    {{ __('Some chart of accounts to be used for saving payments is missing!') }}
                                </div>
                                <div class="block w-full mt-2">
                                    <ul class="w-full list-disc ml-8">
                                        @foreach ($saveError as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @else
                            @if ($selectedBill->bill_status == 'PAID')
                                <div class="w-full my-4 text-center">
                                    <span class="text-base text-red-600 dark:text-red-400 font-black">
                                        {{ __('This bill is already settled!') }}
                                    </span>
                                </div>
                            @else
                                <div class="w-full grid grid-cols-2 gap-1">
                                    <div
                                        class="w-full col-span-2 grid grid-cols-2 py-1 px-1 border border-dashed rounded-md mb-4">
                                        <div class="w-full">
                                            <x-label value="{{ __('Reference Number: ') }}" />
                                        </div>
                                        <div class="w-full">
                                            <x-label class="inline font-bold" value="{{ $selectedBill->billing_id }}" />
                                        </div>
                                        <div class="w-full">
                                            <x-label value="{{ __('Account Number: ') }}" />
                                        </div>

                                        @php
                                            $memberInfo = \App\Models\PowasMembers::join(
                                                'powas_applications',
                                                'powas_members.application_id',
                                                '=',
                                                'powas_applications.application_id',
                                            )
                                                ->where('powas_members.member_id', $this->selectedBill->member_id)
                                                ->first();
                                        @endphp

                                        <div class="w-full">
                                            <x-label class="inline font-bold" value="{{ $memberInfo->member_id }}" />
                                        </div>
                                        <div class="w-full">
                                            <x-label value="{{ __('Account Name: ') }}" />
                                        </div>
                                        <div class="w-full">
                                            <x-label class="inline font-bold"
                                                value="{{ $memberInfo->lastname . ', ' . $memberInfo->firstname }}" />
                                        </div>
                                        <div class="w-full">
                                            <x-label value="{{ __('Billing Month: ') }}" />
                                        </div>
                                        <div class="w-full">
                                            <x-label class="inline font-bold"
                                                value="{{ \Carbon\Carbon::parse($selectedBill->billing_month)->format('F Y') }}" />
                                        </div>
                                    </div>

                                    <div class="w-full">
                                        <x-label value="{{ __('Bill Amount: ') }}" />
                                    </div>
                                    <div class="w-full text-right">
                                        <x-label class="inline font-bold"
                                            value="{{ '₱' . $selectedBill->billing_amount }}" />
                                    </div>

                                    @if ($powasSettings->members_micro_savings > 0)
                                        <div class="w-full">
                                            <x-label value="{{ __('Micro-Savings: ') }}" />
                                        </div>
                                        <div class="w-full text-right">
                                            <x-label class="inline font-bold"
                                                value="{{ '₱' . $powasSettings->members_micro_savings }}" />
                                        </div>
                                    @endif

                                    <div class="w-full">
                                        <x-label value="{{ __('Penalty: ') }}" />
                                    </div>
                                    <div class="w-full text-right">
                                        <x-label class="inline font-bold" value="{{ '₱' . $selectedBill->penalty }}" />
                                    </div>

                                    <div class="w-full">
                                        <x-label value="{{ __('Discount: ') }}" />
                                    </div>
                                    <div class="w-full text-right">
                                        <x-label class="inline font-bold"
                                            value="{{ '₱' . $selectedBill->discount_amount }}" />
                                    </div>

                                    <div class="w-full">
                                        <x-label value="{{ __('Excess Payment: ') }}" />
                                    </div>
                                    <div class="w-full text-right">
                                        <x-label class="inline font-bold"
                                            value="{{ '₱' . number_format($excessPaymentFromDB, 2) }}" />
                                    </div>

                                    <div class="w-full flex items-center">
                                        <x-label value="{{ __('Payment Date: ') }}" />
                                    </div>
                                    <div class="w-full text-right">
                                        <x-input class="w-full" type="date" wire:model.live="paymentDate" autofocus />
                                    </div>
                                    <div class="w-full col-span-2">
                                        <x-input-error class="text-sm" for="paymentDate" />
                                    </div>

                                    @if ($daysPassedAfterDueDate > 0)
                                        <div class="w-full flex items-center">
                                            <x-label value="{{ __('After Due Date Penalty: ') }}" />
                                        </div>
                                        <div class="w-full text-right">
                                            <x-input class="w-full text-right" type="number"
                                                wire:model.live="afterDuePenalty" />
                                        </div>
                                        <div class="w-full col-span-2">
                                            <x-input-error class="text-sm" for="afterDuePenalty" />
                                        </div>
                                    @endif

                                    @if ($withReconnectionFee == true)
                                        <div class="w-full flex items-center">
                                            <x-label value="{{ __('Reconnection Fee: ') }}" />
                                        </div>
                                        <div class="w-full text-right">
                                            <x-input class="w-full text-right" type="number"
                                                wire:model.live="reconnectionFee" />
                                        </div>
                                        <div class="w-full col-span-2">
                                            <x-input-error class="text-sm" for="reconnectionFee" />
                                        </div>
                                    @endif

                                    <div class="w-full flex items-center">
                                        <x-label value="{{ __('Amount to Pay: ') }}" />
                                    </div>
                                    <div class="w-full text-right">
                                        <x-label class="inline font-bold"
                                            value="{{ '₱' . number_format($amountToPay, 2) }}" />
                                    </div>

                                    <div class="w-full flex items-center">
                                        <x-label value="{{ __('Payment Amount: ') }}" />
                                    </div>
                                    <div class="w-full text-right">
                                        <x-input class="w-full text-right" type="number"
                                            wire:model.live="paymentAmount" />
                                    </div>
                                    <div class="w-full col-span-2">
                                        <x-input-error class="text-sm" for="paymentAmount" />
                                    </div>
                                </div>
                            @endif
                        @endif
                    @endslot
                    @slot('footer')
                        @can('create bill payment')
                            @if ($selectedBill->bill_status == 'UNPAID')
                                <x-secondary-button type="button" wire:click="confirmSave" wire:loading.attr="disabled">
                                    {{ __('Save') }}
                                </x-secondary-button>
                            @endif
                        @endcan
                        <x-danger-button class="ms-3" wire:click="$toggle('showingAddPaymentModal')"
                            wire:loading.attr="disabled">
                            {{ __('Close') }}
                        </x-danger-button>
                    @endslot
                </x-dialog-modal>

                <x-confirmation-modal wire:model.live="showingConfirmSaveModal" maxWidth="sm">
                    @slot('title')
                        <span>
                            {{ __('Confirm Add Payment') }}
                        </span>
                    @endslot
                    @slot('content')
                        <div>
                            {{ __('Are you sure to want to save payment?') }}
                        </div>

                        @if (!Auth::user()->hasRole('admin'))
                            <div>
                                {{ __('Please note that this action is irreversible and any changes would require administrative approval.') }}
                            </div>
                        @endif
                    @endslot
                    @slot('footer')
                        <x-secondary-button type="button" wire:click="savePayment" wire:loading.attr="disabled">
                            {{ __('Save') }}
                        </x-secondary-button>
                        <x-danger-button class="ms-3" wire:click="$toggle('showingConfirmSaveModal')"
                            wire:loading.attr="disabled">
                            {{ __('Close') }}
                        </x-danger-button>
                    @endslot
                </x-confirmation-modal>

                <x-confirmation-modal wire:model.live="showingConfirmPrintModal" maxWidth="sm">
                    @slot('title')
                        <span>
                            {{ __('Confirm Receipt Printing') }}
                        </span>
                    @endslot

                    @slot('content')
                        <div>
                            {{ __('Do you want to print bills payment receipt?') }}
                        </div>
                    @endslot

                    @slot('footer')
                        <x-button-link id="billReceipt" wire:click="printReceipt"
                            href="{{ route('billing-receipts', ['billingIDs' => json_encode($toPrintReceipts)]) }}"
                            onclick="return openPopup('billReceipt');" wire:loading.attr="disabled">
                            {{ __('Print') }}
                        </x-button-link>
                        <x-danger-button class="ms-3" wire:click="$toggle('showingConfirmPrintModal')"
                            wire:loading.attr="disabled">
                            {{ __('Close') }}
                        </x-danger-button>
                    @endslot

                    {{-- <script>
                        let printWindow;

                        function openPopup(element) {
                            var url = document.getElementById(element).getAttribute('href');
                            var windowWidth = 960;
                            var windowHeight = 640;

                            var screenWidth = window.screen.width;
                            var screenHeight = window.screen.height;

                            var leftPosition = (screenWidth - windowWidth) / 2;
                            var topPosition = (screenHeight - windowHeight) / 2;

                            var windowFeatures = 'width=' + windowWidth + ',height=' + windowHeight + ',left=' + leftPosition +
                                ',top=' +
                                topPosition + ',resizable=no';

                            printWindow = window.open(url, 'myPopup', windowFeatures);

                            // if (element == 'collectionSheet') {
                            //     printWindow.print();
                            // }

                            if (getMobileOperatingSystem() != 'Android') {
                                printWindow.addEventListener("afterprint", function() {
                                    printWindow.close();
                                });
                            }

                            return false;
                        }

                        function getMobileOperatingSystem() {
                            const userAgent = navigator.userAgent || navigator.vendor || window.opera;

                            if (/windows phone/i.test(userAgent)) {
                                return "Windows Phone";
                            }
                            if (/android/i.test(userAgent)) {
                                return "Android";
                            }

                            if (/iPad|iPhone|iPod/.test(userAgent) && !window.MSStream) {
                                return "iOS";
                            }

                            if (/Windows/.test(navigator.userAgent) && !/Windows Phone|Windows Mobile/.test(navigator.userAgent)) {
                                return "Windows Desktop";
                            }

                            if (/Macintosh|MacIntel|MacPPC|Mac68K/.test(navigator.userAgent)) {
                                return "MacOS";
                            }

                            if (/Linux/.test(navigator.userAgent) && !isAndroid) {
                                return "Linux";
                            }

                            return "unknown";
                        }
                    </script> --}}
                </x-confirmation-modal>
            @endif
        @endif
    </div>
</div>
