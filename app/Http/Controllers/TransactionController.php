<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Transaction;
use App\Models\User;
use App\Rules\LuhnValidationRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $topUsers = $this->topUsers();
        return view('transaction.index', compact('topUsers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $this->validateTransaction($request);

        $source = $this->getCard(toEngNumber($data['source']));
        $destination = $this->getCard(toEngNumber($data['destination']));
        $amount = toEngNumber($data['amount']) + 500;

        $balance = $source->balance;
        $cardID = $source->id;

        if ($balance >= $amount) {

            DB::transaction(function () use ($cardID, $amount, $destination) {
                $card = Card::find($cardID);
                $card->balance -= $amount;
                $card->save();

                Transaction::create([
                    'card_id' => $cardID,
                    'destination_id' => $destination->id,
                    'amount' => $amount,
                ]);

            });
        } else {
            return 'اعتبار کافی نیست!';
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    protected function validateTransaction($request) {
        return $request->validate([
            'source' => ['required', 'size:16', new LuhnValidationRule],

            'destination' => ['required', 'size:16', new LuhnValidationRule, 'different:source'],

            'amount' => ['required', function ($attribute, $value, $fail) {
                $amount = toEngNumber($value);
                if (!is_numeric($amount)) {
                    $fail('مبلغ وارد شده نامعتبر می‌باشد.');
                }
                if ($amount < 1000) {
                    $fail('مبلغ زیر 1,000 تومان مجاز نمی‌باشد.');
                }
                if ($amount > 10000000) {
                    $fail('مبلغ بالای 10,000,000 تومان مجاز نمی‌باشد.');
                }
            }],
        ]);
    }

    /**
     * Get top 3 users with most transactions.
     */
    public function topUsers()
    {
        $transactionsCount = User::query()->get()->map(function ($user) {
            return [
                'user_id' => $user->id,
                'count' => $this->getTransactions($user)->count()];
        })->sortByDesc('count')->take(3);

        $userIDs = array_column($transactionsCount->toArray(), 'user_id');

        return User::query()->whereIn('id', $userIDs)->get()->map(function ($user) {
            return [
                'user' => $user,
                'transactions' => $this->getTransactions($user)->take(10),
            ];
        });
    }

    public function getCard($number)
    {
        return Card::query()->firstWhere('card_number', $number);
    }

    public function getTransactions($user)
    {
        return $user->accounts()->with('cards.transactions')->get()->pluck('cards')->flatten()->pluck('transactions')->flatten()->where('created_at', '>=', now()->subMinutes(10));
    }
}
