<?php

namespace Abather\MiniAccounting\Traits;

use Abather\MiniAccounting\Exceptions\DuplicateEntryException;
use Abather\MiniAccounting\Models\AccountMovement;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Referencable
{
    public function __call($method, $parameters)
    {
        if (str_starts_with($method, "execute") && str_ends_with($method, "Transactions")) {
            $method = str_replace("execute", "", $method);
            $method = lcfirst($method);
            return $this->executeTransactions($method);
        }

        if (in_array($method, ["deposit", 'withdraw'])) {
            return $this->createAccountMovement(strtoupper($method), ...$parameters);
        }

        return parent::__call($method, $parameters);
    }

    public function accountMovements(): MorphMany
    {
        return $this->morphMany(AccountMovement::class, 'reference');
    }

    private function createAccountMovement($type, $description, $amount, $account, $notes = null, array $data = [])
    {
        if (config("mini-accounting.prevent_duplication")) {
            throw_if($this->isDuplicated($account, $type), new DuplicateEntryException);
        }

        $factor = $type == AccountMovement::WITHDRAW ? -1 : 1;
        $account_movement = new AccountMovement;
        $account_movement->description = $description;
        $account_movement->amount = $amount;
        $account_movement->type = $type;
        $account_movement->previous_balance = $account->balance;
        $account_movement->balance = $account->balance + ($amount * $factor);
        $account_movement->accountable_id = $account->id;
        $account_movement->accountable_type = get_class($account);
        $account_movement->data = $data;
        $account_movement->notes = $notes;

        return $this->accountMovements()->save($account_movement);
    }

    public function getDepositAttribute()
    {
        return $this->accountMovements()->whereType(AccountMovement::DEPOSIT)
            ->sum('amount') ?? 0;
    }

    public function getWithdrawAttribute()
    {
        return $this->accountMovements()->whereType(AccountMovement::WITHDRAW)
            ->sum('amount') ?? 0;
    }

    public function getBalanceAttribute()
    {
        return $this->deposit - $this->withdraw;
    }

    public function executeTransactions($transactions = "defaultTransactions")
    {
        foreach ($this->{$transactions}() as $create_transaction) {
            $create_transaction->generateAccountTransaction();
        }
    }

    private function isDuplicated($account, $type)
    {
        return $this->accountMovements()
            ->where('accountable_id', $account->id)
            ->where('accountable_type', get_class($account))
            ->where('type', $type)
            ->exists();
    }
}
