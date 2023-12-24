# Create accounts for any model to withdraw and deposit to it

[![Latest Version on Packagist](https://img.shields.io/packagist/v/abather/mini-accounting.svg?style=flat-square)](https://packagist.org/packages/abather/mini-accounting)
[![Total Downloads](https://img.shields.io/packagist/dt/abather/mini-accounting.svg?style=flat-square)](https://packagist.org/packages/abather/mini-accounting)

## Adding an Account to Your Models and Tracking Transactions

To seamlessly integrate account functionality into your Laravel models and keep track of transactions, follow the steps
outlined below.

### Installation

Begin by installing the package via Composer:

```bash
composer require abather/mini-accounting
```

Next, publish and run the migrations:

```bash
php artisan vendor:publish --tag="mini-accounting-migrations"
php artisan migrate
```

You can also publish the configuration file:

```bash
php artisan vendor:publish --tag="mini-accounting-config"
```

The contents of the published configuration file (`config/mini-accounting.php`) will look like this:

```php
return [
    "prevent_duplication" => true,
    "currency_precision" => 2
];
```

### Usage

This package links two entities: **Accountable** (a model with an account) and **Referencable** (a document triggering
account deposits or withdrawals).

#### Accountable

To make a model accountable, use the `HasAccountMovement` trait:

```php
<?php

namespace App\Models;

use Abather\MiniAccounting\Traits\HasAccountMovement;
use Illuminate\Database\Eloquent\Model;

class System extends Model
{
    use HasAccountMovement;

    // Your code
}
```

Now you can deposit or withdraw any amount from it by referring to any reference model:

```php
$system->deposit("Description Of The Transaction", 350, $bill);
```

You can also provide notes or extra data in JSON format to any transaction:

```php
$system->deposit("Description Of The Transaction", 350, $bill, "Extra Notes", $json);
```

To get the model's current balance:

```php
$system->balance
```

You can also retrieve the model's balance at the end of any month:

```php
$system->balanceAtEndOfMonth(10)
// Returns the system balance at the end of month 10
```

you can also pass the year for this function if you went any year other than this year `balanceAtEndOfMonth(6, 1990)`

also you can get the balance for any given year:

```php
$system->balanceAtEndOfYear()
// Returns the system balance at the end of this year
```

as end of month balance you can specify the year `balanceAtEndOfYear(1990)`

#### Referencable

Make any model referencable by using the `Referencable` trait and implementing the `Referencable` interface:

```php
<?php

namespace App\Models;

use Abather\MiniAccounting\Contracts\Referencable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model implements Referencable
{
    use \Abather\MiniAccounting\Traits\Referencable;
    use HasFactory;

    public function defaultTransactions(): array
    {
        return [];
    }
}
```

Define the `defaultTransactions(): array` method. This method will be described later. Similar to the accountable model,
you can deposit and withdraw from the referencable model, affecting different accounts simultaneously. For example, when
a user buys from a market, the bill will deposit the amount into the market's account and withdraw the same amount from
the user's account:

```php
$bill = Bill::first();
$bill->deposit("Deposit", $bill->amount, $bill->market);
$bill->withdraw("Withdraw", $bill->amount, $bill->user);
```

Assuming you have another transaction caused by the bill, such as a commission, your code would be:

```php
$bill = Bill::first();
$bill->deposit("Deposit", $bill->amount, $bill->market);
$bill->withdraw("Withdraw", $bill->amount, $bill->user);
$bill->withdraw("Withdraw", $bill->amount * 0.1, $bill->market);
$bill->deposit("Deposit", $bill->amount * 0.1, $system);
```

To simplify, define the default transactions for each referencable model using the `defaultTransactions` method:

```php
public function defaultTransactions(): array
{
    return [
        Withdraw::make($this, "description")
            ->setAccount(Account::make(\App\Models\User::class)->relationship("user"))
            ->setCalculation(Equal::make($this, "amount")),

        Deposit::make($this, "any description")
            ->setAccount(Account::make(\App\Models\Market::class)->relationship("market"))
            ->setCalculation(Equal::make($this, "amount")),

        Withdraw::make($this, "other description")
            ->setAccount(Account::make(\App\Models\Market::class)->relationship("market"))
            ->setCalculation(Percentage::make($this, "amount")
                                ->factor(StaticFactor::make(10))
            ),

        Deposit::make($this, "other description")
            ->setAccount(Account::make(\App\Models\System::class, System::first()))
            ->setCalculation(Percentage::make($this, "amount")
                                 ->factor(DynamicFactor::make($this, 'percentage'))
            ),
    ];
}
```

For each object (either `Deposit` or `Withdraw`), set the affected account and the calculation method used to determine the transaction amount.

- you can have *meta-Data* with the transactions using :

```php
$transaction->data = [
    'foo' => 'bar'
];

$transaction->data // ['foo' => 'bar']
  ```

- also you can add note to the transaction via ```->setNote("note")```

##### Account

Refer to the desired account in three ways: direct relationship from the current model, using a foreign key from the
current model, or giving it any ID for reference:

```php
Account::make(\App\Models\Market::class)->relationship("market");
```

In this example, specify the model in the `make` method; during calculation, the account will be the market linked with
the current entity (`$bill->market`). Other settings include:

- `variable('market_id')`: Provide any key from your model referring to the entity (`$bill->market_id`).
- `static(3)`: Lock the record with the ID `3`. Also, pass a second parameter to the `make()` method to specify the
    entity being referred to:

```php
Account::make(\App\Models\Market::class, $this->market)
```

In this way, you do not need to provide any other functions.

##### Calculation

For calculation, use the following objects:

- `Abather\MiniAccounting\Objects\Calculations\Equal`
- `Abather\MiniAccounting\Objects\Calculations\Subtraction`
- `Abather\MiniAccounting\Objects\Calculations\Addition`
- `Abather\MiniAccounting\Objects\Calculations\Percentage`

For each object, provide `make($resource, $attribute)`. In our previous example, `make($this, "amount")` means
the calculation will be on `$bill->amount`. Except for `Equal`, you must also define `factor`, which is the other side
of each equation. `Factor` can be either dynamic or a static value:

- `StaticFactor::make(10)`: The other side of the equation is 10 (e.g., `$bill->amount - 10`).
- `DynamicFactor::make($this, 'percentage')`: The other side of the equation is `$bill->percentage`.

After defining `defaultTransactions()`, use it by calling `executeDefaultTransactions()`. You are free to define as
many `transactions` methods as needed. Keep in mind that the name of each method should end with `Transactions`, and you
can run these transactions by calling the function with "execute" at the beginning of the method name (
e.g., `cancelTransactions()` runs transactions using `executeCancelTransactions()`).

**Note:**
If you ever use `__call($method, $parameters)` in

your `Accountable` or `Referencable` models, please add the following lines:

- Accountable:

```php
public function __call($method, $parameters)
{
    if (in_array($method, ["deposit", 'withdraw'])) {
        return $this->createAccountMovement(strtoupper($method), ...$parameters);
    }

    // Your code ...
    return parent::__call($method, $parameters);
}
```

- Referencable:

```php
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

    // Your code ...
    return parent::__call($method, $parameters);
}
```

This documentation pertains to a Laravel package designed to enhance models' capabilities by linking them to their
accounts. Additionally, it establishes links with other models to serve as reference documents, such as bills or
refunds.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Abather](https://github.com/Abather)
- [JoDeveloper](https://github.com/JoDeveloper)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
