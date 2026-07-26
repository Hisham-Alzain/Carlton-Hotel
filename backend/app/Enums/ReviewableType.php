<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use App\Models\DiningVenue;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Model;

/**
 * What a guest can review.
 *
 * `reviews.reviewable_type` stores the FQCN, not these values — RoomType and
 * DiningVenue both use LogsActivity, so adding them to Relation::morphMap()
 * would retroactively change subject_type for every logged activity and
 * mediable_type for every uploaded image. This enum does the translation at the
 * API boundary instead, the same way Message::senderLabel() does.
 */
enum ReviewableType: string
{
    use HasValues;

    case ROOM_TYPE    = 'room_type';
    case DINING_VENUE = 'dining_venue';

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::ROOM_TYPE    => RoomType::class,
            self::DINING_VENUE => DiningVenue::class,
        };
    }

    public static function fromModel(Model $model): self
    {
        $class = $model::class;

        return match ($class) {
            RoomType::class    => self::ROOM_TYPE,
            DiningVenue::class => self::DINING_VENUE,
            default            => throw new \InvalidArgumentException("Not a reviewable model: {$class}"),
        };
    }
}
