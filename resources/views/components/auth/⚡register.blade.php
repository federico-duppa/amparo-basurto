<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Registrarme')] class extends Component
{
    public string $name = '';

    public string $username = '';

    public string $password = '';

    public string $password_confirmation = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::in(config('amparo.allowed_usernames')),
                'unique:users,username',
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Decime tu nombre, así sé cómo llamarte.',
            'username.required' => 'Elegí un usuario.',
            'username.alpha_dash' => 'El usuario puede tener letras, números y guiones, nada más.',
            'username.in' => 'Ese usuario no está en mi lista. Por ahora atiendo a poca gente.',
            'username.unique' => 'Ese usuario ya está registrado. Si sos vos, probá entrar.',
            'password.required' => 'Elegí una contraseña.',
            'password.min' => 'La contraseña tiene que tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }

    public function register()
    {
        $this->name = trim($this->name);
        $this->username = strtolower(trim($this->username));

        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'username' => $this->username,
            'password' => $this->password,
        ]);

        Auth::login($user);
        session()->regenerate();

        return $this->redirect(route('todos'));
    }

    public function registrationOpen(): bool
    {
        return config('amparo.allowed_usernames') !== [];
    }
};
?>

<section class="mx-auto mt-8 max-w-sm space-y-8 lg:mt-16">
    <div class="flex flex-col items-center gap-3 text-center">
        <img src="{{ asset('icon.svg') }}" alt="" aria-hidden="true" class="size-14 rounded-sm">
        <h1 class="font-brand text-2xl font-bold">Amparo Basurto</h1>
        @if ($this->registrationOpen())
            <p class="text-cuero/70">Contame quién sos y elegí una contraseña.</p>
        @endif
    </div>

    @if (! $this->registrationOpen())
        <p class="rounded-sm border border-cuero/20 px-4 py-8 text-center text-cuero/70">
            El registro está cerrado por ahora.
        </p>
    @else
        <form wire:submit="register" method="post" class="space-y-4">
            <div class="space-y-1">
                <label for="name" class="text-sm font-medium">Tu nombre</label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    wire:model="name"
                    autocomplete="name"
                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                >
                @error('name') <p class="text-sm text-teja" role="alert">{{ $message }}</p> @enderror
            </div>

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
                @error('username') <p class="text-sm text-teja" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1">
                <label for="password" class="text-sm font-medium">Contraseña</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    wire:model="password"
                    autocomplete="new-password"
                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                >
                @error('password') <p class="text-sm text-teja" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-1">
                <label for="password_confirmation" class="text-sm font-medium">Repetí la contraseña</label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    wire:model="password_confirmation"
                    autocomplete="new-password"
                    class="min-h-11 w-full rounded-sm border border-cuero/30 bg-crema px-3 text-base focus:border-monte focus:outline-none focus:ring-2 focus:ring-monte/40"
                >
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                class="min-h-11 w-full rounded-sm bg-monte px-4 font-medium text-crema hover:bg-monte/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-monte disabled:opacity-60"
            >
                Crear mi cuenta
            </button>
        </form>
    @endif

    <p class="text-center text-sm text-cuero/70">
        ¿Ya tenés cuenta?
        <a href="{{ route('login') }}" class="font-medium text-monte underline underline-offset-2">Entrá</a>
    </p>
</section>
