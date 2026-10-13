@extends('layouts.app')
@section('title', $tenant->name)
@section('subheading', 'Account status, plan and limits. Business data is not shown here.')

@section('actions')
    <a href="{{ route('admin.accounts.index') }}" class="btn btn-sm btn-outline-secondary">All accounts</a>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Account</div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5">Status</dt><dd class="col-7">{{ $tenant->statusLabel() }}</dd>
                        <dt class="col-5">Contact</dt><dd class="col-7">{{ $tenant->contact_email }}</dd>
                        <dt class="col-5">Phone</dt><dd class="col-7">{{ $tenant->contact_phone ?: '-' }}</dd>
                        <dt class="col-5">Requested</dt><dd class="col-7">{{ pms_date($tenant->created_at) }}</dd>
                        <dt class="col-5">Activated</dt><dd class="col-7">{{ pms_date($tenant->activated_at) }}</dd>
                        <dt class="col-5">Active properties</dt><dd class="col-7">{{ $propertiesActive }} (of {{ $propertiesTotal }} total)</dd>
                        <dt class="col-5">Active staff</dt><dd class="col-7">{{ $staffActive }}</dd>
                    </dl>
                    @if ($tenant->status_reason)
                        <hr><div class="small text-muted">Reason on file: {{ $tenant->status_reason }}</div>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Users</div>
                <ul class="list-group list-group-flush">
                    @foreach ($tenant->users as $user)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div>{{ $user->name }}</div>
                                <div class="text-muted small">{{ $user->email }}</div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-secondary">{{ $user->roleLabel() }}</span>
                                @unless ($user->is_active)<span class="badge bg-danger">Inactive</span>@endunless
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Status actions</div>
                <div class="card-body">
                    @if (in_array($tenant->status, ['pending_approval', 'pending_verification', 'rejected']))
                        <form method="POST" action="{{ route('admin.accounts.activate', $tenant) }}" class="mb-3">
                            @csrf
                            <label class="form-label small mb-1">Activate on plan</label>
                            <div class="input-group input-group-sm">
                                <select name="plan_id" class="form-select">
                                    @foreach ($plans as $plan)
                                        <option value="{{ $plan->id }}" @selected($tenant->plan_id === $plan->id || $plan->is_default)>{{ $plan->name }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-success">Activate</button>
                            </div>
                            <div class="form-text">Sets up the chart of accounts, expense categories and approval rules.</div>
                        </form>

                        <form method="POST" action="{{ route('admin.accounts.reject', $tenant) }}">
                            @csrf
                            <label class="form-label small mb-1 required">Reject with reason</label>
                            <textarea name="reason" rows="2" class="form-control form-control-sm mb-2" required minlength="5"></textarea>
                            <button class="btn btn-sm btn-outline-danger">Reject request</button>
                        </form>
                    @elseif ($tenant->status === 'active')
                        <form method="POST" action="{{ route('admin.accounts.suspend', $tenant) }}">
                            @csrf
                            <label class="form-label small mb-1 required">Suspend with reason</label>
                            <textarea name="reason" rows="2" class="form-control form-control-sm mb-2" required minlength="5"></textarea>
                            <button class="btn btn-sm btn-outline-danger">Suspend account</button>
                            <div class="form-text">Blocks access. Nothing is deleted.</div>
                        </form>
                    @elseif ($tenant->status === 'suspended')
                        <form method="POST" action="{{ route('admin.accounts.reactivate', $tenant) }}">
                            @csrf
                            <button class="btn btn-sm btn-success">Reactivate account</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white fw-semibold">Support access</div>
                <div class="card-body">
                    @if ($activeGrant)
                        <div class="alert alert-warning small">
                            A support session is open until {{ $activeGrant->expires_at->format('d M Y H:i') }}.
                        </div>
                    @endif
                    <p class="text-muted small">
                        You have no standing access to this customer business data. Starting a session requires a
                        written reason, expires automatically, and writes every page you open into their audit log.
                    </p>
                    <form method="POST" action="{{ route('admin.support.store', $tenant) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small mb-1 required">Reason (at least 15 characters)</label>
                            <textarea name="reason" rows="2" class="form-control form-control-sm" required minlength="15"
                                      placeholder="e.g. Customer ticket 142: investor statement shows wrong total"></textarea>
                        </div>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Minutes</span>
                            <input name="minutes" type="number" min="5" max="480" value="30" class="form-control" required>
                            <button class="btn btn-outline-warning" @disabled(! $tenant->isActive())>Start support session</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-white fw-semibold">Plan and limits</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.accounts.entitlements', $tenant) }}">
                        @csrf @method('PUT')

                        <div class="mb-3">
                            <label class="form-label">Plan</label>
                            <select name="plan_id" class="form-select">
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->id }}" @selected($tenant->plan_id === $plan->id)>{{ $plan->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <p class="text-muted small">
                            These override the plan for this account only. Leave a field as the plan value and no
                            override is stored. Lowering a limit never deletes anything: it only applies to what the
                            account does next.
                        </p>

                        <table class="table table-sm align-middle">
                            <thead class="table-light"><tr><th>Entitlement</th><th style="width:220px">Value</th></tr></thead>
                            <tbody>
                            @foreach ($definitions as $key => $definition)
                                <tr>
                                    <td>
                                        {{ $definition['label'] }}
                                        <div class="text-muted" style="font-size:.75rem">{{ $definition['help'] }}</div>
                                        @if ($overrides->has($key))
                                            <span class="badge bg-info text-dark">Overridden</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($definition['type'] === 'bool')
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="entitlements[{{ $key }}]" value="0">
                                                <input class="form-check-input" type="checkbox" value="1"
                                                       name="entitlements[{{ $key }}]" @checked($effective[$key])>
                                            </div>
                                        @elseif ($definition['type'] === 'csv')
                                            <input name="entitlements[{{ $key }}]" class="form-control form-control-sm"
                                                   value="{{ implode(',', (array) $effective[$key]) }}">
                                        @else
                                            <input name="entitlements[{{ $key }}]" type="number" class="form-control form-control-sm"
                                                   value="{{ $effective[$key] }}">
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>

                        <button class="btn btn-primary">Save plan and limits</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
