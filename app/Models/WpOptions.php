<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $option_id bigint unsigned auto_increment primary key,
 * @property string $option_name varchar(191) default '' not null,
 * @property string $option_value longtext not null,
 * @property string $autoload varchar(20) default 'yes' not null,
 */
class WpOptions extends Model
{
    protected $table = 'lh_options';
    protected $primaryKey = 'option_id';
    public $timestamps = false;

    public static function getOptionValue(string $name): ?string
    {
        /** @var ?self $optionRow */
        $optionRow = self::query()->where('option_name', '=', $name)->firstOrFail();
        return $optionRow?->option_value;
    }
}
