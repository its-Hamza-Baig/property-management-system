@extends('layouts.app')
@section('title', $account->exists ? 'Edit account' : 'Add cash or bank account')

@section('content')
    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ $account->exists ? route('accounts.update', $account) : route('accounts.store') }}">
                        @csrf
                        @if ($account->exists) @method('PUT') @endif

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label required">Account name</label>
                                <input name="name" value="{{ old('name', $account->name) }}"
                                       class="form-control @error('name') is-invalid @enderror" required autofocus>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">Type</label>
                                @if ($account->exists)
                                    <input class="form-control" value="{{ $account->typeLabel() }}" disabled>
                                @else
                                    <select name="type" class="form-select" required>
                                        <option value="cash" @selected(old('type', $account->type) === 'cash')>Cash</option>
                                        <option value="bank" @selected(old('type', $account->type) === 'bank')>Bank</option>
                                    </select>
                                @endif
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Bank name</label>
                                <input name="bank_name" value="{{ old('bank_name', $account->bank_name) }}" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Account number</label>
                                <input name="account_number" value="{{ old('account_number', $account->account_number) }}" class="form-control">
                            </div>

                            @unless ($account->exists)
                                <div class="col-md-6">
                                    <label class="form-label required">Opening balance</label>
                                    <input name="opening_balance" type="number" step="0.01" min="0"
                                           value="{{ old('opening_balance', '0') }}" class="form-control" required>
                                    <div class="form-text">Posted once when the account is created. It cannot be edited later, because that would restate history.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">Opening date</label>
                                    <input name="opening_date" type="date" value="{{ old('opening_date', now()->toDateString()) }}"
                                           class="form-control" required>
                                </div>
                            @else
                                <div class="col-12">
                                    <div class="form-check">
                                        <input type="hidden" name="is_active" value="0">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                               id="is_active" @checked(old('is_active', $account->is_active))>
                                        <label class="form-check-label" for="is_active">Account is active</label>
                                    </div>
                                    <div class="form-text">
                                        Opening balance was {{ pkr($account->opening_balance) }} on {{ pms_date($account->opening_date) }}.
                                        To correct it, post an adjustment rather than editing the account.
                                    </div>
                                </div>
                            @endunless
                        </div>

                        <div class="mt-3">
                            <button class="btn btn-primary">{{ $account->exists ? 'Save changes' : 'Create account' }}</button>
                            <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
