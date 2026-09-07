<?php

declare(strict_types=1);

namespace Songwunsch;

/**
 * The room this request happens in.
 *
 * Everything in the application is room-scoped -- wish lists, suggestions,
 * the song selection -- and almost every address is built relative to the
 * room the visitor is in. That room is worked out once, by RoomListener,
 * and read from here by the URL generator, the repositories and the views.
 *
 * The main room has id 0 and no machine name; it lives at the base path
 * itself (RoomRepository::DEFAULT_ID).
 */
final class RoomContext
{
    /** @var array<string,mixed>|null */
    private ?array $room = null;

    /** Was the room named in the address, rather than remembered or defaulted? */
    private bool $fromAddress = false;

    /** @param array<string,mixed> $room */
    public function set(array $room, bool $fromAddress = false): void
    {
        $this->room        = $room;
        $this->fromAddress = $fromAddress;
    }

    /** @return array<string,mixed> */
    public function room(): array
    {
        return $this->room ?? RoomRepository::defaultRoom();
    }

    public function id(): int
    {
        return (int) $this->room()['id'];
    }

    /** The machine name, '' for the main room. */
    public function slug(): string
    {
        return (string) ($this->room()['slug'] ?? '');
    }

    public function isMain(): bool
    {
        return $this->id() === RoomRepository::DEFAULT_ID;
    }

    public function fromAddress(): bool
    {
        return $this->fromAddress;
    }
}
