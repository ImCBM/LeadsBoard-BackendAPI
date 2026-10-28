<x-layout title="API Keys — LeadsBoard">
    <div class="page-header">
        <h1>API Keys</h1>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('createKeyModal').showModal()">
            <span>➕</span> Generate New Key
        </button>
    </div>

    @if(session('new_api_key'))
        <div class="alert alert-success" style="background: #e8f5e9; border: 2px solid var(--primary); padding: 20px;">
            <h3 style="color: var(--primary); margin-bottom: 10px;">New API Key Generated!</h3>
            <p style="margin-bottom: 15px;">Please copy your new API key now. For your security, it will not be shown again.</p>
            <div style="background: #fff; padding: 12px; border-radius: 6px; border: 1px solid var(--outline); font-family: monospace; font-size: 16px; user-select: all; text-align: center; letter-spacing: 1px;">
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
                    <th>Status</th>
                    <th>Last Used</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($apiKeys as $key)
                    <tr>
                        <td><strong>{{ $key->name }}</strong></td>
                        <td>
                            <span class="chip" style="background: {{ $key->isPublic() ? 'rgba(33, 150, 243, 0.15)' : 'rgba(156, 39, 176, 0.15)' }}; color: {{ $key->isPublic() ? '#1976d2' : '#7b1fa2' }}; font-weight: 600;">
                                {{ ucfirst($key->key_type?->value ?? 'secret') }}
                            </span>
                        </td>
                        <td><code style="background: var(--surface); padding: 2px 6px; border-radius: 4px;">{{ $key->plain_text_prefix }}...</code></td>
                        <td>
                            <span class="chip" style="text-transform: capitalize; background: var(--surface); color: var(--on-surface);">{{ $key->tier ?? 'free' }}</span>
                        </td>
                        <td>
                            @if($key->rate_limit_per_minute === 0)
                                <span class="chip chip-new">Unlimited</span>
                            @elseif($key->rate_limit_per_minute === null)
                                {{ config('services.api.default_rate_limit', 120) }} / min (Default)
                            @else
                                {{ $key->rate_limit_per_minute }} / min
                            @endif
                        </td>
                        <td>
                            @if($key->is_active)
                                <span class="chip chip-qualified">Active</span>
                            @else
                                <span class="chip chip-rejected">Inactive</span>
                            @endif
                        </td>
                        <td>{{ $key->last_used_at ? $key->last_used_at->diffForHumans() : 'Never' }}</td>
                        <td>{{ $key->created_at->format('M j, Y') }}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline" 
                                onclick="openEditModal({{ $key->id }}, '{{ addslashes($key->name) }}', {{ $key->rate_limit_per_minute ?? 0 }}, {{ $key->is_active ? 'true' : 'false' }})">
                                Edit
                            </button>
                            <form action="{{ route('dashboard.api-keys.destroy', $key) }}" method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to revoke and delete this key? This cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Revoke</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 30px; color: var(--on-surface-variant);">No API Keys found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Create Modal --}}
    <dialog id="createKeyModal" style="padding: 24px; border: 1px solid var(--outline); border-radius: 12px; margin: auto; max-width: 440px; width: 100%; box-shadow: 0 4px 12px var(--shadow-strong); background: var(--surface-low); color: var(--on-background);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; font-size: 20px;">Create API Key</h2>
            <button onclick="document.getElementById('createKeyModal').close()" style="background:none; border:none; font-size:20px; cursor:pointer; color: var(--on-surface-variant);">&times;</button>
        </div>
        <form action="{{ route('dashboard.api-keys.store') }}" method="POST">
            @csrf
            <div style="margin-bottom: 16px;">
                <label style="display:block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: var(--on-surface-variant);">Name / Label</label>
                <input type="text" name="name" required placeholder="e.g. n8n Production Webhook" style="width: 100%; padding: 10px; border: 1px solid var(--outline); border-radius: 6px; background: var(--background); color: var(--on-background);">
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display:block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: var(--on-surface-variant);">Key Type</label>
                <select name="key_type" style="width: 100%; padding: 10px; border: 1px solid var(--outline); border-radius: 6px; background: var(--background); color: var(--on-background);">
                    <option value="secret" selected>Secret Key (lb_sec_... — Backend Integrations)</option>
                    <option value="public">Public Key (lb_pub_... — Frontend & Read-Only Safe)</option>
                </select>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display:block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: var(--on-surface-variant);">Tier</label>
                <select name="tier" style="width: 100%; padding: 10px; border: 1px solid var(--outline); border-radius: 6px; background: var(--background); color: var(--on-background);">
                    <option value="free" selected>Free Developer (60 req/min)</option>
                    <option value="guest">Guest (30 req/min)</option>
                    <option value="pro">Pro / Growth (300 req/min)</option>
                    <option value="enterprise">Enterprise (Unlimited)</option>
                </select>
            </div>
            <div style="margin-bottom: 24px;">
                <label style="display:block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: var(--on-surface-variant);">Custom Rate Limit (req/min)</label>
                <input type="number" name="rate_limit_per_minute" value="0" min="0" style="width: 100%; padding: 10px; border: 1px solid var(--outline); border-radius: 6px; background: var(--background); color: var(--on-background);">
                <small style="color: var(--on-surface-variant); display:block; margin-top: 4px;">Set to 0 to use tier default rate limit.</small>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('createKeyModal').close()">Cancel</button>
                <button type="submit" class="btn btn-primary">Generate Key</button>
            </div>
        </form>
    </dialog>

    {{-- Edit Modal --}}
    <dialog id="editKeyModal" style="padding: 24px; border: 1px solid var(--outline); border-radius: 12px; margin: auto; max-width: 400px; width: 100%; box-shadow: 0 4px 12px var(--shadow-strong); background: var(--surface-low); color: var(--on-background);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; font-size: 20px;">Edit API Key</h2>
            <button onclick="document.getElementById('editKeyModal').close()" style="background:none; border:none; font-size:20px; cursor:pointer; color: var(--on-surface-variant);">&times;</button>
        </div>
        <form id="editForm" method="POST">
            @csrf
            @method('PUT')
            <div style="margin-bottom: 16px;">
                <label style="display:block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: var(--on-surface-variant);">Name / Label</label>
                <input type="text" id="editName" name="name" required style="width: 100%; padding: 10px; border: 1px solid var(--outline); border-radius: 6px; background: var(--background); color: var(--on-background);">
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display:block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: var(--on-surface-variant);">Rate Limit (req/min)</label>
                <input type="number" id="editRateLimit" name="rate_limit_per_minute" min="0" style="width: 100%; padding: 10px; border: 1px solid var(--outline); border-radius: 6px; background: var(--background); color: var(--on-background);">
                <small style="color: var(--on-surface-variant); display:block; margin-top: 4px;">Set to 0 for unlimited requests.</small>
            </div>
            <div style="margin-bottom: 24px;">
                <label style="display:flex; align-items: center; gap: 8px; font-weight: 600; font-size: 13px; color: var(--on-surface-variant);">
                    <input type="checkbox" id="editIsActive" name="is_active" value="1">
                    Key is Active
                </label>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('editKeyModal').close()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </dialog>

    @push('scripts')
    <script>
        function openEditModal(id, name, rateLimit, isActive) {
            const modal = document.getElementById('editKeyModal');
            document.getElementById('editForm').action = `/dashboard/api-keys/${id}`;
            document.getElementById('editName').value = name;
            document.getElementById('editRateLimit').value = rateLimit;
            document.getElementById('editIsActive').checked = isActive;
            modal.showModal();
        }
    </script>
    @endpush
</x-layout>
