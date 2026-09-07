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

    public function getCalendarLink(): void
    {
        $this->user()->calendarToken();

        session()->flash('status', 'Your calendar link is ready. Paste it into your calendar app as a subscription.');
    }

    public function resetCalendarLink(): void
    {
        $this->user()->resetCalendarToken();

        session()->flash('status', 'New calendar link. The old one stopped working.');
    }

    public function render(): View
    {
        // A column query rather than the attribute: the authenticated instance may be
        // one the guard built from the session before this column existed on it.
        $token = User::query()->whereKey($this->user()->id)->value('calendar_token');

        return view('livewire.profile.edit', [
            'calendarUrl' => $token === null ? null : route('calendar.feed', ['token' => $token]),
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
