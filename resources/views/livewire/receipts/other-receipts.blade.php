<div class="w-full">
    {{-- Print Button --}}
    <div class="no-print mt-5 mx-auto text-center">
        <x-button type="button" wire:click="updatePrintLog" onclick="window.print();" wire:loading.attr="disabled"
            title="Print">Print</x-button>
    </div>

    {{-- @dd($receiptNumber) --}}

    <div class="text-center py-4">
        <span class="jetbrains x-small-text uppercase">{{ __('--- Start of Print ---') }}</span>
    </div>
    {{-- @foreach ($trxnList as $trxn) --}}
    <div class="mx-auto receipt py-6 text-xs border-t-2 border-b-2 border-dashed border-black">
        {{-- <div class="flex items-center justify-center">
            <img src="{{ asset('storage/assets/logo-modified.png') }}" width="32" alt="">
        </div> --}}
        <div class="text-center font-bold">
            <span class="mx-2 jetbrains">{{ $trxnList[0]['powas_name'] }}</span>
        </div>

        <div class="text-center italic powas-address mb-4">
            <span class="jetbrains">{{ $trxnList[0]['powas_address'] }}</span>
        </div>

        <div class="text-center">
            <hr style="border-style: solid;">
            <span class="font-black jetbrains">{{ __('RECEIPT') }}</span>
            <hr style="border-style: solid;">
        </div>

        <div class="mt-4 jetbrains">
            {!! __('<b class="jetbrains">Receipt No.:</b> ') . $receiptNumber !!}
        </div>
        <div class="jetbrains">
            {!! __('<b class="jetbrains">Transacted by:</b> ') . $trxnList[0]['transact_by'] !!}
        </div>
        <div class="jetbrains">
            {!! __('<b class="jetbrains">Date:</b> ' . $trxnList[0]['transact_date']) !!}
        </div>
        <div class="mt-2 mb-4 jetbrains">
            {!! __('<b class="jetbrains">Received from:</b> ') . $trxnList[0]['received_from'] !!}
        </div>
        <div class="grid grid-cols-3 mb-4">
            <div class="col-span-2 text-center custom-border font-bold jetbrains">{{ __('Particulars') }}</div>
            <div class="text-center custom-border font-bold jetbrains">{{ __('Amount') }}</div>
            @php
                $totalAmount = 0;
                $particulars = '';
            @endphp

            @if ($thisReceipt->description == null)
                @foreach ($trxnList as $trxn)
                    <div class="col-span-2 jetbrains">{{ $trxn['alias'] }}</div>
                    <div class="text-right jetbrains">{{ number_format($trxn['amount'], 2) }}</div>
                    @php
                        $totalAmount = $totalAmount + $trxn['amount'];
                        $particulars = $particulars . $trxn['alias'] . '/';
                    @endphp
                @endforeach
            @else
                @foreach ($trxnList as $trxn)
                    <div class="col-span-2 jetbrains">{{ $thisReceipt->description }}</div>
                    <div class="text-right jetbrains">{{ number_format($trxn['amount'], 2) }}</div>
                    @php
                        $totalAmount = $totalAmount + $trxn['amount'];
                        $particulars = $particulars . $trxn['alias'] . '/';
                    @endphp
                @endforeach
            @endif

            <div class="col-span-3">
                <hr style="border-style: solid;">
            </div>
            <div class="col-span-2 font-bold jetbrains">{{ __('Total') }}</div>
            <div class="text-right font-bold jetbrains">{{ number_format($totalAmount, 2) }}</div>
        </div>
        <div class="italic mb-4 jetbrains text-justify">
            {!! __('This receipt is a valid proof of your payment for <b class="jetbrains">') .
                rtrim($particulars, '/') .
                '</b> to <b class="jetbrains">' .
                $trxnList[0]['powas_name'] .
                '</b>.' !!}
        </div>
        <div class="mb-4 text-center text-base font-black jetbrains">
            {{ __('Thank you!') }}
        </div>
        <div class="text-center jetbrains">
            &copy; {{ date('Y') . ' ' . config('app.name') }}
        </div>
    </div>
    {{-- @endforeach --}}

    <div class="text-center py-4">
        <span class="jetbrains x-small-text uppercase">{{ __('--- End of Print ---') }}</span>
    </div>

    {{-- Print Button --}}
    <div class="no-print mt-5 mx-auto text-center">
        <x-button type="button" wire:click="updatePrintLog" onclick="window.print();" wire:loading.attr="disabled"
            title="Print">Print</x-button>
    </div>
</div>
