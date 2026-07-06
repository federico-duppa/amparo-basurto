<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Compartir un recurso (auto, historia, proyecto, lista) con otra persona por
 * su usuario: mismo flujo en los cuatro módulos que lo ofrecen, cambiando
 * sólo el modelo y el género de los textos que Amparo muestra.
 *
 * @property string $shareUsername
 */
trait SharesWithMembers
{
    /**
     * El recurso del que el usuario autenticado es dueño. Compartir y dejar
     * de compartir son acciones reservadas al dueño (lo ajeno responde 404).
     *
     * @return Model&object{members: BelongsToMany}
     */
    abstract protected function shareableOwned(): Model;

    /**
     * Sustantivo del recurso y su género, para armar los mensajes de Amparo
     * ("Ese vehículo ya es tuyo." / "Esa historia ya es tuya.").
     *
     * @return array{noun: string, genero: 'm'|'f'}
     */
    abstract protected function shareableNoun(): array;

    /**
     * Hook opcional para los componentes que cachean una colección computed
     * derivada del recurso compartido (p. ej. Todo con sus proyectos).
     */
    protected function afterShareChange(): void {}

    public function share(): void
    {
        $entity = $this->shareableOwned();

        $this->shareUsername = strtolower(trim($this->shareUsername));

        ['noun' => $noun, 'genero' => $genero] = $this->shareableNoun();
        $lo = $genero === 'f' ? 'la' : 'lo';
        $ese = $genero === 'f' ? 'Esa' : 'Ese';
        $tuyo = $genero === 'f' ? 'tuya' : 'tuyo';

        $this->validate([
            'shareUsername' => ['required', 'string', 'max:50'],
        ], [
            'shareUsername.required' => "Decime el usuario de la persona con quien {$lo} compartís.",
        ]);

        $user = User::where('username', $this->shareUsername)->first();

        if (! $user) {
            $this->addError('shareUsername', 'No encontré a nadie con ese usuario.');

            return;
        }

        if ($entity->isOwnedBy($user)) {
            $this->addError('shareUsername', "{$ese} {$noun} ya es {$tuyo}.");

            return;
        }

        if ($entity->members()->whereKey($user->id)->exists()) {
            $this->addError('shareUsername', "Ya {$lo} estás compartiendo con esa persona.");

            return;
        }

        $entity->members()->attach($user->id);

        $this->reset('shareUsername');
        $this->afterShareChange();
    }

    public function unshare(int $userId): void
    {
        $this->shareableOwned()->members()->detach($userId);
        $this->afterShareChange();
    }
}
