<?php
declare(strict_types=1);
// Created by i729. Empty configuration never authenticates a request.
function security_secret_matches(string $expected, string $provided): bool {
    return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
}
function security_redemption_owner(array $record, string $uid, string $chatId, array $verifiedBindings): bool {
    $boundUid = (string)($verifiedBindings[$chatId] ?? '');
    $ownerUid = (string)($record['uid'] ?? '');
    return $uid !== '' && $boundUid !== '' && $ownerUid !== ''
        && hash_equals($boundUid, $uid) && hash_equals($ownerUid, $uid);
}
