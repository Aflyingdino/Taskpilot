<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/projects/10';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/helpers.php';

function expectTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function expectSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}. Expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n");
        exit(1);
    }
}

expectTrue(validEmail('dev@example.com'), 'validEmail accepts valid email');
expectTrue(!validEmail('nope@'), 'validEmail rejects invalid email');

expectSame('john@example.com', normalizeEmail('  John@Example.com '), 'normalizeEmail lowercases and trims');

expectTrue(validPassword('abc1234567'), 'validPassword accepts strong password');
expectTrue(!validPassword('abcdefgxyz'), 'validPassword rejects no-digit password');

expectTrue(validColor('#a1B2c3'), 'validColor accepts hex color');
expectTrue(!validColor('blue'), 'validColor rejects non-hex string');

expectTrue(validDateString('2026-03-16'), 'validDateString accepts yyyy-mm-dd');
expectTrue(!validDateString('2026-99-99'), 'validDateString rejects invalid date');
expectTrue(validDateTimeString('2026-03-16T13:45:00Z'), 'validDateTimeString accepts ISO datetime');

expectSame('fakeadmiin', normalizeModerationText('F@KE---ADM111N'), 'normalizeModerationText strips and normalizes');

expectSame('/projects/10', path(), 'path strips api prefix');
expectSame(true, isSafeMethod('GET'), 'GET is safe method');
expectSame(false, isSafeMethod('PATCH'), 'PATCH is unsafe method');

$params = matchRoute('/projects/{id}/tasks/{taskId}', '/projects/12/tasks/77');
expectSame(['id' => '12', 'taskId' => '77'], $params, 'matchRoute maps route params');
expectSame(null, matchRoute('/projects/{id}', '/groups/12'), 'matchRoute returns null for non-match');

expectSame('127.0.0.1', clientIp(), 'clientIp reads remote addr');

$_SESSION = [];
$tokenA = csrfToken();
$tokenB = csrfToken();
expectTrue(is_string($tokenA) && strlen($tokenA) === 64, 'csrfToken returns a 64-char token');
expectSame($tokenA, $tokenB, 'csrfToken is stable until rotation');

$rotated = rotateCsrfToken();
expectTrue($rotated !== $tokenA, 'rotateCsrfToken rotates token');

echo "PHP helper tests passed\n";
