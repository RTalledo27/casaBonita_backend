<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Modules\Security\Models\User;
use App\Mail\NewUserCredentialsMail;

class TestWelcomeEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test-welcome {email?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enviar email de prueba de bienvenida a un usuario';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $emailAddress = $this->argument('email') ?? $this->ask('Ingresa el correo del usuario de prueba');
        
        // Buscar usuario por email
        $user = User::where('email', $emailAddress)->first();
        
        if (!$user) {
            $this->error("❌ No se encontró ningún usuario con el email: {$emailAddress}");
            
            // Ofrecer crear usuario de prueba
            if ($this->confirm('¿Deseas usar un usuario de prueba ficticio?', true)) {
                $user = new User([
                    'email' => $emailAddress,
                    'first_name' => 'Usuario',
                    'last_name' => 'De Prueba',
                    'dni' => '12345678',
                    'position' => 'Asesor Inmobiliario',
                    'hire_date' => now()->format('Y-m-d')
                ]);
                
                $this->info("✅ Usando usuario ficticio para prueba");
            } else {
                return 1;
            }
        }
        
        $temporaryPassword = '123456';
        $loginUrl = config('app.frontend_url') ?? env('FRONTEND_URL', 'http://localhost:4200');
        
        $this->info("📧 Enviando email de bienvenida...");
        $this->info("   Destinatario: {$user->email}");
        $this->info("   Nombre: {$user->first_name} {$user->last_name}");
        $this->info("   URL Login: {$loginUrl}");
        
        try {
            Mail::to($user->email)->send(
                new NewUserCredentialsMail($user, $temporaryPassword, $loginUrl)
            );
            
            $this->info("");
            $this->info("✅ ¡Email enviado exitosamente!");
            $this->info("");
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['Destinatario', $user->email],
                    ['Contraseña Temporal', $temporaryPassword],
                    ['URL de Acceso', $loginUrl],
                ]
            );
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("");
            $this->error("❌ Error al enviar el email:");
            $this->error("   {$e->getMessage()}");
            $this->error("");
            $this->warn("💡 Verifica la configuración de correo en tu archivo .env:");
            $this->warn("   - MAIL_MAILER");
            $this->warn("   - MAIL_HOST");
            $this->warn("   - MAIL_PORT");
            $this->warn("   - MAIL_USERNAME");
            $this->warn("   - MAIL_PASSWORD");
            $this->warn("   - MAIL_FROM_ADDRESS");
            
            return 1;
        }
    }
}
