<?php
require_once __DIR__ . '/../api/security.php';
function check($condition) { if (!$condition) throw new RuntimeException('Security regression'); }
check(!security_secret_matches('', ''));
check(!security_secret_matches('fixture-secret', 'wrong'));
check(security_secret_matches('fixture-secret', 'fixture-secret'));
check(!security_redemption_owner(['uid' => 'owner-a'], 'owner-a', 'chat-a', []));
check(!security_redemption_owner(['uid' => 'owner-a'], 'owner-b', 'chat-b', ['chat-b' => 'owner-b']));
check(!security_redemption_owner(['uid' => 'owner-a'], 'owner-a', 'chat-b', ['chat-a' => 'owner-a']));
check(security_redemption_owner(['uid' => 'owner-a'], 'owner-a', 'chat-a', ['chat-a' => 'owner-a']));
echo "7 security checks passed\n";
