<?php
declare(strict_types=1);

namespace App;

final readonly class ReservationResult
{
    public function __construct(
        public int $reservationId,
        public int $remainingStock,
        public bool $replayed,
    ) {}

    /** @return array{reservation_id: int, status: string, remaining_stock: int} */
    public function toArray(): array
    {
        return [
            'reservation_id' => $this->reservationId,
            'status' => 'confirmed',
            'remaining_stock' => $this->remainingStock,
        ];
    }
}
