<?php

namespace App\Filament\Resources\Auth;

use Visualbuilder\EmailTemplates\Notifications\UserResetPasswordRequestNotification;


class RequestPasswordReset extends \Filament\Pages\Auth\PasswordReset\RequestPasswordReset
{
    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();
            return;
        }

        $data = $this->form->getState();

        $status = Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
            $data,
            function (CanResetPassword $user, string $token): void {
                if (!method_exists($user, 'notify')) {
                    $userClass = $user::class;
                    throw new Exception("Model [{$userClass}] does not have a [notify()] method.");
                }
                $tokenUrl = Filament::getResetPasswordUrl($token, $user);

                /**
                 * Use our custom notification is the only difference.
                 */
                $user->notify(new UserResetPasswordRequestNotification($tokenUrl));
            },
        );

        if ($status !== Password::RESET_LINK_SENT) {
            Notification::make()
                ->title(__($status))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__($status))
            ->success()
            ->send();

        $this->form->fill();
    }

    /**
     * @param $token
     *
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $url = \Illuminate\Support\Facades\URL::secure(route('password.reset', ['token' => $token, 'email' => $this->email]));

        $this->notify(new UserResetPasswordRequestNotification($url));
    }
}
