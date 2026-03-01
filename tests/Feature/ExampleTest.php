<?php

declare(strict_types=1);

it('root redirects unauthenticated users', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
