<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Event;
use App\Services\EventCatalog;

final readonly class DeleteEvent
{
    public function __construct(private EventCatalog $catalog)
    {
    }

    public function execute(Event $event): void
    {
        $id = $event->id;
        $event->delete();
        $this->catalog->forget($id);
    }
}
