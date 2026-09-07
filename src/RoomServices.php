<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * Builds the room-scoped services for a given room.
 *
 * Wish lists, suggestions and the wish protection all belong to one room and
 * are constructed with its id. The container hands out the ones for the room
 * of the request (RoomContext); this is for the handful of places that must
 * reach into another room: closing a room from the room list, archiving a
 * room, and adopting a suggestion, whose song joins the wish list of the
 * room it was suggested in.
 */
final class RoomServices
{
    public function __construct(
        private readonly Database $db,
        private readonly Settings $settings,
        private readonly Limits $limits,
        private readonly bool $trustProxy,
    ) {
    }

    public function wishes(int $roomId): WishRepository
    {
        return new WishRepository($this->db, $roomId);
    }

    public function suggestions(int $roomId): SuggestionRepository
    {
        return new SuggestionRepository($this->db, $roomId);
    }

    public function guard(int $roomId): WishGuard
    {
        return new WishGuard($this->db, $this->settings, $this->limits, $this->trustProxy, $roomId);
    }
}
