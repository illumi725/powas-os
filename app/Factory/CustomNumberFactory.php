<?php

namespace App\Factory;

use App\Models\IssuedReceipts;
use App\Models\Powas;
use App\Models\Transactions;
use App\Models\Vouchers;
use Carbon\Carbon;

class CustomNumberFactory
{
    public static function receipt($powasID, $recordDate)
    {
        $today = Carbon::parse($recordDate);

        $receiptCount = IssuedReceipts::where('powas_id', $powasID)
            ->whereYear('transaction_date', $today->year)
            ->whereMonth('transaction_date', $today->month)
            ->distinct()
            ->pluck('receipt_number')
            ->count();

        $datePart = $today->format('Ym');

        if ($receiptCount >= 0 && $receiptCount + 1 < 10) {
            return $datePart . '000' . ($receiptCount + 1);
        } elseif ($receiptCount >= 10 && $receiptCount + 1 < 100) {
            return $datePart . '00' . ($receiptCount + 1);
        } elseif ($receiptCount >= 100 && $receiptCount + 1 < 1000) {
            return $datePart . '0' . ($receiptCount + 1);
        } else {
            return $datePart . ($receiptCount + 1);
        }
    }

    public static function voucher($powasID, $recordDate)
    {
        $today = Carbon::parse($recordDate);

        $voucherNumber = Vouchers::where('powas_id', $powasID)
            ->whereYear('voucher_date', $today->year)
            ->whereMonth('voucher_date', $today->month)
            ->distinct()
            ->pluck('voucher_number')
            ->count();

        $datePart = $today->format('m');

        if ($voucherNumber + 1 >= 0 && $voucherNumber + 1 < 10) {
            return $datePart . '-000' . ($voucherNumber + 1);
        } elseif ($voucherNumber + 1 >= 10 && $voucherNumber + 1 < 100) {
            return $datePart . '-00' . ($voucherNumber + 1);
        } elseif ($voucherNumber + 1 >= 100 && $voucherNumber + 1 < 1000) {
            return $datePart . '-0' . ($voucherNumber + 1);
        } else {
            return $datePart . ($voucherNumber + 1);
        }
    }

    public static function journalEntryNumber($powasID, $recordDate)
    {
        $today = Carbon::parse($recordDate);

        $journalEntryNumber = Transactions::where('powas_id', $powasID)
            ->whereYear('transaction_date', $today->year)
            ->whereMonth('transaction_date', $today->month)
            ->distinct()
            ->pluck('journal_entry_number')
            ->count();

        $datePart = $today->format('m');

        if ($journalEntryNumber + 1 >= 0 && $journalEntryNumber + 1 < 10) {
            return $datePart . '-000' . ($journalEntryNumber + 1);
        } elseif ($journalEntryNumber + 1 >= 10 && $journalEntryNumber + 1 < 100) {
            return $datePart . '-00' . ($journalEntryNumber + 1);
        } elseif ($journalEntryNumber + 1 >= 100 && $journalEntryNumber + 1 < 1000) {
            return $datePart . '-0' . ($journalEntryNumber + 1);
        } else {
            return $datePart . ($journalEntryNumber + 1);
        }
    }

    public static function powasID($province, $municipality, $barangay)
    {
        $instance = new self();
        $str1 = $instance->generateAbbreviation($province);
        $str2 = $instance->generateAbbreviation($municipality);
        $str3 = $instance->generateAbbreviation($barangay);

        $powasCount = Powas::where('province', $province)
            ->where('municipality', $municipality)
            ->where('barangay', $barangay)
            ->count();

        if ($powasCount >= 0 && $powasCount < 10) {
            $str4 = '00' . $powasCount + 1;
        } elseif ($powasCount >= 10 && $powasCount < 100) {
            $str4 = '0' . $powasCount + 1;
        } else {
            $str4 = $powasCount + 1;
        }

        return $str1 . '-' . $str2 . '-' . $str3 . '-' . $str4;
    }

    private static function generateAbbreviation($string)
    {
        $string = preg_replace('/[^a-zA-Z0-9\s]/', '', $string);
        $string = preg_replace('/\s+/', ' ', $string);
        $words = explode(' ', $string);

        if (count($words) == 1) {
            $abbreviation = strtoupper(substr($words[0], 0, 3));
        } elseif (count($words) == 2) {
            $abbreviation = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 2));
        } else {
            $abbreviation = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1) . substr($words[2], 0, 1));
        }

        return $abbreviation;
    }

    public static function getRandomID()
    {
        return rand(1000000000000000000, 9223372036854775807);
    }
}
