<?php

namespace App\Livewire\Profile;

use App\Actions\Auth\UnlinkSocialAccount;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Controllers\Auth\DiscordAuthController;
use App\Models\SocialAccount;
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

    public string $tokenName = '';

    public bool $tokenCanWrite = false;

    /**
     * The secret of the key just created, held for this one response and no other.
     * It is not stored anywhere in the clear; Sanctum keeps a hash.
     */
    public ?string $newToken = null;

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

    /**
     * Unlinking is allowed always; the card says the reset link is the way back in
     * for somebody who never set a password.
     */
    public function unlinkDiscord(UnlinkSocialAccount $unlink): void
    {
        $unlink->handle($this->user(), SocialAccount::DISCORD);

        session()->flash('status', 'Discord is no longer linked to your account.');

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

    /**
     * A key reads as its owner reads. "Can write" adds the one other ability, and a
     * key without it gets 403 from every route that changes something.
     */
    public function createToken(): void
    {
        $validated = $this->validate([
            'tokenName' => ['required', 'string', 'max:60'],
            'tokenCanWrite' => ['boolean'],
        ]);

        $abilities = $this->tokenCanWrite ? ['read', 'write'] : ['read'];

        $this->newToken = $this->user()->createToken(trim($validated['tokenName']), $abilities)->plainTextToken;

        $this->reset('tokenName', 'tokenCanWrite');
    }

    public function revokeToken(int $tokenId): void
    {
        $this->user()->tokens()->whereKey($tokenId)->delete();

        session()->flash('status', 'Key revoked. Anything that was using it stops working now.');

        $this->redirectRoute('profile.edit');
    }

    public function render(): View
    {
        // A column query rather than the attribute: the authenticated instance may be
        // one the guard built from the session before this column existed on it.
        $token = User::query()->whereKey($this->user()->id)->value('calendar_token');

        return view('livewire.profile.edit', [
            'discordConfigured' => DiscordAuthController::isConfigured(),
            'discord' => $this->user()->socialAccount(SocialAccount::DISCORD),
            'calendarUrl' => $token === null ? null : route('calendar.feed', ['token' => $token]),
            'tokens' => $this->user()->tokens()->orderByDesc('created_at')->get(),
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
