<?php

namespace App\Domain\Access\Actions;

use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;
use InvalidArgumentException;

/**
 * Membuat tautan aktivasi sekali pakai (berlaku 72 jam) untuk kandidat yang belum aktif.
 * Tautan ditampilkan sekali kepada HR untuk dikirim manual; email baru di fase 2.
 * Membuat tautan baru membatalkan tautan sebelumnya.
 */
class CreateActivationLink
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function __invoke(User $candidate): string
    {
        $profile = $candidate->candidateProfile;

        if (! $candidate->isCandidate() || $profile === null) {
            throw new InvalidArgumentException('Tautan aktivasi hanya untuk akun kandidat.');
        }

        if ($profile->activated_at !== null) {
            throw new InvalidArgumentException('Akun kandidat sudah aktif.');
        }

        $token = self::broker()->createToken($candidate);

        $this->logger->log('candidate.activation_link_created', $profile);

        return route('activation.show', ['user' => $candidate, 'token' => $token]);
    }

    public static function broker(): PasswordBroker
    {
        $broker = Password::broker('activations');

        assert($broker instanceof PasswordBroker);

        return $broker;
    }
}
