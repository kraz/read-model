<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Pagination\Cursor;

use JsonException;
use Kraz\ReadModel\Exception\InvalidCursorException;
use Override;

use function array_key_exists;
use function base64_decode;
use function base64_encode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function rtrim;
use function strtr;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Default codec: URL-safe base64 of compact JSON.
 *
 * Opaque to API consumers (its structure is non-obvious) but NOT tamper-proof. Wrap
 * this codec with {@see SignedCursorCodec} when integrity matters.
 */
final readonly class Base64JsonCursorCodec implements CursorCodecInterface
{
    #[Override]
    public function encode(Cursor $cursor): string
    {
        $payload = [
            'd' => $cursor->getDirection()->value,
            'p' => $cursor->getPosition(),
            's' => $cursor->getSortSignature(),
        ];

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new InvalidCursorException('Unable to encode cursor: ' . $e->getMessage(), 0, $e);
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    #[Override]
    public function decode(string $token): Cursor
    {
        if ($token === '') {
            throw new InvalidCursorException('Cursor token cannot be empty.');
        }

        $json = base64_decode(strtr($token, '-_', '+/'), true);
        if ($json === false || $json === '') {
            throw new InvalidCursorException('Cursor token is not valid base64.');
        }

        try {
            /** @phpstan-var mixed $data */
            $data = json_decode($json, true, 6, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidCursorException('Cursor token is not valid JSON: ' . $e->getMessage(), 0, $e);
        }

        if (! is_array($data) || ! array_key_exists('d', $data) || ! array_key_exists('p', $data) || ! array_key_exists('s', $data)) {
            throw new InvalidCursorException('Cursor token payload is malformed.');
        }

        if (! is_string($data['d'])) {
            throw new InvalidCursorException('Cursor token direction is malformed.');
        }

        $direction = Direction::tryFrom($data['d']);
        if ($direction === null) {
            throw new InvalidCursorException('Cursor token direction is unknown.');
        }

        if (! is_array($data['p'])) {
            throw new InvalidCursorException('Cursor token position is malformed.');
        }

        $position = [];
        /** @phpstan-var mixed $entry */
        foreach ($data['p'] as $entry) {
            if (! is_array($entry) || ! array_key_exists('field', $entry) || ! array_key_exists('value', $entry)) {
                throw new InvalidCursorException('Cursor token position entry is malformed.');
            }

            /** @phpstan-var mixed $field */
            $field = $entry['field'];
            if (! is_string($field) || $field === '') {
                throw new InvalidCursorException('Cursor token position entry has invalid field.');
            }

            /** @phpstan-var mixed $value */
            $value = $entry['value'];
            if ($value !== null && ! is_int($value) && ! is_float($value) && ! is_string($value) && ! is_bool($value)) {
                throw new InvalidCursorException('Cursor token position entry has invalid value type.');
            }

            $position[] = ['field' => $field, 'value' => $value];
        }

        if (! is_string($data['s'])) {
            throw new InvalidCursorException('Cursor token sort signature is malformed.');
        }

        return new Cursor($direction, $position, $data['s']);
    }
}
