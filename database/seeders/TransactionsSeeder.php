<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TransactionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = ['Sales Invoice', 'Purchase Invoice', 'Payment', 'Receipt', 'Journal Entry'];
        $sourceDbs = ['Accurate_DB_001', 'Accurate_DB_002', 'Accurate_DB_003'];
        $statuses = ['pending', 'success', 'failed'];
        
        $transactions = [];
        
        for ($i = 1; $i <= 50; $i++) {
            $module = $modules[array_rand($modules)];
            $sourceDb = $sourceDbs[array_rand($sourceDbs)];
            $status = $statuses[array_rand($statuses)];
            $capturedDate = Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23));
            
            // Generate realistic data based on module type
            $data = $this->generateDataByModule($module, $i);
            
            $transactions[] = [
                'transaction_no' => 'TRX-' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'source_db' => $sourceDb,
                'module' => $module,
                'data' => json_encode($data),
                'description' => $this->generateDescription($module, $status),
                'captured_at' => $capturedDate,
                'status' => $status,
                'created_at' => $capturedDate,
                'updated_at' => $capturedDate,
            ];
        }
        
        DB::table('transactions')->insert($transactions);
    }
    
    private function generateDataByModule($module, $index)
    {
        switch ($module) {
            case 'Sales Invoice':
                return [
                    'invoice_no' => 'SI-' . str_pad($index, 5, '0', STR_PAD_LEFT),
                    'customer_name' => 'Customer ' . chr(64 + ($index % 26 + 1)),
                    'amount' => rand(1000000, 50000000),
                    'tax' => rand(100000, 5000000),
                    'total' => rand(1100000, 55000000),
                    'payment_terms' => rand(1, 3) == 1 ? 'Cash' : 'Credit',
                    'items' => [
                        [
                            'product_name' => 'Product ' . rand(1, 100),
                            'quantity' => rand(1, 50),
                            'unit_price' => rand(50000, 1000000),
                        ]
                    ]
                ];
                
            case 'Purchase Invoice':
                return [
                    'invoice_no' => 'PI-' . str_pad($index, 5, '0', STR_PAD_LEFT),
                    'supplier_name' => 'Supplier ' . chr(64 + ($index % 26 + 1)),
                    'amount' => rand(2000000, 80000000),
                    'tax' => rand(200000, 8000000),
                    'total' => rand(2200000, 88000000),
                    'payment_terms' => rand(1, 3) == 1 ? 'Cash' : 'Credit',
                    'items' => [
                        [
                            'product_name' => 'Raw Material ' . rand(1, 50),
                            'quantity' => rand(10, 100),
                            'unit_price' => rand(100000, 2000000),
                        ]
                    ]
                ];
                
            case 'Payment':
                return [
                    'payment_no' => 'PAY-' . str_pad($index, 5, '0', STR_PAD_LEFT),
                    'payee' => 'Vendor ' . chr(64 + ($index % 26 + 1)),
                    'amount' => rand(5000000, 100000000),
                    'payment_method' => ['Transfer', 'Cash', 'Check', 'Giro'][rand(0, 3)],
                    'bank_account' => 'BCA - 1234567890',
                    'reference_no' => 'REF-' . rand(10000, 99999),
                ];
                
            case 'Receipt':
                return [
                    'receipt_no' => 'RCP-' . str_pad($index, 5, '0', STR_PAD_LEFT),
                    'payer' => 'Customer ' . chr(64 + ($index % 26 + 1)),
                    'amount' => rand(5000000, 100000000),
                    'payment_method' => ['Transfer', 'Cash', 'Check', 'Giro'][rand(0, 3)],
                    'bank_account' => 'Mandiri - 0987654321',
                    'reference_no' => 'REF-' . rand(10000, 99999),
                ];
                
            case 'Journal Entry':
                return [
                    'journal_no' => 'JE-' . str_pad($index, 5, '0', STR_PAD_LEFT),
                    'description' => 'General Journal Entry',
                    'entries' => [
                        [
                            'account' => 'Cash in Bank',
                            'debit' => rand(1000000, 50000000),
                            'credit' => 0,
                        ],
                        [
                            'account' => 'Accounts Receivable',
                            'debit' => 0,
                            'credit' => rand(1000000, 50000000),
                        ]
                    ]
                ];
                
            default:
                return [];
        }
    }
    
    private function generateDescription($module, $status)
    {
        if ($status === 'success') {
            return "Successfully migrated {$module} data to target database";
        } elseif ($status === 'failed') {
            return "Failed to migrate {$module} data: " . ['Connection timeout', 'Invalid data format', 'Duplicate entry'][rand(0, 2)];
        } else {
            return "Waiting to migrate {$module} data";
        }
    }
}
