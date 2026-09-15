<?php

declare(strict_types=1);

function middleware_auth(): void
{
    if (!auth_check()) {
        redirect('/login');
    }
}

function middleware_guest(): void
{
    if (auth_check()) {
        redirect('/dashboard');
    }
}
