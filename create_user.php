<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$b = App\Models\Business::create(['name' => 'Test Business']);
App\Models\User::create([
    'business_id' => $b->id,
    'name' => 'Admin',
    'email' => 'admin@test.com',
    'password' => Illuminate\Support\Facades\Hash::make('password'),
    'role' => 'admin'
]);
echo "User created successfully.\n";
