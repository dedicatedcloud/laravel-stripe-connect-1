<?php

namespace SimonHamp\LaravelStripeConnect\Traits;

use Stripe\Account;
use Stripe\Balance;
use Stripe\Transfer;
use Stripe\StripeClient;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;
use SimonHamp\LaravelStripeConnect\Interfaces\StripeConnect;

trait Payable
{
    protected static StripeClient $stripe;

    protected Account $stripe_connect_account;

    protected static function bootPayable()
    {
        static::$stripe = App::make(StripeConnect::class);
    }

    /**
     * Create a new Stripe Connect account for this model
     */
    public function createStripeAccount(array $details): self
    {
        $this->stripe_connect_account = static::$stripe->accounts->create($details);

        $this->setStripeAccountId($this->stripe_connect_account->id)->save();

        return $this;
    }

    /**
     * Get the latest details about this account from Stripe
     */
    public function retrieveStripeAccount(): Account
    {
        return $this->stripe_connect_account = static::$stripe->accounts->retrieve($this->getStripeAccountId());
    }

    public function getStripeAccountId()
    {
        return $this->{$this->getStripeAccountIdColumn()};
    }

    public function isStripeAccountActive()
    {
        return $this->{$this->getStripeAccountStatusColumn()};
    }

    /**
     * Get the redirect URL needed to take this account through Stripe's onboarding flow
     */
    public function getStripeAccountLink($type = 'account_onboarding'): string
    {
        $link = static::$stripe->accountLinks->create(
            [
                'account' => $this->getStripeAccountId(),
                'refresh_url' => URL::route(Config::get('stripe_connect.routes.account.refresh')),
                'return_url' => URL::route(Config::get('stripe_connect.routes.account.return')),
                'type' => 'account_onboarding',
            ]
        );

        return $link->url;
    }

    public function pay($amount, $currency): Transfer
    {
        // TODO: capture this in the database, which may allow us to do a reversal later
        return static::$stripe->transfers->create([
            'amount' => $amount,
            'currency' => $currency,
            'destination' => $this->getStripeAccountId(),
        ]);
    }

    public function getAccountBalance(): Balance
    {
        return static::$stripe->balance->retrieve([
            'stripe_account' => $this->getStripeAccountId(),
        ]);
    }

    protected function getStripeAccountIdColumn()
    {
        return Config::get('stripe_connect.payable.account_id_column');
    }

    protected function setStripeAccountId($id)
    {
        $this->{$this->getStripeAccountIdColumn()} = $id;

        return $this;
    }

    protected function getStripeAccountStatusColumn()
    {
        return Config::get('stripe_connect.payable.account_status_column');
    }

    protected function setStripeAccountStatus($status)
    {
        $this->{$this->getStripeAccountStatusColumn()} = $status;

        return $this;
    }
}
