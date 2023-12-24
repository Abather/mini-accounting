<?php

namespace Workbench\App\Models;

use Abather\MiniAccounting\Casts\MoneyCast;
use Abather\MiniAccounting\Contracts\Referencable;
use Abather\MiniAccounting\Objects\Account;
use Abather\MiniAccounting\Objects\Calculations\Equal;
use Abather\MiniAccounting\Objects\Calculations\Factors\StaticFactor;
use Abather\MiniAccounting\Objects\Calculations\Percentage;
use Abather\MiniAccounting\Objects\Data;
use Abather\MiniAccounting\Objects\Transactions\Deposit;
use Abather\MiniAccounting\Objects\Transactions\Withdraw;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\Database\Factories\BillFactory;

class Bill extends Model implements Referencable
{
    use HasFactory, \Abather\MiniAccounting\Traits\Referencable;

    protected $casts = [
        "amount" => MoneyCast::class,
        "data" => "array"
    ];

    protected static function newFactory()
    {
        return BillFactory::new();
    }

    public function defaultTransactions(): array
    {
        return [
            Withdraw::make($this, "description")
                ->setAccount(Account::make(\Workbench\App\Models\User::class)
                    ->relationship("user"))
                ->setCalculation(Equal::make($this, "amount"))
                ->setNote("your notes should be written here"),

            Deposit::make($this, "deposit to system")
                ->setAccount(Account::make(\Workbench\App\Models\System::class, System::first()))
                ->setCalculation(Percentage::make($this, "amount")
                    ->factor(StaticFactor::make(10))),
        ];
    }

    public function cancelTransactions(): array
    {
        return [
            Deposit::make($this, "description")
                ->setAccount(Account::make(\Workbench\App\Models\User::class)
                    ->relationship("user"))
                ->setCalculation(Equal::make($this, "amount"))
        ];
    }

    public function withDataTransactions(): array
    {
        return [
            Withdraw::make($this, "withdraw from user")
                ->setAccount(Account::make(\Workbench\App\Models\User::class)
                    ->relationship('user'))
                ->setCalculation(Equal::make($this, "amount"))
                ->setData(Data::make($this, "data"))
        ];
    }

    public function withCollectionDataTransactions(): array
    {
        return [
            Withdraw::make($this, "withdraw from user")
                ->setAccount(Account::make(\Workbench\App\Models\User::class)
                    ->relationship('user'))
                ->setCalculation(Equal::make($this, "amount"))
                ->setData(Data::make($this, "user"))
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Workbench\App\Models\User::class);
    }
}
