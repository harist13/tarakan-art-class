<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Nama lengkap</label>
        <input type="text" name="full_name" class="form-control" value="{{ old('full_name', $user->full_name ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" value="{{ old('username', $user->username ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $user->email ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Role</label>
        <select name="role" class="form-select" required>
            <option value="admin" @selected(old('role', $user->role ?? '') === 'admin')>Admin</option>
            <option value="super_admin" @selected(old('role', $user->role ?? '') === 'super_admin')>Super Admin</option>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Password {{ isset($user) ? '(kosongkan jika tidak diubah)' : '' }}</label>
        <input type="password" name="password" class="form-control" {{ isset($user) ? '' : 'required' }}>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Konfirmasi password</label>
        <input type="password" name="password_confirmation" class="form-control" {{ isset($user) ? '' : 'required' }}>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select" required>
            <option value="active" @selected(old('status', $user->status ?? 'active') === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $user->status ?? '') === 'inactive')>Inactive</option>
        </select>
    </div>
</div>
