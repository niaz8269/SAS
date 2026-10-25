<x-layouts.student title="Confirm deletion">
    <h1 class="text-2xl font-extrabold">Confirm account deletion</h1>
    <form method="POST" action="{{ route('student.privacy.delete.destroy', $token) }}" class="card mt-5 max-w-lg space-y-3 p-5">
        @csrf @method('DELETE')
        <p class="text-sm">This permanently deletes your account and documents and cannot be undone.</p>
        <div><label for="pw" class="label">Password</label><x-password-input id="pw" name="password" autocomplete="current-password" :invalid="$errors->has('password')" />@error('password')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
        <div><label for="conf" class="label">Type DELETE</label><input id="conf" name="confirmation" required autocomplete="off" class="input @error('confirmation') input-error @enderror">@error('confirmation')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>
        <button class="btn-danger">Delete everything</button>
        <a class="btn-ghost" href="{{ route('student.privacy') }}">Cancel</a>
    </form>
</x-layouts.student>
