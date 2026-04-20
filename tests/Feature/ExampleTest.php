<?php

test('the application redirects guests to the admin login page', function () {
    $response = $this->get('/');

    $response->assertRedirect('/admin/login');
});
