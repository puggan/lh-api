<?php

namespace App\Models\Concerns;

use App\Models\Logs;
use App\Models\WpUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes a row to `logs` whenever a model is created, updated, deleted or restored.
 *
 * `changes` is {"column": [old, new], ...} with the raw database values.
 * `wp_users_id` is the logged in WordPress user, NULL for the booker or the system.
 *
 * Only Eloquent model events are logged: a query builder update or delete
 * (Model::query()->where(...)->update(...)) bypasses this, so change rows through the model.
 * CleanupOldBookings deletes on purpose without per-row logs, and writes one summary row instead.
 *
 * @mixin Model
 */
trait LogsChanges
{
    /** Columns that are never logged: maintained by the database. */
    private static array $notLogged = ['created_at', 'updated_at', 'booked_periods_id'];

    public static function bootLogsChanges(): void
    {
        static::created(static function (Model $model): void {
            $changes = [];
            foreach (self::loggable($model->getAttributes()) as $column => $value) {
                $changes[$column] = [null, $value];
            }
            self::writeLog($model, 'created', $changes);
        });

        static::updated(static function (Model $model): void {
            $changes = [];
            foreach (self::loggable($model->getChanges()) as $column => $value) {
                $changes[$column] = [$model->getRawOriginal($column), $value];
            }
            if (!$changes) {
                return;
            }
            self::writeLog($model, self::updateAction($changes), $changes);
        });

        static::deleted(static function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                // Soft delete: only deleted_at changed.
                $column = $model->getDeletedAtColumn();
                self::writeLog($model, 'deleted', [$column => [null, $model->getAttributes()[$column] ?? null]]);
                return;
            }
            $changes = [];
            foreach (self::loggable($model->getRawOriginal()) as $column => $value) {
                $changes[$column] = [$value, null];
            }
            self::writeLog($model, 'deleted', $changes);
        });
    }

    /**
     * 'restored' when a soft delete is undone, the new status when a booking's status changes
     * (e.g. 'booked', 'rejected'), otherwise 'updated'.
     */
    private static function updateAction(array $changes): string
    {
        if (array_key_exists('deleted_at', $changes) && $changes['deleted_at'][1] === null) {
            return 'restored';
        }
        if (array_key_exists('status', $changes)) {
            return (string)$changes['status'][1];
        }
        return 'updated';
    }

    private static function loggable(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(self::$notLogged));
    }

    private static function writeLog(Model $model, string $action, array $changes): void
    {
        $log = new Logs();
        $log->table_name = $model->getTable();
        $log->row_id = $model->getKey();
        $log->action = $action;
        $log->wp_users_id = WpUser::loggedIn()?->ID;
        // Not $log->changes = ...: inside a model class that hits Model's own protected $changes property.
        $log->setAttribute('changes', $changes ?: null);
        $log->save();
    }
}
