@props([
    'id',
    'name' => 'password',
    'autocomplete' => 'current-password',
    'strength' => false,
    'required' => true,
    'invalid' => false,
    'inputClass' => 'input',
    'describedby' => null,
])
{{--
    A password box with a show/hide eye button and, for new passwords (strength="true"), a weak / medium / strong bar.
    The bar is only advice shown while typing; the server still enforces the real rule (at least 8 characters, with letters and numbers).
    Without JavaScript it is an ordinary password box.
--}}
<div class="relative" x-data="{
        show: false,
        value: '',
        get score() {
            const v = this.value;
            if (!v) return 0;
            let s = 0;
            if (v.length >= 8) s++;
            if (v.length >= 12) s++;
            if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
            if (/\d/.test(v)) s++;
            if (/[^A-Za-z0-9]/.test(v)) s++;
            if (/(.)\1{2,}/.test(v) || /(password|passw0rd|qwerty|asdf|12345|abcde|letmein|admin|welcome)/i.test(v)) s = Math.min(s, 1);
            return v.length < 8 ? Math.min(s, 1) : s;
        },
        get level() { return this.score >= 4 ? 3 : (this.score === 3 ? 2 : 1); },
        get label() { return ['', 'Weak', 'Medium', 'Strong'][this.level]; },
        get tip() {
            if (this.value.length < 8) return 'Use at least 8 characters.';
            if (this.level === 1) return 'Add more characters, capital letters, numbers or a symbol, and avoid common words.';
            if (this.level === 2) return 'Good. A longer password, or a symbol, makes it strong.';
            return 'Strong password.';
        }
    }">
    <input id="{{ $id }}" name="{{ $name }}" type="password" :type="show ? 'text' : 'password'" autocomplete="{{ $autocomplete }}"
        @if ($required) required @endif
        @if ($describedby) aria-describedby="{{ $describedby }}" @endif
        @if ($strength) x-model="value" @endif
        {{ $attributes->merge(['class' => $inputClass.' pr-11'.($invalid ? ' input-error' : '')]) }}>
    <button type="button" class="absolute right-0 top-0 flex h-full w-11 items-center justify-center rounded-r-md text-slate-500 hover:text-navy-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-navy-700 print:hidden"
        x-cloak @click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'" :aria-pressed="show.toString()" aria-label="Show password" aria-pressed="false" style="display:none" x-show="true">
        <svg x-show="!show" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
        <svg x-show="show" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 5.1A10.6 10.6 0 0 1 12 5c6.4 0 10 7 10 7a17.7 17.7 0 0 1-3.2 4.2M6.5 6.6C3.7 8.4 2 12 2 12s3.6 7 10 7a10.5 10.5 0 0 0 4.4-1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
    </button>
    @if ($strength)
        <div x-show="value.length > 0" x-cloak class="mt-2" style="display:none">
            <div class="flex gap-1" aria-hidden="true">
                <span class="h-1.5 flex-1 rounded-full transition-colors" :class="level >= 1 ? (level === 1 ? 'bg-red-500' : (level === 2 ? 'bg-amber-500' : 'bg-green-600')) : 'bg-slate-200'"></span>
                <span class="h-1.5 flex-1 rounded-full transition-colors" :class="level >= 2 ? (level === 2 ? 'bg-amber-500' : 'bg-green-600') : 'bg-slate-200'"></span>
                <span class="h-1.5 flex-1 rounded-full transition-colors" :class="level >= 3 ? 'bg-green-600' : 'bg-slate-200'"></span>
            </div>
            <p class="mt-1 text-xs text-muted" role="status" aria-live="polite">Password strength: <strong :class="level === 1 ? 'text-red-600' : (level === 2 ? 'text-amber-700' : 'text-green-700')" x-text="label"></strong> <span x-text="'. ' + tip"></span></p>
        </div>
    @endif
</div>
