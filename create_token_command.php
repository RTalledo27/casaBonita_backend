<?php

use Illuminate\Console\Command;
use Modules\Security\Models\User;

class CreateTokenCommand extends Command
{
    protected $signature = 'create:test-token';
    protected $description = 'Create a test token for middleware testing';

    public function handle()
    {
        $user = User::where('must_change_password', true)->first();
        
        if (!$user) {
            $this->error('No user found with must_change_password = true');
            return 1;
        }
        
        $this->info("Found user: {$user->username}");
        $this->info("must_change_password: " . ($user->must_change_password ? 'true' : 'false'));
        
        $token = $user->createToken('test-middleware-token')->plainTextToken;
        $this->info("\nGenerated token: {$token}");
        $this->info("\nTo test the middleware, run:");
        $this->info("curl -H \"Authorization: Bearer {$token}\" -H \"Accept: application/json\" http://127.0.0.1:8000/api/v1/security/users");
        $this->info("\nExpected result: 403 status with must_change_password message");
        
        return 0;
    }
}