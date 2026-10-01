<?php

namespace App\Chat\Application;

use App\Shared\Application\Security\Caller;

/**
 * The caller of a chat turn, kept in the job's payload (internal, never returned) so the worker runs every tool as
 * that user, with the same groups, account and impersonation as the request that started it.
 */
final class CallerPayload
{
    /** @return array<string, mixed> */
    public static function of(Caller $caller): array
    {
        return [
            'user_id' => $caller->userId,
            'email' => $caller->email,
            'name' => $caller->name,
            'customer_id' => $caller->customerId,
            'groups' => $caller->groups,
            'root' => $caller->root,
            'real_email' => $caller->realEmail,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function caller(array $data): Caller
    {
        return new Caller(
            (string) ($data['user_id'] ?? ''),
            (string) ($data['email'] ?? ''),
            (string) ($data['name'] ?? ''),
            (string) ($data['customer_id'] ?? ''),
            array_values(array_filter((array) ($data['groups'] ?? []), 'is_string')),
            true === ($data['root'] ?? false),
            \is_string($data['real_email'] ?? null) ? $data['real_email'] : null,
        );
    }
}
