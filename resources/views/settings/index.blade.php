@extends('layouts.app')
@section('title', 'Settings')
@section('subheading', 'Approval rules, expense categories and what your plan allows.')

@section('actions')
    <a href="{{ route('settings.audit') }}" class="btn btn-sm btn-outline-secondary">Audit log</a>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Approval rules</div>
                <div class="card-body">
                    <p class="text-muted small">
                        Approval is off by default so routine entry is not slowed down. Switch it on for the actions
                        you want a second pair of eyes on. Leave the threshold empty to require approval every time,
                        or set an amount so only larger items are held up. Nobody can approve their own request.
                    </p>
                    <form method="POST" action="{{ route('settings.approvals') }}">
                        @csrf @method('PUT')
                        <table class="table table-sm align-middle">
                            <thead class="table-light"><tr><th>Action</th><th style="width:120px">Required</th><th style="width:200px">Only at or above</th></tr></thead>
                            <tbody>
                            @foreach ($rules as $rule)
                                <tr>
                                    <td>{{ $rule->actionLabel() }}</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="rules[{{ $rule->action }}][is_enabled]" value="0">
                                            <input class="form-check-input" type="checkbox" value="1"
                                                   name="rules[{{ $rule->action }}][is_enabled]" @checked($rule->is_enabled)>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                               name="rules[{{ $rule->action }}][threshold_amount]"
                                               value="{{ $rule->threshold_amount }}" placeholder="Always">
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <button class="btn btn-sm btn-primary">Save approval rules</button>
                    </form>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Expense categories</div>
                <div class="card-body">
                    <p class="text-muted small">
                        A capitalised category increases the cost base of the property, which also raises the cap on
                        investor capital. Everything else reduces profit. Changing a category here never restates an
                        expense that has already been confirmed.
                    </p>

                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle">
                            <thead class="table-light"><tr><th>Name</th><th>Applies to</th><th>Capitalised</th><th>Active</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <form method="POST" action="{{ route('settings.categories.update', $category) }}">
                                        @csrf @method('PUT')
                                        <td><input name="name" value="{{ $category->name }}" class="form-control form-control-sm"></td>
                                        <td class="small">{{ $category->appliesToLabel() }}</td>
                                        <td>
                                            <input type="hidden" name="is_capitalized" value="0">
                                            <input type="checkbox" name="is_capitalized" value="1" class="form-check-input" @checked($category->is_capitalized)>
                                        </td>
                                        <td>
                                            <input type="hidden" name="is_active" value="0">
                                            <input type="checkbox" name="is_active" value="1" class="form-check-input" @checked($category->is_active)>
                                        </td>
                                        <td><button class="btn btn-sm btn-outline-secondary">Save</button></td>
                                    </form>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" action="{{ route('settings.categories.store') }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-5">
                            <label class="form-label small mb-1 required">New category</label>
                            <input name="name" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1 required">Applies to</label>
                            <select name="applies_to" class="form-select form-select-sm">
                                <option value="purchase">Purchase</option>
                                <option value="property">Property holding</option>
                                <option value="sale">Sale</option>
                                <option value="general">General</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_capitalized" value="1" id="newcap">
                                <label class="form-check-label small" for="newcap">Capitalised</label>
                            </div>
                        </div>
                        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Add</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Your plan</div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-7">Plan</dt><dd class="col-5 text-end">{{ $tenant->plan?->name ?? 'None' }}</dd>
                        <dt class="col-7">Active properties</dt>
                        <dd class="col-5 text-end">{{ $propertiesUsed }}{{ (int) $entitlements['max_properties'] === -1 ? '' : ' / '.$entitlements['max_properties'] }}</dd>
                        <dt class="col-7">Staff accounts</dt>
                        <dd class="col-5 text-end">{{ $staffUsed }}{{ (int) $entitlements['max_staff'] === -1 ? '' : ' / '.$entitlements['max_staff'] }}</dd>
                        <dt class="col-7">Document uploads</dt>
                        <dd class="col-5 text-end">{{ $entitlements['documents_enabled'] ? 'Enabled' : 'Not enabled' }}</dd>
                        <dt class="col-7">Storage allowance</dt>
                        <dd class="col-5 text-end">{{ $entitlements['max_storage_mb'] }} MB</dd>
                        <dt class="col-7">Excel and PDF export</dt>
                        <dd class="col-5 text-end">{{ $entitlements['exports_enabled'] ? 'Enabled' : 'Not enabled' }}</dd>
                        <dt class="col-7">Investor portal</dt>
                        <dd class="col-5 text-end">{{ $entitlements['investor_portal_enabled'] ? 'Enabled' : 'Not enabled' }}</dd>
                    </dl>
                    <hr>
                    <div class="text-muted small">
                        Limits are set by the platform administrator. If a limit is lowered, nothing you already
                        have is deleted; only new actions are affected.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
