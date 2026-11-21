<?php

namespace App\Domain\Access\Actions;

use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;
use InvalidArgumentException;

/**
 * Membuat tautan aktivasi sekali pakai (berlaku 72 jam) untuk akun kandidat
 * atau staf yang belum aktif. Tautan ditampilkan sekali kepada pembuat akun
 * untuk dikirim manual; email baru di fase 2. Membuat tautan baru membatalkan
 * tautan sebelumnya. Pembuat akun tidak pernah mengetahui password pengguna.
 */
class CreateActivationLink
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function __invoke(User $user): string
    {
        if (! self::awaitsActivation($user)) {
            throw new InvalidArgumentException('Akun ini sudah aktif atau tidak dapat diaktivasi lewat tautan.');
        }

        $token = self::broker()->createToken($user);

        $user->isCandidate()
            ? $this->logger->log('candidate.activation_link_created', $user->candidateProfile)
            : $this->logger->log('staff.activation_link_created', $user);

        return route('activation.show', ['user' => $user, 'token' => $token]);
    }

    /**
     * Kandidat aktif setelah profilnya diaktivasi; staf aktif setelah
     * emailnya terverifikasi lewat aktivasi.
     */
    public static function awaitsActivation(User $user): bool
    {
        return match (true) {
            $user->isCandidate() => $user->candidateProfile !== null && $user->candidateProfile->activated_at === null,
            $user->isStaff() => $user->email_verified_at === null,
            default => false,
        };
    }

    public static function broker(): PasswordBroker
    {
        $broker = Password::broker('activations');

        assert($broker instanceof PasswordBroker);

        return $broker;
    }
}
