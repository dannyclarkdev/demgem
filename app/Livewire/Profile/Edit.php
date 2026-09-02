<?php

namespace App\Livewire\Profile;

use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profile')]
class Edit extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->name = $this->user()->name;
        $this->email = $this->user()->email;
    }

    public function updateProfile(UpdateUserProfileInformation $action): void
    {
        $action->update($this->user(), [
            'name' => $this->name,
            'email' => $this->email,
        ]);

        session()->flash('status', 'Profile updated.');

        $this->redirectRoute('profile.edit');
    }

    public function updatePassword(UpdateUserPassword $action): void
    {
        $action->update($this->user(), [
            'current_password' => $this->current_password,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        session()->flash('status', 'Password updated.');

        $this->redirectRoute('profile.edit');
    }

    public function render(): View
    {
        return view('livewire.profile.edit');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
