<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pencatat audit log dengan hash chaining SHA-256 (FR-12, FR-13).
 * Setiap entri memuat hash entri sebelumnya, sehingga perubahan atau penghapusan entri
 * pada data uji dapat dideteksi lewat verifyChain(). Ini bukan jaminan log anti-manipulasi.
 */
class AuditLogger
{
    public static function genesis(): string
    {
        return str_repeat('0', 64);
    }

    public static function record(
        string $event,
        string $result = 'berhasil',
        User|int|null $actor = null,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
    ): AuditLog {
        $actorId = $actor instanceof User ? (int) $actor->getKey() : $actor;
        $before = self::sanitize($before);
        $after = self::sanitize($after);
        $occurredAt = now();

        return DB::transaction(function () use ($event, $result, $actorId, $targetType, $targetId, $before, $after, $reason, $occurredAt): AuditLog {
            // Penguncian baris terakhir agar rantai tetap berurutan saat ada pencatatan bersamaan.
            $last = AuditLog::query()->orderByDesc('id')->lockForUpdate()->first();
            $previousHash = $last?->entry_hash ?? self::genesis();

            $log = AuditLog::create([
                'actor_user_id' => $actorId,
                'event_type' => $event,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'action_result' => $result,
                'before_data' => $before,
                'after_data' => $after,
                'reason' => $reason,
                'occurred_at' => $occurredAt,
                'previous_hash' => $previousHash,
                'entry_hash' => self::hashFor(
                    $previousHash,
                    $occurredAt->format('Y-m-d H:i:s'),
                    $actorId,
                    $event,
                    $targetType,
                    $targetId,
                    $result,
                    $before,
                    $after,
                    $reason,
                ),
            ]);

            self::writeCheckpoint($log);

            return $log;
        });
    }

    public static function hashFor(
        string $previousHash,
        string $occurredAt,
        ?int $actorId,
        string $event,
        ?string $targetType,
        ?int $targetId,
        string $result,
        ?array $before,
        ?array $after,
        ?string $reason,
    ): string {
        $payload = json_encode([
            $previousHash,
            $occurredAt,
            $actorId,
            $event,
            $targetType,
            $targetId,
            $result,
            $before === null ? null : self::sortKeys($before),
            $after === null ? null : self::sortKeys($after),
            $reason,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $payload);
    }

    /**
     * Memeriksa seluruh rantai dan, bila aktif, kecocokan dengan checkpoint.
     *
     * @return array{ok: bool, checked: int, first_invalid_id: ?int, message: string}
     */
    public static function verifyChain(): array
    {
        $expectedPrevious = self::genesis();
        $checked = 0;
        $lastId = null;
        $lastHash = null;

        foreach (AuditLog::query()->orderBy('id')->lazyById(500) as $entry) {
            if ($entry->previous_hash !== $expectedPrevious) {
                return self::fail($checked, $entry->id, 'rantai terputus (hash sebelumnya tidak cocok; kemungkinan ada entri yang dihapus atau diubah).');
            }

            $calculated = self::hashFor(
                (string) $entry->previous_hash,
                $entry->occurred_at->format('Y-m-d H:i:s'),
                $entry->actor_user_id === null ? null : (int) $entry->actor_user_id,
                $entry->event_type,
                $entry->target_type,
                $entry->target_id === null ? null : (int) $entry->target_id,
                $entry->action_result,
                $entry->before_data,
                $entry->after_data,
                $entry->reason,
            );

            if (! hash_equals((string) $entry->entry_hash, $calculated)) {
                return self::fail($checked, $entry->id, 'isi entri tidak sesuai dengan hash-nya (entri telah diubah).');
            }

            $expectedPrevious = $entry->entry_hash;
            $checked++;
            $lastId = $entry->id;
            $lastHash = $entry->entry_hash;
        }

        if (config('audit.checkpoint_enabled')) {
            $checkpoint = self::readCheckpoint();

            if ($checkpoint !== null && (
                ($checkpoint['last_id'] ?? null) !== $lastId
                || ($checkpoint['last_hash'] ?? null) !== $lastHash
                || ($checkpoint['count'] ?? null) !== $checked
            )) {
                return [
                    'ok' => false,
                    'checked' => $checked,
                    'first_invalid_id' => null,
                    'message' => 'Checkpoint tidak cocok dengan isi tabel (kemungkinan entri terakhir dihapus atau tabel dikembalikan).',
                ];
            }
        }

        return [
            'ok' => true,
            'checked' => $checked,
            'first_invalid_id' => null,
            'message' => "Rantai utuh ({$checked} entri diperiksa).",
        ];
    }

    private static function fail(int $checked, int $invalidId, string $message): array
    {
        return [
            'ok' => false,
            'checked' => $checked,
            'first_invalid_id' => $invalidId,
            'message' => "Entri #{$invalidId}: {$message}",
        ];
    }

    private static function writeCheckpoint(AuditLog $log): void
    {
        if (! config('audit.checkpoint_enabled')) {
            return;
        }

        Storage::disk('local')->put(config('audit.checkpoint_file'), json_encode([
            'last_id' => $log->id,
            'last_hash' => $log->entry_hash,
            'count' => AuditLog::query()->count(),
            'updated_at' => now()->toDateTimeString(),
        ]));
    }

    private static function readCheckpoint(): ?array
    {
        $disk = Storage::disk('local');
        $file = config('audit.checkpoint_file');

        return $disk->exists($file) ? json_decode($disk->get($file), true) : null;
    }

    private static function sanitize(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        // Dinormalkan lewat JSON agar bentuknya sama dengan yang dibaca kembali dari database.
        $data = json_decode(json_encode($data), true) ?? [];

        return self::redact($data);
    }

    private static function redact(array $data): array
    {
        $blocked = array_map('strtolower', (array) config('audit.redacted_keys', []));

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $blocked, true)) {
                unset($data[$key]);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }

    private static function sortKeys(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sortKeys($value);
            }
        }

        ksort($data);

        return $data;
    }
}