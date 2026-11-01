<x-layout title="API Keys — LeadsBoard">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h1>API Keys</h1>
            <p style="color: var(--on-surface-variant); font-size: 13px; margin-top: 4px;">
                Manage backend services, webhook pipelines, and client API keys with granular scopes, IP restrictions, and rate budgets.
            </p>
        </div>
        <button type="button" class="btn btn-primary" onclick="openCreateModal()">
            + Generate Key
        </button>
    </div>

    @if($errors->any())
        <div class="alert alert-error" style="margin-bottom: 20px;">
            <strong>Validation Errors:</strong>
            <ul style="margin-left: 20px; font-size: 13px; margin-top: 4px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('new_api_key'))
        <div class="alert alert-success" style="background: #e8f5e9; border: 1px solid var(--primary); padding: 18px 20px; border-radius: 8px; margin-bottom: 24px;">
            <div style="font-weight: 600; color: #155724; margin-bottom: 6px;">New API Key Generated</div>
            <p style="margin-bottom: 12px; font-size: 13px; color: var(--on-surface);">Copy this key now. It will not be displayed again.</p>
            <div style="background: #ffffff; padding: 12px 16px; border-radius: 6px; border: 1px solid var(--outline); font-family: monospace; font-size: 14px; font-weight: 600; user-select: all; letter-spacing: 0.5px; color: #08402f;">
                {{ session('new_api_key') }}
            </div>
        </div>
    @endif

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Prefix</th>
                    <th>Tier</th>
                    <th>Rate Limit</th>
                    <th style="min-width: 150px;">Scopes</th>
                    <th>Allowed IPs</th>
                    <th>Expiration</th>
                    <th>Status</th>
                    <th>Last Used</th>
                    <th>Created</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($apiKeys as $key)
                    @php
                        $keyScopes = $key->scopes->pluck('name')->all();
                        $hasWildcard = in_array('*', $keyScopes);
                        $keyPayload = [
                            'id' => $key->id,
                            'name' => $key->name,
                            'key_type' => $key->isPublic() ? 'public' : 'secret',
                            'tier_id' => $key->tier_id,
                            'rate_limit_per_minute' => $key->rate_limit_per_minute,
                            'is_active' => (bool)$key->is_active,
                            'allowed_ips' => is_array($key->allowed_ips) ? implode(', ', $key->allowed_ips) : ($key->allowed_ips ?? ''),
                            'expires_at' => $key->expires_at ? $key->expires_at->format('Y-m-d\TH:i') : '',
                            'scopes' => $keyScopes,
                        ];
                    @endphp
                    <tr>
                        <td><strong>{{ $key->name }}</strong></td>
                        <td>
                            <span class="chip" style="background: {{ $key->isPublic() ? 'rgba(33, 150, 243, 0.12)' : 'rgba(156, 39, 176, 0.12)' }}; color: {{ $key->isPublic() ? '#1565c0' : '#6a1b9a' }}; font-weight: 600;">
                                {{ strtoupper($key->key_type?->value ?? 'secret') }}
                            </span>
                        </td>
                        <td><code style="background: var(--surface); padding: 2px 6px; border-radius: 4px; font-size: 12px;">{{ $key->plain_text_prefix }}...</code></td>
                        <td>
                            <span class="chip" style="text-transform: capitalize; background: var(--surface); color: var(--on-surface);">{{ $key->tier?->name ?? 'Developer' }}</span>
                        </td>
                        <td>
                            @if($key->rate_limit_per_minute === 0)
                                <span class="chip chip-new">Unlimited</span>
                            @elseif($key->rate_limit_per_minute === null)
                                <span style="font-size: 12px; color: var(--on-surface-variant);">Tier Default ({{ $key->tier?->rate_limit_per_minute ?? 120 }}/m)</span>
                            @else
                                <span style="font-weight: 500;">{{ $key->rate_limit_per_minute }} / min</span>
                            @endif
                        </td>
                        <td style="white-space: normal; max-width: 220px;">
                            @if($hasWildcard)
                                <span class="chip" style="background: var(--primary-container); color: var(--on-primary-container); font-weight: 600;">Full Access (*)</span>
                            @elseif(count($keyScopes) > 0)
                                <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                    @foreach(array_slice($keyScopes, 0, 2) as $s)
                                        <span class="chip" style="font-size: 10px; padding: 2px 6px; background: var(--surface); color: var(--on-surface-variant); border: 1px solid var(--outline);">{{ $s }}</span>
                                    @endforeach
                                    @if(count($keyScopes) > 2)
                                        <span class="chip" title="{{ implode(', ', $keyScopes) }}" style="font-size: 10px; padding: 2px 6px; background: var(--surface-container); color: var(--on-surface-variant); cursor: help;">+{{ count($keyScopes) - 2 }} more</span>
                                    @endif
                                </div>
                            @else
                                <span style="color: var(--on-surface-variant); font-size: 12px; font-style: italic;">None</span>
                            @endif
                        </td>
                        <td>
                            @if(!empty($key->allowed_ips))
                                <span title="Allowed IPs: {{ is_array($key->allowed_ips) ? implode(', ', $key->allowed_ips) : $key->allowed_ips }}" style="cursor: help; font-size: 12px; font-weight: 500;">
                                    {{ count((array)$key->allowed_ips) }} IP(s)
                                </span>
                            @else
                                <span style="color: var(--on-surface-variant); font-size: 12px;">Unrestricted</span>
                            @endif
                        </td>
                        <td>
                            @if($key->expires_at)
                                <span style="font-size: 12px; color: {{ $key->expires_at->isPast() ? 'var(--error)' : 'var(--on-surface)' }};">
                                    {{ $key->expires_at->isPast() ? 'Expired' : $key->expires_at->format('M j, Y') }}
                                </span>
                            @else
                                <span style="color: var(--on-surface-variant); font-size: 12px;">Never</span>
                            @endif
                        </td>
                        <td>
                            @if($key->is_active)
                                <span class="chip chip-qualified">Active</span>
                            @else
                                <span class="chip chip-rejected">Inactive</span>
                            @endif
                        </td>
                        <td style="font-size: 12px;">{{ $key->last_used_at ? $key->last_used_at->diffForHumans() : 'Never' }}</td>
                        <td style="font-size: 12px;">{{ $key->created_at->format('M j, Y') }}</td>
                        <td style="text-align: right; white-space: nowrap;">
                            <button type="button" class="btn btn-sm btn-outline" onclick='openEditModal(@json($keyPayload))'>
                                Edit
                            </button>
                            <form action="{{ route('dashboard.api-keys.destroy', $key) }}" method="POST" style="display:inline-block;" onsubmit="return confirm('Revoke and delete this API key? This action is immediate and cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Revoke</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 40px; color: var(--on-surface-variant);">No API keys found. Generate a key to begin.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Create Modal --}}
    <dialog id="createKeyModal" onclick="if(event.target === this) this.close()" style="padding: 24px; border: 1px solid var(--outline); border-radius: 12px; margin: auto; max-width: 660px; width: 95%; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px var(--shadow-strong); background: var(--surface-low); color: var(--on-background);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h2 style="margin: 0; font-size: 18px;">Create API Key</h2>
            <button type="button" onclick="document.getElementById('createKeyModal').close()" style="background:none; border:none; font-size:20px; cursor:pointer; color: var(--on-surface-variant); line-height: 1;">&times;</button>
        </div>

        <form action="{{ route('dashboard.api-keys.store') }}" method="POST" id="createKeyForm">
            @csrf

            <div style="margin-bottom: 16px;">
                <label style="display:block; font-weight: 600; font-size: 12px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--on-surface-variant);">Name / Label</label>
                <input type="text" name="name" required placeholder="e.g. Production Webhook Pipeline" style="width: 100%; padding: 9px 12px; border: 1px solid var(--outline); border-radius: 6px; background: var(--background); color: var(--on-background); font-size: 13px;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display:block; font-weight: 600; font-size: 12px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--on-surface-variant);">Key Type</label>
                    <select name="key_type" id="createKeyType" onchange="handleKeyTypeChange('create')" style="width: 100%; padding: 9px 12px; border: 1px solid var(--outline); border-radius: 6px; background: var(--background); color: var(--on-background); font-size: 13px;">
                        <option value="secret" selected>Secret Key (lb_sec_... — Server & Backend)</option>
                        <option value="public">Public Key (lb_pub_... — Client Read-Only)</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-weight: 600; font-size: 12px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--on-surface-variant);">Assigned Tier</label>
                    <select name="tier_id" style="width: 100%; padding: 9px 12px; border: 1px solid var(--outline); border-radius: 6px; background: var(--background); color: var(--on-background); font-size: 13px;">
                        @foreach($tiers as $tierOption)
                            <option value="{{ $tierOption->id }}" {{ $tierOption->slug === 'developer' ? 'selected' : '' }}>
                                {{ $tierOption->name }} ({{ $tierOption->rate_limit_per_minute }} req/min)
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Scope Presets --}}
            <div style="margin-bottom: 16px;">
                <label style="display:block; font-weight: 600; font-size: 12px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--on-surface-variant);">Scope Presets</label>
                <div style="display: flex; flex-wrap: wrap; gap: 6px;" id="createPresetsContainer">
                    <button type="button" id="createPreset_all" class="preset-btn active" onclick="applyScopePreset('create', 'all')">
                        All Scopes (*)
                    </button>
                    <button type="button" id="createPreset_readwrite" class="preset-btn" onclick="applyScopePreset('create', 'readwrite')">
                        Read & Write
                    </button>
                    <button type="button" id="createPreset_readonly" class="preset-btn" onclick="applyScopePreset('create', 'readonly')">
                        Read Only
                    </button>
                    <button type="button" id="createPreset_webhooks" class="preset-btn" onclick="applyScopePreset('create', 'webhooks')">
                        Webhook Ingestion
                    </button>
                    <button type="button" id="createPreset_clear" class="preset-btn" onclick="applyScopePreset('create', 'clear')">
                        Clear All
                    </button>
                </div>
            </div>

            {{-- Granular Scopes Matrix --}}
            <div style="background: var(--surface); border: 1px solid var(--outline); border-radius: 8px; padding: 14px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <label style="font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--on-surface);">
                        Granular Permissions
                    </label>
                    <span id="createSelectedCount" style="font-size: 11px; color: var(--on-surface-variant); font-family: monospace;">All scopes selected</span>
                </div>

                {{-- Wildcard Checkbox --}}
                <div style="background: var(--background); border: 1px solid var(--outline); border-radius: 6px; padding: 8px 12px; margin-bottom: 12px;" id="createWildcardContainer">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 13px; color: var(--on-surface); cursor: pointer;">
                        <input type="checkbox" name="scopes[]" value="*" id="createScopeWildcard" checked onchange="handleWildcardCheckboxToggle('create', this.checked)">
                        <span>Full Access Wildcard (*)</span>
                        <span style="font-weight: 400; font-size: 12px; color: var(--on-surface-variant);">— Grants access to all current and future endpoints</span>
                    </label>
                </div>

                {{-- Grouped Resources Grid --}}
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px;" id="createGranularScopesGrid">
                    @foreach($scopesByResource as $resource => $resourceScopes)
                        <div style="background: var(--background); border: 1px solid var(--outline); border-radius: 6px; padding: 10px;">
                            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--primary); margin-bottom: 8px;">
                                {{ $resource }}
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                @foreach($resourceScopes as $s)
                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--on-surface); cursor: pointer;">
                                        <input type="checkbox" name="scopes[]" value="{{ $s->name }}" class="create-granular-scope" data-action="{{ $s->action }}" checked onchange="handleGranularScopeChange('create')">
                                        {{ $s->action }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Advanced Controls --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display:block; font-weight: 600; font-size: 12px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--on-surface-variant);">Rate Limit Override (req/min)</label>
                    <input type="number" name="rate_limit_per_minute" value="" placeholder="Leave blank for tier default, or 0 for unlimited" min="0" style="width: 100%; padding: 9px 12px; border: 1px solid var(--outline); border-radius: 6px; background: var(--background); color: var(--on-background); font-size: 13px;">
                    <small style="color: var(--on-surface-variant); font-size: 11px; display:block; margin-top: 4px;">Leave blank for tier default, or set 0 for unlimited.</small>
                </div>
                <div>
                    <label style="display:block; font-weight: 600; font-size: 12px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--on-surface-variant);">Expiration Date (Optional)</label>
                    <input type="datetime-local" name="expires_at" style="width: 100%; padding: 9px 12px; border: 1px solid var(--outline); border-radius: 6px; background: var(--background); color: var(--on-background); font-size: 13px;">
                    <small style="color: var(--on-surface-variant); font-size: 11px; display:block; margin-top: 4px;">Leave blank for permanent key (never expires).</small>
                </div>
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display:block; font-weight: 600; font-size: 12px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--on-surface-variant);">Allowed IPs Whitelist (Optional)</label>
                <input type="text" name="allowed_ips" placeholder="e.g. 127.0.0.1, 10.0.0.0/24" style="width: 100%; padding: 9px 12px; border: 1px solid var(--outline); border-radius: 6px; background: var(--background); color: var(--on-background); font-size: 13px;">
                <small style="color: var(--on-surface-variant); font-size: 11px; display:block; margin-top: 4px;">Comma or newline separated IPv4/IPv6 addresses or CIDR blocks. Leave blank for unrestricted access.</small>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--outline); padding-top: 16px;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('createKeyModal').close()">Cancel</button>
                <button type="submit" class="btn btn-primary">Generate Key</button>
            </div>
        </form>
    </dialog>

    {{-- Edit Modal --}}
    <dialog id="editKeyModal" onclick="if(event.target === this) this.close()" style="padding: 24px; border: 1px solid var(--outline); border-radius: 12px; margin: auto; max-width: 660px; width: 95%; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px var(--shadow-strong); background: var(--surface-low); color: var(--on-background);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <h2 style="margin: 0; font-size: 18px;">Edit API Key</h2>
                <span id="editKeyTypeBadge" class="chip" style="font-weight: 600;">SECRET</span>
            </div>
            <button type="button" onclick="document.getElementById('editKeyModal').close()" style="background:none; border:none; font-size:20px; cursor:pointer; color: var(--on-surface-variant); line-height: 1;">&times;</button>
        </div>

        <div id="editPublicKeyNotice" style="display:none; background: #e3f2fd; border: 1px solid #90caf9; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; font-size: 12px; color: #1565c0;">
            Public keys are restricted to read-only scopes. Write, delete, and wildcard permissions cannot be assigned.
        </div>

        <form id="editForm" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="scopes_submitted" value="1">

            <div style="margin-bottom: 16px;">
                <label style="display:block; font-weight: 600; font-size: 12px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--on-surface-variant);">Name / Label</label>
                <input type="text" id="editName" name="name" required style="width: 100%; padding: 9px 12px; border: 1px solid var(--outline); border-radius: 6px; background: var(--background); color: var(--on-background); font-size: 13px;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display:block; font-weight: 600; font-size: 12px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--on-surface-variant);">Tier</label>
                    <select id="editTierId" name="tier_id" style="width: 100%; padding: 9px 12px; border: 1px solid var(--outline); border-radius: 6px; background: var(--background); color: var(--on-background); font-size: 13px;">
                        @foreach($tiers as $tierOption)
                            <option value="{{ $tierOption->id }}">
                                {{ $tierOption->name }} ({{ $tierOption->rate_limit_per_minute }} req/min)
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-weight: 600; font-size: 12px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--on-surface-variant);">Rate Limit (req/min)</label>
                    <input type="number" id="editRateLimit" name="rate_limit_per_minute" min="0" placeholder="Tier default" style="width: 100%; padding: 9px 12px; border: 1px solid var(--outline); border-radius: 6px; background: var(--background); color: var(--on-background); font-size: 13px;">
                    <small style="color: var(--on-surface-variant); font-size: 11px; display:block; margin-top: 4px;">Leave blank for tier default, or 0 for unlimited.</small>
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display:flex; align-items: center; gap: 8px; font-weight: 600; font-size: 13px; color: var(--on-surface); cursor: pointer;">
                    <input type="checkbox" id="editIsActive" name="is_active" value="1">
                    Key is Active and Enabled
                </label>
            </div>

            {{-- Scope Presets for Edit --}}
            <div style="margin-bottom: 16px;">
                <label style="display:block; font-weight: 600; font-size: 12px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--on-surface-variant);">Scope Presets</label>
                <div style="display: flex; flex-wrap: wrap; gap: 6px;" id="editPresetsContainer">
                    <button type="button" id="editPreset_all" class="preset-btn" onclick="applyScopePreset('edit', 'all')">
                        All Scopes (*)
                    </button>
                    <button type="button" id="editPreset_readwrite" class="preset-btn" onclick="applyScopePreset('edit', 'readwrite')">
                        Read & Write
                    </button>
                    <button type="button" id="editPreset_readonly" class="preset-btn" onclick="applyScopePreset('edit', 'readonly')">
                        Read Only
                    </button>
                    <button type="button" id="editPreset_webhooks" class="preset-btn" onclick="applyScopePreset('edit', 'webhooks')">
                        Webhook Ingestion
                    </button>
                    <button type="button" id="editPreset_clear" class="preset-btn" onclick="applyScopePreset('edit', 'clear')">
                        Clear All
                    </button>
                </div>
            </div>

            {{-- Granular Scopes Matrix for Edit --}}
            <div style="background: var(--surface); border: 1px solid var(--outline); border-radius: 8px; padding: 14px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <label style="font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--on-surface);">
                        Granular Permissions
                    </label>
                    <span id="editSelectedCount" style="font-size: 11px; color: var(--on-surface-variant); font-family: monospace;">0 scopes selected</span>
                </div>

                {{-- Wildcard Checkbox --}}
                <div style="background: var(--background); border: 1px solid var(--outline); border-radius: 6px; padding: 8px 12px; margin-bottom: 12px;" id="editWildcardContainer">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 13px; color: var(--on-surface); cursor: pointer;">
                        <input type="checkbox" name="scopes[]" value="*" id="editScopeWildcard" onchange="handleWildcardCheckboxToggle('edit', this.checked)">
                        <span>Full Access Wildcard (*)</span>
                        <span style="font-weight: 400; font-size: 12px; color: var(--on-surface-variant);">— Grants access to all current and future endpoints</span>
                    </label>
                </div>

                {{-- Grouped Resources Grid --}}
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px;" id="editGranularScopesGrid">
                    @foreach($scopesByResource as $resource => $resourceScopes)
                        <div style="background: var(--background); border: 1px solid var(--outline); border-radius: 6px; padding: 10px;">
                            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--primary); margin-bottom: 8px;">
                                {{ $resource }}
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                @foreach($resourceScopes as $s)
                                    <label style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--on-surface); cursor: pointer;">
                                        <input type="checkbox" name="scopes[]" value="{{ $s->name }}" class="edit-granular-scope" data-action="{{ $s->action }}" onchange="handleGranularScopeChange('edit')">
                                        {{ $s->action }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display:block; font-weight: 600; font-size: 12px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--on-surface-variant);">Expiration Date</label>
                    <input type="datetime-local" id="editExpiresAt" name="expires_at" style="width: 100%; padding: 9px 12px; border: 1px solid var(--outline); border-radius: 6px; background: var(--background); color: var(--on-background); font-size: 13px;">
                    <small style="color: var(--on-surface-variant); font-size: 11px; display:block; margin-top: 4px;">Clear to remove expiration date (never expires).</small>
                </div>
                <div>
                    <label style="display:block; font-weight: 600; font-size: 12px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--on-surface-variant);">Allowed IPs Whitelist</label>
                    <input type="text" id="editAllowedIps" name="allowed_ips" placeholder="e.g. 127.0.0.1, 10.0.0.0/24" style="width: 100%; padding: 9px 12px; border: 1px solid var(--outline); border-radius: 6px; background: var(--background); color: var(--on-background); font-size: 13px;">
                    <small style="color: var(--on-surface-variant); font-size: 11px; display:block; margin-top: 4px;">Comma or newline separated IPv4/IPv6 addresses or CIDR blocks. Empty = unrestricted.</small>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--outline); padding-top: 16px;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('editKeyModal').close()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </dialog>

    @push('styles')
    <style>
        .preset-btn {
            padding: 5px 11px;
            font-size: 12px;
            font-weight: 500;
            border-radius: 6px;
            border: 1px solid var(--outline);
            background: var(--surface);
            color: var(--on-surface);
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .preset-btn:hover:not(:disabled) {
            background: var(--surface-hover);
            border-color: var(--outline-strong);
        }
        .preset-btn.active {
            background: var(--primary-container);
            color: var(--on-primary-container);
            border-color: var(--primary);
            font-weight: 600;
        }
        .preset-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
    </style>
    @endpush

    @push('scripts')
    <script>
        function openCreateModal() {
            document.getElementById('createKeyForm').reset();
            handleKeyTypeChange('create');
            applyScopePreset('create', 'all');
            document.getElementById('createKeyModal').showModal();
        }

        function setActivePresetButton(prefix, presetKey) {
            const container = document.getElementById(prefix + 'PresetsContainer');
            if (!container) return;
            const buttons = container.querySelectorAll('.preset-btn');
            buttons.forEach(btn => btn.classList.remove('active'));

            if (presetKey) {
                const targetBtn = document.getElementById(prefix + 'Preset_' + presetKey);
                if (targetBtn && !targetBtn.disabled) {
                    targetBtn.classList.add('active');
                }
            }
        }

        function updateScopeSummaryText(prefix) {
            const summarySpan = document.getElementById(prefix + 'SelectedCount');
            if (!summarySpan) return;

            const wildcardCheckbox = document.getElementById(prefix + 'ScopeWildcard');
            if (wildcardCheckbox && wildcardCheckbox.checked) {
                summarySpan.innerText = 'Full Access (*) granted';
                summarySpan.style.color = 'var(--primary)';
                return;
            }

            const totalCount = document.querySelectorAll('.' + prefix + '-granular-scope').length;
            const checkedCount = document.querySelectorAll('.' + prefix + '-granular-scope:checked').length;

            if (checkedCount === 0) {
                summarySpan.innerText = '0 scopes selected (no access)';
                summarySpan.style.color = 'var(--error)';
            } else if (checkedCount === totalCount) {
                summarySpan.innerText = `All ${totalCount} granular scopes selected`;
                summarySpan.style.color = 'var(--primary)';
            } else {
                summarySpan.innerText = `${checkedCount} of ${totalCount} scopes selected`;
                summarySpan.style.color = 'var(--on-surface-variant)';
            }
        }

        function handleKeyTypeChange(prefix) {
            const keyTypeSelect = document.getElementById(prefix + 'KeyType');
            const isPublic = keyTypeSelect && keyTypeSelect.value === 'public';
            const checkboxes = document.querySelectorAll('.' + prefix + '-granular-scope');
            const wildcardCheckbox = document.getElementById(prefix + 'ScopeWildcard');
            const wildcardContainer = document.getElementById(prefix + 'WildcardContainer');

            const presetAll = document.getElementById(prefix + 'Preset_all');
            const presetReadWrite = document.getElementById(prefix + 'Preset_readwrite');
            const presetWebhooks = document.getElementById(prefix + 'Preset_webhooks');

            if (isPublic) {
                if (wildcardCheckbox) {
                    wildcardCheckbox.checked = false;
                    wildcardCheckbox.disabled = true;
                }
                if (wildcardContainer) {
                    wildcardContainer.style.opacity = '0.4';
                    wildcardContainer.style.pointerEvents = 'none';
                }
                if (presetAll) presetAll.disabled = true;
                if (presetReadWrite) presetReadWrite.disabled = true;
                if (presetWebhooks) presetWebhooks.disabled = true;

                checkboxes.forEach(cb => {
                    const action = cb.dataset.action;
                    if (action !== 'read') {
                        cb.checked = false;
                        cb.disabled = true;
                        cb.parentElement.style.opacity = '0.4';
                        cb.parentElement.style.cursor = 'not-allowed';
                    } else {
                        cb.disabled = false;
                        cb.parentElement.style.opacity = '1';
                        cb.parentElement.style.cursor = 'pointer';
                    }
                });

                applyScopePreset(prefix, 'readonly');
            } else {
                if (wildcardCheckbox) wildcardCheckbox.disabled = false;
                if (wildcardContainer) {
                    wildcardContainer.style.opacity = '1';
                    wildcardContainer.style.pointerEvents = 'auto';
                }
                if (presetAll) presetAll.disabled = false;
                if (presetReadWrite) presetReadWrite.disabled = false;
                if (presetWebhooks) presetWebhooks.disabled = false;

                checkboxes.forEach(cb => {
                    cb.disabled = false;
                    cb.parentElement.style.opacity = '1';
                    cb.parentElement.style.cursor = 'pointer';
                });
            }

            updateScopeSummaryText(prefix);
        }

        function handleWildcardCheckboxToggle(prefix, isChecked) {
            const checkboxes = document.querySelectorAll('.' + prefix + '-granular-scope');
            checkboxes.forEach(cb => {
                if (!cb.disabled) cb.checked = isChecked;
            });

            setActivePresetButton(prefix, isChecked ? 'all' : 'clear');
            updateScopeSummaryText(prefix);
        }

        function handleGranularScopeChange(prefix) {
            const wildcardCheckbox = document.getElementById(prefix + 'ScopeWildcard');
            const totalGranular = document.querySelectorAll('.' + prefix + '-granular-scope:not(:disabled)').length;
            const checkedGranular = document.querySelectorAll('.' + prefix + '-granular-scope:checked').length;

            if (checkedGranular < totalGranular && wildcardCheckbox) {
                wildcardCheckbox.checked = false;
            }

            setActivePresetButton(prefix, null);
            updateScopeSummaryText(prefix);
        }

        function applyScopePreset(prefix, preset) {
            const wildcardCheckbox = document.getElementById(prefix + 'ScopeWildcard');
            const checkboxes = document.querySelectorAll('.' + prefix + '-granular-scope');

            if (preset === 'all') {
                if (wildcardCheckbox && !wildcardCheckbox.disabled) wildcardCheckbox.checked = true;
                checkboxes.forEach(cb => {
                    if (!cb.disabled) cb.checked = true;
                });
            } else if (preset === 'clear') {
                if (wildcardCheckbox) wildcardCheckbox.checked = false;
                checkboxes.forEach(cb => cb.checked = false);
            } else if (preset === 'webhooks') {
                if (wildcardCheckbox) wildcardCheckbox.checked = false;
                checkboxes.forEach(cb => {
                    if (!cb.disabled) cb.checked = cb.value === 'webhooks:post';
                });
            } else if (preset === 'readonly') {
                if (wildcardCheckbox) wildcardCheckbox.checked = false;
                checkboxes.forEach(cb => {
                    if (!cb.disabled) cb.checked = cb.dataset.action === 'read';
                });
            } else if (preset === 'readwrite') {
                if (wildcardCheckbox) wildcardCheckbox.checked = false;
                checkboxes.forEach(cb => {
                    if (!cb.disabled) cb.checked = true;
                });
            }

            setActivePresetButton(prefix, preset);
            updateScopeSummaryText(prefix);
        }

        function openEditModal(keyData) {
            const modal = document.getElementById('editKeyModal');
            document.getElementById('editForm').action = `/dashboard/api-keys/${keyData.id}`;
            document.getElementById('editName').value = keyData.name;
            document.getElementById('editTierId').value = keyData.tier_id;
            
            document.getElementById('editRateLimit').value = (keyData.rate_limit_per_minute !== null && keyData.rate_limit_per_minute !== undefined) ? keyData.rate_limit_per_minute : '';
            document.getElementById('editIsActive').checked = keyData.is_active;
            document.getElementById('editAllowedIps').value = keyData.allowed_ips || '';
            document.getElementById('editExpiresAt').value = keyData.expires_at || '';

            const badge = document.getElementById('editKeyTypeBadge');
            const notice = document.getElementById('editPublicKeyNotice');
            const wildcardCheckbox = document.getElementById('editScopeWildcard');
            const wildcardContainer = document.getElementById('editWildcardContainer');
            const granularCheckboxes = document.querySelectorAll('.edit-granular-scope');

            const presetAll = document.getElementById('editPreset_all');
            const presetReadWrite = document.getElementById('editPreset_readwrite');
            const presetWebhooks = document.getElementById('editPreset_webhooks');

            const isPublic = keyData.key_type === 'public';

            if (isPublic) {
                badge.innerText = 'PUBLIC KEY';
                badge.style.background = 'rgba(33, 150, 243, 0.12)';
                badge.style.color = '#1565c0';
                notice.style.display = 'block';

                if (wildcardCheckbox) {
                    wildcardCheckbox.checked = false;
                    wildcardCheckbox.disabled = true;
                }
                if (wildcardContainer) {
                    wildcardContainer.style.opacity = '0.4';
                    wildcardContainer.style.pointerEvents = 'none';
                }
                if (presetAll) presetAll.disabled = true;
                if (presetReadWrite) presetReadWrite.disabled = true;
                if (presetWebhooks) presetWebhooks.disabled = true;

                granularCheckboxes.forEach(cb => {
                    const action = cb.dataset.action;
                    if (action !== 'read') {
                        cb.checked = false;
                        cb.disabled = true;
                        cb.parentElement.style.opacity = '0.4';
                        cb.parentElement.style.cursor = 'not-allowed';
                    } else {
                        cb.disabled = false;
                        cb.parentElement.style.opacity = '1';
                        cb.parentElement.style.cursor = 'pointer';
                    }
                });
            } else {
                badge.innerText = 'SECRET KEY';
                badge.style.background = 'rgba(156, 39, 176, 0.12)';
                badge.style.color = '#6a1b9a';
                notice.style.display = 'none';

                if (wildcardCheckbox) wildcardCheckbox.disabled = false;
                if (wildcardContainer) {
                    wildcardContainer.style.opacity = '1';
                    wildcardContainer.style.pointerEvents = 'auto';
                }
                if (presetAll) presetAll.disabled = false;
                if (presetReadWrite) presetReadWrite.disabled = false;
                if (presetWebhooks) presetWebhooks.disabled = false;

                granularCheckboxes.forEach(cb => {
                    cb.disabled = false;
                    cb.parentElement.style.opacity = '1';
                    cb.parentElement.style.cursor = 'pointer';
                });
            }

            const scopes = Array.isArray(keyData.scopes) ? keyData.scopes : [];

            if (!isPublic && scopes.includes('*')) {
                if (wildcardCheckbox) wildcardCheckbox.checked = true;
                granularCheckboxes.forEach(cb => cb.checked = true);
                setActivePresetButton('edit', 'all');
            } else {
                if (wildcardCheckbox) wildcardCheckbox.checked = false;
                granularCheckboxes.forEach(cb => {
                    cb.checked = !cb.disabled && scopes.includes(cb.value);
                });
                setActivePresetButton('edit', null);
            }

            updateScopeSummaryText('edit');
            modal.showModal();
        }
    </script>
    @endpush
</x-layout>
