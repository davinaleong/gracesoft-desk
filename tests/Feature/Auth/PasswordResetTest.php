<?php

test('reset password link screen is disabled', function () {
    $response = $this->get('/forgot-password');

    $response->assertNotFound();
});

test('reset password link cannot be requested', function () {
    $response = $this->post('/forgot-password', ['email' => 'admin@gracesoft.dev']);

    $response->assertNotFound();
});

test('reset password screen is disabled', function () {
    $response = $this->get('/reset-password/fake-token');

    $response->assertNotFound();
});

test('password reset submit endpoint is disabled', function () {
    $response = $this->post('/reset-password', [
        'token' => 'fake-token',
        'email' => 'admin@gracesoft.dev',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertNotFound();
});
