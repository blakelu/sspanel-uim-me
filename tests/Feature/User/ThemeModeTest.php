<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\DB;
use Ramsey\Uuid\Uuid;

it('saves the opposite theme mode and refreshes the page from the navigation', function (int $initialMode, int $expectedMode) {
    $connection = DB::connection();
    $connection->beginTransaction();
    $previousUser = $GLOBALS['user'] ?? null;

    try {
        $user = new User();
        $user->email = 'theme-mode-' . Uuid::uuid4()->toString() . '@example.invalid';
        $user->pass = 'unused';
        $user->passwd = bin2hex(random_bytes(16));
        $user->uuid = Uuid::uuid4()->toString();
        $user->api_token = bin2hex(random_bytes(32));
        $user->port = 1025;
        $user->is_dark_mode = $initialMode;
        $user->save();
        $user->isLogin = true;
        $GLOBALS['user'] = $user;

        $response = $this->post('/user/switch_theme_mode', [], ['HX-Request' => 'true']);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getHeaderLine('HX-Refresh'))->toBe('true')
            ->and($user->refresh()->is_dark_mode)->toBe($expectedMode);
    } finally {
        $GLOBALS['user'] = $previousUser;
        $connection->rollBack();
    }
})->with([
    'light to dark' => [0, 1],
    'dark to light' => [1, 0],
    'automatic to light' => [2, 0],
]);
