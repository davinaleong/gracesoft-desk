<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LedgerPrototypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Sales' => 'income',
            'Services' => 'income',
            'Other Income' => 'income',
            'Operations' => 'expense',
            'Software' => 'expense',
            'Marketing' => 'expense',
            'Payroll' => 'expense',
            'Professional Fees' => 'expense',
            'Tax' => 'expense',
            'Other Expense' => 'expense',
            'Transfer' => 'expense',
            'Refund' => 'expense',
        ];

        foreach ($categories as $name => $type) {
            TransactionCategory::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'type' => $type,
                    'is_active' => true,
                ]
            );
        }

        $methods = [
            'Bank Transfer',
            'PayNow',
            'Credit Card',
            'Debit Card',
            'Cash',
            'Cheque',
            'Stripe',
            'PayPal',
            'Wise',
            'GrabPay',
            'Internal Transfer',
        ];

        foreach ($methods as $methodName) {
            PaymentMethod::query()->updateOrCreate(
                ['slug' => Str::slug($methodName)],
                [
                    'name' => $methodName,
                    'is_active' => true,
                ]
            );
        }

        $accounts = [
            ['name' => 'OCBC', 'code' => 'BANK-OCBC', 'type' => 'bank'],
            ['name' => 'DBS', 'code' => 'BANK-DBS', 'type' => 'bank'],
            ['name' => 'CitiBank', 'code' => 'CARD-CITIBANK', 'type' => 'card'],
            ['name' => 'Cash', 'code' => 'CASH-MAIN', 'type' => 'cash'],
        ];

        foreach ($accounts as $account) {
            Account::query()->updateOrCreate(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'currency' => 'SGD',
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'is_active' => true,
                ]
            );
        }

        $transactions = [
            ['id' => 'TXN-2026-0001', 'date' => '25/1/2026', 'type' => 'Expense', 'category' => 'Operations', 'description' => 'Apply for new business entity name', 'counterparty' => 'ACRA', 'reference' => 'ACRA260125000831', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '15.000', 'gst' => '1.240', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0002', 'date' => '27/1/2026', 'type' => 'Expense', 'category' => 'Software', 'description' => 'Adobe Stock -10 assets a month', 'counterparty' => 'Adobe Systems Software Ireland Ltd', 'reference' => '3349521218', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '40.200', 'gst' => '3.320', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0003', 'date' => '28/1/2026', 'type' => 'Expense', 'category' => 'Operations', 'description' => 'Register new soleproprietorship/partnership (3 years)', 'counterparty' => 'ACRA', 'reference' => 'ACRA260128000658', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '160.000', 'gst' => '0.000', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0004', 'date' => '5/2/2026', 'type' => 'Expense', 'category' => 'Software', 'description' => 'ChatGPT Plus Subscription (per seat)', 'counterparty' => 'OpenAI OpCo, LLC', 'reference' => '2340CB76-0014', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '28.670', 'gst' => '2.370', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0005', 'date' => '9/5/2026', 'type' => 'Expense', 'category' => 'Software', 'description' => 'GitHub Copilot Usage + GitHub Copilot Pro - Month', 'counterparty' => 'GitHub, Inc.', 'reference' => 'INV119403102', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '35.490', 'gst' => '0.000', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0006', 'date' => '16/2/2026', 'type' => 'Expense', 'category' => 'Software', 'description' => 'Zoom Workplace Pro Monthly', 'counterparty' => 'Zoom Communications, Inc.', 'reference' => 'INV342080864', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '23.990', 'gst' => '2.160', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0007', 'date' => '17/2/2026', 'type' => 'Expense', 'category' => 'Software', 'description' => 'Creative Cloud Pro', 'counterparty' => 'Adobe Systems Software Ireland Ltd', 'reference' => '3369760188', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '77.730', 'gst' => '6.420', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0008', 'date' => '28/2/2026', 'type' => 'Expense', 'category' => 'Software', 'description' => 'Laravel Cloud Growth + Laravel Cloud Starter $', 'counterparty' => 'Laravel Holdings Inc.', 'reference' => 'LAR-D623-202602-92933', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '38.030', 'gst' => '3.140', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0009', 'date' => '4/3/2026', 'type' => 'Expense', 'category' => 'Marketing', 'description' => '1 x Early Bird HER x WoW + Masterclass', 'counterparty' => 'Eventbrite', 'reference' => '#14380215143', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '126.840', 'gst' => '0.000', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0010', 'date' => '16/3/2026', 'type' => 'Expense', 'category' => 'Software', 'description' => 'Professional Full seats (annual) + professional Dev seats', 'counterparty' => 'Figma, Inc.', 'reference' => '16A302EE-0015', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '277.270', 'gst' => '22.890', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0011', 'date' => '17/4/2026', 'type' => 'Expense', 'category' => 'Software', 'description' => 'Zoom Workplace Pro Annual', 'counterparty' => 'Zoom Communications, Inc.', 'reference' => 'INV350367244', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '261.490', 'gst' => '21.590', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0012', 'date' => '22/4/2026', 'type' => 'Expense', 'category' => 'Professional Fees', 'description' => 'Baton ID:VIC-NICF141-26-0654 FUNDAMENTALS OF THE PT NUC Learning Hub', 'counterparty' => 'NTUC Learning Hub', 'reference' => 'WS-26-008147', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '784.800', 'gst' => '64.800', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0013', 'date' => '24/2/2026', 'type' => 'Expense', 'category' => 'Professional Fees', 'description' => 'Cybersecurity for Developers: From Basics to Best Practice', 'counterparty' => 'Udemy', 'reference' => 'SG2026-14644', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '13.980', 'gst' => '0.000', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0014', 'date' => '24/2/2026', 'type' => 'Expense', 'category' => 'Professional Fees', 'description' => 'Complete GDPR, GDPR Certification, data protection 2024', 'counterparty' => 'Udemy', 'reference' => 'SG2026-14645', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '13.980', 'gst' => '0.000', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0015', 'date' => '26/4/2026', 'type' => 'Expense', 'category' => 'Software', 'description' => 'Hobby plan', 'counterparty' => 'Railway Corporation', 'reference' => '53TUDYXZ-0001', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '6.600', 'gst' => '0.540', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0016', 'date' => '27/4/2026', 'type' => 'Expense', 'category' => 'Professional Fees', 'description' => 'SIMBA mobile plan add-on (data roaming / subscription', 'counterparty' => 'SIMBA', 'reference' => '1045632378', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '10.000', 'gst' => '0.500', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0017', 'date' => '25/4/2026', 'type' => 'Expense', 'category' => 'Software', 'description' => 'DMARC Digests Subscription (per Domain)', 'counterparty' => 'DMARC Digests', 'reference' => 'SQFZ9X9Y-0003', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '18.500', 'gst' => '1.530', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0018', 'date' => '17/4/2026', 'type' => 'Expense', 'category' => 'Software', 'description' => 'Creative Cloud Pro', 'counterparty' => 'Adobe Systems Software Ireland Ltd', 'reference' => '3429979142', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '91.130', 'gst' => '7.520', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0019', 'date' => '27/4/2026', 'type' => 'Expense', 'category' => 'Software', 'description' => 'Adobe Stock -10 assets a month', 'counterparty' => 'Adobe Systems Software Ireland Ltd', 'reference' => '3440406141', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '40.200', 'gst' => '3.320', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0020', 'date' => '29/4/2026', 'type' => 'Expense', 'category' => 'Software', 'description' => 'Backblaze Services', 'counterparty' => 'BACKBLAZE', 'reference' => 'N/A', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '1.280', 'gst' => '0.000', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
            ['id' => 'TXN-2026-0021', 'date' => '30/4/2026', 'type' => 'Expense', 'category' => 'Software', 'description' => 'Laravel Cloud Growth', 'counterparty' => 'Laravel Holdings Inc.', 'reference' => 'LAR-D623-202604-118989', 'payment_method' => 'Credit Card', 'money_in' => '', 'money_out' => '129.160', 'gst' => '0.000', 'status' => 'Paid', 'account' => 'CitiBank', 'notes' => ''],
        ];

        foreach ($transactions as $row) {
            $category = TransactionCategory::query()->where('slug', Str::slug($row['category']))->first();
            $paymentMethod = PaymentMethod::query()->where('slug', Str::slug($row['payment_method']))->first();
            $account = Account::query()->where('name', $row['account'])->first();

            if (! $category || ! $paymentMethod || ! $account) {
                continue;
            }

            $moneyIn = $this->toMoney($row['money_in']);
            $moneyOut = $this->toMoney($row['money_out']);
            $amount = $moneyIn > 0 ? $moneyIn : $moneyOut;

            if ($amount <= 0) {
                continue;
            }

            $type = $this->normalizeType($row['type'], $moneyIn, $moneyOut);
            $direction = $type === 'income' ? 'in' : 'out';

            $descriptionParts = array_filter([
                trim((string) $row['description']),
                trim((string) $row['counterparty']) !== '' ? 'Counterparty: '.trim((string) $row['counterparty']) : null,
                trim((string) $row['notes']) !== '' ? 'Notes: '.trim((string) $row['notes']) : null,
            ]);

            Transaction::query()->updateOrCreate(
                ['transaction_code' => $row['id']],
                [
                    'account_id' => $account->id,
                    'transaction_category_id' => $category->id,
                    'payment_method_id' => $paymentMethod->id,
                    'project_id' => null,
                    'type' => $type,
                    'direction' => $direction,
                    'status' => $this->normalizeStatus($row['status']),
                    'transaction_date' => $this->normalizeDate($row['date']),
                    'reference' => trim((string) $row['reference']) !== '' ? trim((string) $row['reference']) : null,
                    'description' => implode(' | ', $descriptionParts),
                    'amount' => $amount,
                    'gst_amount' => $this->toMoney($row['gst']),
                ]
            );
        }
    }

    private function normalizeDate(string $value): string
    {
        $clean = trim($value);

        if ($clean === '') {
            return now()->toDateString();
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $clean) === 1) {
            return $clean;
        }

        return Carbon::createFromFormat('j/n/Y', $clean)->toDateString();
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pending' => 'pending',
            'cancelled', 'canceled', 'refunded' => 'void',
            default => 'completed',
        };
    }

    private function normalizeType(string $type, float $moneyIn, float $moneyOut): string
    {
        $normalized = strtolower(trim($type));

        if ($normalized === 'transfer') {
            return 'transfer';
        }

        if ($normalized === 'income') {
            return 'income';
        }

        if ($normalized === 'refund') {
            return $moneyIn > 0 ? 'income' : 'expense';
        }

        if ($moneyIn > 0 && $moneyOut <= 0) {
            return 'income';
        }

        return 'expense';
    }

    private function toMoney(string $value): float
    {
        $clean = str_replace([',', '$', ' '], '', trim($value));

        if ($clean === '') {
            return 0.0;
        }

        return is_numeric($clean) ? (float) $clean : 0.0;
    }
}
