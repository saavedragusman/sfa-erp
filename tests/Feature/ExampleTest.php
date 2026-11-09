<?php

declare(strict_types=1);

it('returns a successful response from the home page', function () {
    $this->get('/')->assertStatus(200);
});
