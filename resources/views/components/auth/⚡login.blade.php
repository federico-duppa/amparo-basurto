<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Entrar')] class extends Component
{
    public string $username = '';

    public string $password = '';

    public bool $remember = false;

    protected function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'username.required' => 'Decime tu usuario.',
            'password.required' => 'Me falta tu contraseña.',
        ];
    }

    public function login()
    {
        $this->validate();

        $this->username = strtolower(trim($this->username));
        $throttleKey = 'login:'.$this->username.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $this->addError('username', 'Probaste demasiadas veces. Esperá un minuto y volvé a intentar.');

            return;
        }

        if (! Auth::attempt(['username' => $this->username, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);
            $this->addError('username', 'No coinciden ese usuario y esa contraseña.');

            return;
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        return $this->redirectIntended(route('todos'));
    }
};
?>

<section class="mx-auto mt-8 max-w-sm space-y-8 lg:mt-16">
    <div class="flex flex-col items-center gap-3 text-center">
        <img src="{{ asset('icon.svg') }}" alt="" aria-hidden="true" class="size-14 rounded-sm">
        <h1 class="font-brand text-2xl font-bold">Amparo Basurto</h1>
        <p class="text-cuero/70">Hola. Decime quién sos.</p>
    </div>

    <form wire:submit="login" method="post" class="space-y-4">
        <div class="space-y-1">
            <label for="username" class="text-sm font-medium">Usuario</label>
            <input
                id="username"
                name="username"
                type="text"
                wire:model="username"
                autocomplete="username"
                autocapitalize="none"
                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
            >
        </div>

        <div class="space-y-1">
            <label for="password" class="text-sm font-medium">Contraseña</label>
            <input
                id="password"
                name="password"
                type="password"
                wire:model="password"
                autocomplete="current-password"
                class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
            >
        </div>

        <label class="flex min-h-11 items-center gap-2.5 text-sm">
            <input type="checkbox" wire:model="remember" class="size-5 rounded-sm border-cuero/50 accent-monte">
            Recordarme en este dispositivo
        </label>

        @error('username')
            <p class="text-sm text-teja" role="alert">{{ $message }}</p>
        @enderror
        @error('password')
            <p class="text-sm text-teja" role="alert">{{ $message }}</p>
        @enderror

        <button
            type="submit"
            wire:loading.attr="disabled"
            class="min-h-11 w-full rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60"
        >
            Entrar
        </button>
    </form>

    <p class="text-center text-sm text-cuero/70">
        ¿Primera vez?
        <a href="{{ route('register') }}" class="font-medium text-monte underline underline-offset-2">Registrate</a>
    </p>
</section>
