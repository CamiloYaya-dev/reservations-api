<?php
declare(strict_types=1);

namespace App;

final readonly class ReservationRequest
{
    private function __construct(
        public string $requestId,
        public int $productId,
        public int $quantity,
    ) {}

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, false, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new Problem(400, 'invalid_json', 'El cuerpo debe ser JSON valido.');
        }
        if (!$data instanceof \stdClass) {
            throw new Problem(422, 'validation_error', 'El cuerpo debe ser un objeto JSON.');
        }
        if (!isset($data->request_id, $data->product_id, $data->quantity)) {
            throw new Problem(422, 'validation_error', 'request_id, product_id y quantity son obligatorios.');
        }
        if (array_diff(array_keys(get_object_vars($data)), ['request_id', 'product_id', 'quantity'])) {
            throw new Problem(422, 'validation_error', 'Se recibieron campos no admitidos.');
        }
        if (!is_string($data->request_id)
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,63}\z/D', $data->request_id) !== 1) {
            throw new Problem(422, 'validation_error', 'request_id: 1 a 64 caracteres ASCII; letras, numeros, punto, guion, guion bajo o dos puntos. Debe iniciar con letra o numero.');
        }
        foreach (['product_id', 'quantity'] as $field) {
            if (!is_int($data->$field) || $data->$field < 1 || $data->$field > 2147483647) {
                throw new Problem(422, 'validation_error', "$field debe ser un entero entre 1 y 2147483647.");
            }
        }
        return new self($data->request_id, $data->product_id, $data->quantity);
    }
}
