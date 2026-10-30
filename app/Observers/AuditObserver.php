<?php

namespace App\Observers;

use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Mencatat pembuatan, perubahan, dan penghapusan data penting ke audit log (FR-12).
 * Password tidak pernah dicatat; hanya penanda bahwa password ditetapkan atau diubah (NFR-05).
 */
class AuditObserver
{
    private const IGNORED = ['created_at', 'updated_at', 'remember_token'];

    public function created(Model $model): void
    {
        $after = [];

        foreach ($model->getAttributes() as $key => $value) {
            if (in_array($key, self::IGNORED, true)) {
                continue;
            }

            if ($key === 'password') {
                $after['kata_sandi'] = '[ditetapkan]';

                continue;
            }

            $after[$key] = $value;
        }

        $this->log($model, 'created', null, $after);
    }

    public function updated(Model $model): void
    {
        $changed = array_diff(array_keys($model->getChanges()), self::IGNORED);

        if ($changed === []) {
            return;
        }

        $before = [];
        $after = [];

        foreach ($changed as $key) {
            if ($key === 'password') {
                $after['kata_sandi'] = '[diubah]';

                continue;
            }

            $before[$key] = $model->getOriginal($key);
            $after[$key] = $model->getAttribute($key);
        }

        $this->log($model, 'updated', $before ?: null, $after);
    }

    public function deleted(Model $model): void
    {
        $before = collect($model->getOriginal())
            ->except([...self::IGNORED, 'password'])
            ->all();

        $this->log($model, 'deleted', $before, null);
    }

    private function log(Model $model, string $action, ?array $before, ?array $after): void
    {
        $type = Str::snake(class_basename($model));

        try {
            AuditLogger::record(
                "{$type}_{$action}",
                'berhasil',
                auth()->id() ? (int) auth()->id() : null,
                $type,
                (int) $model->getKey(),
                $before,
                $after,
            );
        } catch (\Throwable $e) {
            // Kegagalan pencatatan tidak boleh menggagalkan operasi utama, tetapi harus terlihat.
            report($e);
        }
    }
}