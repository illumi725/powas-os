<div class="py-4 px-4 space-y-4" x-data="{ expanded: '' }" id="expensesList">
    <x-alert-message class="me-3" on="alert" />

    {{-- Filter --}}
    <div class="w-full grid grid-cols-1 md:grid-cols-2 gap-4 px-4">
        <div class="w-full grid grid-cols-1 md:flex md:items-center gap-4">
            <span class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Expenses') }}
            </span>

            <x-button-link id="fisPrint" onclick="return openPopup('fisPrint');"
                href="{{ route('accounting', ['powasID' => $powasID, 'transactionMonth' => $selectedMonthYear]) }}"
                wire:loading.attr="disabled">
                {{ __('Financial Statement') }}
            </x-button-link>
        </div>
        <div class="w-full md:flex md:justify-end md:items-center gap-2">
            <x-label class="block md:inline" value="{{ __('Transaction Month: ') }}" />
            <x-combobox class="block w-full md:w-auto md:inline" wire:model="selectedMonthYear"
                wire:change="fetchData2">
                @slot('options')
                    @foreach ($monthYear as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                @endslot
            </x-combobox>
            @can('create transaction')
                @livewire('accounting.add-transaction', ['powasID' => $powasID, 'powas' => $powas])
            @endcan
        </div>
    </div>

    <div wire:loading wire:target="fetchData2" class="my-2 w-full text-center">
        <x-label class="text-xl font-bold my-16" value="{{ __('Loading data... Please wait...') }}" />
    </div>

    <div class="w-full px-4 pb-4" wire:loading.class="hidden" wire:target="fetchData2">
        @if (count($transactionsList) == 0 || $transactionsList == null)
            <div class="my-2 w-full text-center">
                <x-label class="text-xl font-black my-16" value="{{ __('No records found!') }}" />
            </div>
        @else
            <div class="overflow-x-auto overflow-y-auto">
                <table class="border border-collapse min-w-max md:w-full">
                    <thead class="bg-gray-400 border border-collapse">
                        <tr>
                            <th class=" py-2">
                                <span>
                                    {{ __('Date') }}
                                </span>
                            </th>
                            <th class="py-2" style="width: 620px;">
                                <span>
                                    {{ __('Description') }}
                                </span>
                            </th>
                            <th class="px-2 py-2">
                                <span>
                                    {{ __('Entry #') }}
                                </span>
                            </th>
                            <th class="py-2 whitespace-nowrap">
                                <span>
                                    {{ __('Account #') }}
                                </span>
                            </th>
                            <th class="px-6 py-2">
                                <span>
                                    {{ __('Amount') }}
                                </span>
                            </th>
                            <th class="px-6 py-2">
                                <span>
                                    {{ __('Action') }}
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @foreach ($transactionsList as $journalEntryNumber => $transaction)
                            <tr class="even:bg-gray-100 odd:bg-slate-200 hover:font-bold hover:bg-gray-300 cursor-pointer"
                                wire:key="{{ $journalEntryNumber }}">
                                <th class="flex justify-center align-middle px-3 py-1 whitespace-nowrap">
                                    <span>
                                        {{ $transactionsList[$journalEntryNumber][0]->transaction_date }}
                                    </span>
                                </th>

                                <td class="px-3 py-1">
                                    @foreach ($transaction as $item)
                                        @if ($item->transaction_side == 'DEBIT')
                                            <div>
                                                <span>
                                                    {{ $item->description }}
                                                </span>
                                            </div>
                                        @endif
                                    @endforeach
                                </td>

                                <td class="text-center py-1">
                                    <div>
                                        <span>
                                            {{ $journalEntryNumber }}
                                        </span>
                                    </div>
                                </td>

                                <td class="text-center py-1">
                                    @foreach ($transaction as $item)
                                        @if ($item->transaction_side == 'DEBIT')
                                            <div>
                                                <span>
                                                    {{ $item->account_number }}
                                                </span>
                                            </div>
                                        @endif
                                    @endforeach
                                </td>

                                <td class="text-right px-4 py-1">
                                    @foreach ($transaction as $item)
                                        @if ($item->transaction_side == 'DEBIT')
                                            <div>
                                                <span>
                                                    {{ $item->amount }}
                                                </span>
                                            </div>
                                        @endif
                                    @endforeach
                                </td>

                                <td class="text-right px-4 py-2">
                                    @foreach ($transaction as $item)
                                        @if ($item->transaction_side == 'DEBIT')
                                            <div>
                                                {{-- <span>
                                                    
                                                </span> --}}

                                                <a class="uppercase text-xs font-bold bg-green-300 text-green-950 my-2 py-1 px-2 rounded-full shadow-md" id={{ $voucherLists[$item->trxn_id] }} target="_blank"
                                                    onclick="return openPopup('{{ $voucherLists[$item->trxn_id] }}');"
                                                    href="{{ route('print-voucher', ['powasID' => $powasID, 'voucherID' => $voucherLists[$item->trxn_id]]) }}"
                                                    >
                                                    {{ __('View Voucher') }}
                                                </a>
                                            </div>

                                            @php
                                                $totalExpenses = $totalExpenses + $item->amount;
                                            @endphp
                                        @endif
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-white">
                            <td colspan="5" class="text-center font-bold italic py-1">
                                <span>
                                    {{ __('TOTAL') }}
                                </span>
                            </td>
                            <td class="text-right px-2 py-1 font-bold text-red-600 italic">
                                <span>
                                    {{ number_format($totalExpenses, 2) }}
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>
