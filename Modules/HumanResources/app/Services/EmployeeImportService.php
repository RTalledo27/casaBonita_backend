<?php

namespace Modules\HumanResources\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\HumanResources\Models\Employee;
use Modules\HumanResources\Models\Team;
use Modules\HumanResources\Models\Office;
use Modules\HumanResources\Models\Area;
use Modules\HumanResources\Models\Position;
use Carbon\Carbon;
use Exception;
use Modules\Security\Models\User;
use Modules\Security\Models\Role;
use App\Mail\NewUserCredentialsMail;
use Illuminate\Support\Str;

class EmployeeImportService
{
    /**
     * Importar empleados desde datos de Excel
     */
    public function importFromExcel(array $excelData): array
    {
        $results = [
            'success' => 0,
            'updated' => 0,
            'errors' => [],
            'created_users' => [],
            'updated_users' => [],
            'created_employees' => [],
            'emails_sent' => 0,
            'emails_failed' => []
        ];

        DB::beginTransaction();

        try {
            foreach ($excelData as $index => $row) {
                $rowNumber = $index + 2; // +2 porque empezamos en fila 2 (después del header)
                
                try {
                    // Validar datos requeridos
                    $validationResult = $this->validateRowData($row, $rowNumber);
                    if (!$validationResult['valid']) {
                        $results['errors'][] = $validationResult['error'];
                        continue;
                    }

                    // Procesar datos del empleado
                    $processedData = $this->processRowData($row);
                    
                    // Buscar usuario existente por DNI o Correo
                    $existingUser = User::where('dni', $processedData['user_data']['dni'])
                                        ->orWhere('email', $processedData['user_data']['email'])
                                        ->first();

                    if ($existingUser) {
                        // ACTUALIZAR USUARIO EXISTENTE
                       $this->updateUser($existingUser, $processedData['user_data']);
                       $results['updated_users'][] = $existingUser->user_id;

                       // Buscar o crear empleado
                       $existingEmployee = Employee::where('user_id', $existingUser->user_id)->first();
                       
                       if ($existingEmployee) {
                           $this->updateEmployee($existingEmployee, $processedData['employee_data']);
                       } else {
                           $employee = $this->createEmployee($processedData['employee_data'], $existingUser->user_id);
                           $results['created_employees'][] = $employee->employee_id;
                       }
                       
                       // Asignar roles (Accesos)
                       if (!empty($processedData['roles'])) {
                           $this->assignRoles($existingUser, $processedData['roles']);
                       }

                       $results['updated']++;

                    } else {
                        // CREAR NUEVO USUARIO
                        // Generar contraseña aleatoria segura
                        $rawPassword = Str::random(10);
                        $processedData['user_data']['password_hash'] = Hash::make($rawPassword);
                        
                        // Crear usuario
                        $user = $this->createUser($processedData['user_data']);
                        $results['created_users'][] = $user->user_id;
                        
                        // Crear empleado
                        $employee = $this->createEmployee($processedData['employee_data'], $user->user_id);
                        $results['created_employees'][] = $employee->employee_id;
                        
                        // Asignar roles (Accesos)
                        if (!empty($processedData['roles'])) {
                            $this->assignRoles($user, $processedData['roles']);
                        }

                        // Enviar correo con credenciales
                        try {
                            $this->sendWelcomeEmail($user, $rawPassword);
                            $results['emails_sent']++;
                            Log::info("Email enviado exitosamente a: {$user->email}");
                        } catch (Exception $emailError) {
                            $results['emails_failed'][] = "Fila {$rowNumber}: Error al enviar email a {$user->email} - {$emailError->getMessage()}";
                            Log::error("Error enviando email de bienvenida", [
                                'user_id' => $user->user_id,
                                'email' => $user->email,
                                'error' => $emailError->getMessage()
                            ]);
                        }
                        
                        $results['success']++;
                    }
                    
                } catch (Exception $e) {
                    $results['errors'][] = "Fila {$rowNumber}: {$e->getMessage()}";
                    Log::error("Error importando fila {$rowNumber}", [
                        'error' => $e->getMessage(),
                        'data' => $row
                    ]);
                }
            }

            if (count($results['errors']) > 0 && $results['success'] === 0 && $results['updated'] === 0) {
                DB::rollBack();
                return $results;
            }

            DB::commit();
            return $results;
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Validar datos de una fila
     */
    private function validateRowData(array $row, int $rowNumber): array
    {
        $errors = [];

        // Validar campos requeridos
        $requiredFields = [
            'COLABORADOR' => 'El nombre del colaborador es requerido',
            'DNI' => 'El DNI es requerido',
            'CORREO' => 'El correo es requerido',
            'AREA' => 'El Área (Departamento) es requerida',
            'EQUIPO' => 'El Equipo es requerido',
            'OFICINA' => 'La Oficina es requerida',
            'ACCESOS' => 'Los Accesos (Roles) son requeridos'
        ];

        foreach ($requiredFields as $field => $message) {
            if (empty($row[$field])) {
                $errors[] = $message;
            }
        }
        
        if (!empty($row['DNI']) && (strlen($row['DNI']) !== 8 || !is_numeric($row['DNI']))) {
            $errors[] = "El DNI debe tener 8 dígitos";
        }
        
        if (!empty($row['CORREO']) && !filter_var($row['CORREO'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "El formato del correo es inválido";
        }

        // Ya NO validamos duplicados aquí porque ahora actualizamos si existe.

        if (!empty($errors)) {
            return [
                'valid' => false,
                'error' => "Fila {$rowNumber}: " . implode(', ', $errors)
            ];
        }

        return ['valid' => true];
    }

    /**
     * Procesar datos de una fila
     */
    private function processRowData(array $row): array
    {
        // Separar nombre completo
        $nameParts = $this->splitFullName($row['COLABORADOR']);
        
        // Generar username único
        $username = $this->generateUsername($nameParts['first_name'], $nameParts['last_name']);
        
        // Generar código de empleado único
        $employeeCode = $this->generateEmployeeCode();
        
        // Procesar fechas
        $birthDate = $this->parseDate($row['FECHA NAC'] ?? null);
        $hireDate = $this->parseDate($row['FECHA DE INICIO'] ?? null);
        
        // Procesar salario
        $baseSalary = $this->parseDecimal($row['SUELDO BASICO'] ?? 0);
        
        // Log para debugging del salario
        Log::info("Procesando salario para {$row['COLABORADOR']}", [
            'salario_base_raw' => $row['SUELDO BASICO'] ?? 'NO_EXISTE',
            'salario_base_parsed' => $baseSalary
        ]);
        
        // Obtener/Crear Office ID (case-insensitive firstOrCreate)
        $officeId = null;
        if (!empty($row['OFICINA'])) {
            $office = Office::findOrCreateByName($row['OFICINA']);
            $officeId = $office->office_id;
        }

        // Obtener/Crear Area ID (case-insensitive firstOrCreate)
        $areaId = null;
        if (!empty($row['AREA'])) {
            $area = Area::findOrCreateByName($row['AREA']);
            $areaId = $area->area_id;
        }

        // Obtener/Crear Team ID (case-insensitive firstOrCreate)
        $teamId = null;
        if (!empty($row['EQUIPO'])) {
            $team = Team::findOrCreateByName($row['EQUIPO']);
            $teamId = $team->team_id;
        }

        // Obtener/Crear Position ID usando findOrCreateSmart
        $positionId = null;
        if (!empty($row['CARGO'])) {
            $position = Position::findOrCreateSmart($row['CARGO']);
            $positionId = $position->position_id;
        }

        return [
            'user_data' => [
                'username' => $username,
                // 'password_hash' se asignará en el importador dependiendo si es nuevo o no
                'email' => $row['CORREO'],
                'status' => 'active', // El import siempre activa
                'must_change_password' => true,
                'first_name' => $this->removeAccents($nameParts['first_name']),
                'last_name' => $this->removeAccents($nameParts['last_name']),
                'dni' => $row['DNI'] ?? null,
                'phone' => $row['TELEFONO'] ?? null,
                'birth_date' => $birthDate,
                'position' => $row['CARGO'] ?? null,
                'department' => $row['AREA'] ?? null, // Mantener string en user también
                'address' => $row['DIRECCION'] ?? null,
                'hire_date' => $hireDate,
            ],
            'employee_data' => [
                'employee_code' => $employeeCode,
                'employee_type' => $this->mapEmployeeType($row['CARGO'] ?? ''),
                'base_salary' => $baseSalary,
                'hire_date' => $hireDate,
                'employment_status' => 'activo',
                'emergency_contact_name' => $row['CONTACTO EMERGENCIA'] ?? null,
                'emergency_contact_phone' => $row['NUMERO EMERGENCIA'] ?? null,
                'afp_code' => $row['AFP'] ?? null,
                'cuspp' => !empty($row['CUSPP']) ? $row['CUSPP'] : '0',
                'social_security_number' => $row['SUNAT'] ?? null,
                'is_commission_eligible' => 1,
                'is_bonus_eligible' => 1,
                'team_id' => $teamId,
                'office_id' => $officeId,  // Nuevo: FK a offices
                'area_id' => $areaId,       // Nuevo: FK a areas
                'position_id' => $positionId, // Nuevo: FK a positions
            ],
            'roles' => $row['ACCESOS'] ?? null
        ];
    }

    /**
     * Crear usuario
     */
    private function createUser(array $userData): User
    {
        return User::create($userData);
    }

    /**
     * Crear empleado
     */
    private function createEmployee(array $employeeData, int $userId): Employee
    {
        $employeeData['user_id'] = $userId;
        return Employee::create($employeeData);
    }

    /**
     * Actualizar usuario existente
     */
    private function updateUser(User $user, array $userData): void
    {
        // No actualizamos password ni username para evitar problemas de acceso
        unset($userData['password_hash']);
        unset($userData['username']); // Preservar username original
        
        $user->update($userData);
    }

    /**
     * Actualizar empleado existente
     */
    private function updateEmployee(Employee $employee, array $employeeData): void
    {
        unset($employeeData['employee_code']); // No cambiar código de empleado
        $employee->update($employeeData);
    }

    /**
     * Buscar ID de equipo por nombre
     */
    private function findTeamByName(string $teamName): ?int
    {
        if (empty($teamName)) {
            return null;
        }

        $team = Team::where('team_name', 'LIKE', '%' . trim($teamName) . '%')->first();

        if (!$team) {
            // Log::warning("Equipo no encontrado: {$teamName}");
            // Opcional: Crear equipo si no existe? Por ahora solo retornamos null.
            return null;
        }

        return $team->team_id;
    }

    /**
     * Asignar roles a usuario
     */
    private function assignRoles(User $user, string $accesos): void
    {
        // Limpiar roles string (ej: "Admin, Vendedor" -> ["Admin", "Vendedor"])
        $roleNames = array_map('trim', explode(',', $accesos));
        $validRoles = [];

        foreach ($roleNames as $roleName) {
            // Buscar rol insensitive case
            // Asumiendo que usamos Spatie Permission o similar con tabla roles
            $role = Role::whereRaw('LOWER(name) = ?', [mb_strtolower($roleName, 'UTF-8')])->first();
            
            if ($role) {
                $validRoles[] = $role->name; // Usar el nombre exacto de la DB
            } else {
                Log::warning("Rol no encontrado durante importación: {$roleName}");
            }
        }

        if (!empty($validRoles)) {
            // Sincronizar roles (reemplaza los existentes) o asignar (agrega)?
            // El usuario pidió "Accesos", normalmente esto define QUE roles tiene. 
            // Sync parece más seguro para reflejar estado actual del Excel.
            $user->syncRoles($validRoles);
        }
    }

    /**
     * Separar nombre completo en nombre y apellido
     * Considera que en Perú normalmente se usan dos apellidos
     */
    private function splitFullName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName));
        $count = count($parts);
        
        if ($count === 1) {
            // Solo una palabra: es el nombre, apellido vacío
            return [
                'first_name' => $parts[0],
                'last_name' => ''
            ];
        }
        
        if ($count === 2) {
            // Dos palabras: primera es nombre, segunda es apellido
            return [
                'first_name' => $parts[0],
                'last_name' => $parts[1]
            ];
        }
        
        // Tres o más palabras: las dos últimas son apellidos
        $firstName = implode(' ', array_slice($parts, 0, $count - 2));
        $lastName = implode(' ', array_slice($parts, $count - 2));
        
        return [
            'first_name' => $firstName,
            'last_name' => $lastName
        ];
    }

    /**
     * Generar username único
     */
    private function generateUsername(string $firstName, string $lastName): string
    {
        $base = strtolower(substr($firstName, 0, 1) . str_replace(' ', '', $lastName));
        $base = $this->removeAccents($base);
        
        $username = $base;
        $counter = 1;
        
        while (User::where('username', $username)->exists()) {
            $username = $base . $counter;
            $counter++;
        }
        
        return $username;
    }

    /**
     * Generar código de empleado único
     */
    private function generateEmployeeCode(): string
    {
        do {
            $code = 'EMP' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Employee::where('employee_code', $code)->exists());
        
        return $code;
    }

    /**
     * Mapear cargo a tipo de empleado
     */
    private function mapEmployeeType(string $cargo): string
    {
        $cargo = mb_strtolower(trim($cargo), 'UTF-8');
        // Normalizar caracteres especiales
        $cargo = $this->removeAccents($cargo);
        
        // Mapeo específico de cargos a tipos de empleado
        if (str_contains($cargo, 'asesor') && str_contains($cargo, 'inmobiliario')) {
            return 'asesor_inmobiliario';
        }
        if (str_contains($cargo, 'jefa') && str_contains($cargo, 'ventas')) {
            return 'jefa_de_ventas';
        }
        if (str_contains($cargo, 'arquitecto') && !str_contains($cargo, 'arquitecta')) {
            return 'arquitecto';
        }
        if (str_contains($cargo, 'arquitecta')) {
            return 'arquitecta';
        }
        if ((str_contains($cargo, 'community') || str_contains($cargo, 'comunity')) && str_contains($cargo, 'manager')) {
            return 'community_manager_corporativo';
        }
        if (str_contains($cargo, 'ingeniero') && str_contains($cargo, 'sistemas')) {
            return 'ingeniero_de_sistemas';
        }
        if (str_contains($cargo, 'disenador') && str_contains($cargo, 'audiovisual')) {
            return 'diseñador_audiovisual_area_de_marketing';
        }
        if (str_contains($cargo, 'disenador') && str_contains($cargo, 'marketing')) {
            return 'diseñador_audiovisual_area_de_marketing';
        }
        if (str_contains($cargo, 'encargado') && str_contains($cargo, 'ti')) {
            return 'encargado_de_ti';
        }
        if (str_contains($cargo, 'director')) {
            return 'director';
        }
        if (str_contains($cargo, 'analista') && str_contains($cargo, 'administracion')) {
            return 'analista_de_administracion';
        }
        if (str_contains($cargo, 'tracker')) {
            return 'tracker';
        }
        if (str_contains($cargo, 'contadora') && str_contains($cargo, 'junior')) {
            return 'contadora_junior';
        }
        
        // Por defecto, si no coincide con ningún patrón específico, asignar asesor inmobiliario
        return 'asesor_inmobiliario';
    }

    /**
     * Parsear fecha
     */
    private function parseDate($date): ?string
    {
        if (empty($date)) {
            return null;
        }
        
        // Log del valor original para debugging
        Log::info("Parseando fecha", ['original_value' => $date, 'type' => gettype($date)]);
        
        try {
            // Si es un número (Excel date serial)
            if (is_numeric($date)) {
                $numericDate = (float) $date;
                
                // Excel date serial number (desde 1900-01-01)
                if ($numericDate > 1) {
                    // Excel considera 1900-01-01 como día 1, pero hay un bug histórico
                    // donde 1900 se considera año bisiesto cuando no lo es
                    $baseDate = Carbon::create(1899, 12, 30); // Fecha base corregida
                    $parsed = $baseDate->addDays($numericDate);
                    $result = $parsed->format('Y-m-d');
                    Log::info("Fecha parseada desde serial", ['serial' => $numericDate, 'result' => $result]);
                    return $result;
                }
            }
            
            // Convertir a string para procesamiento de texto
            $dateString = trim((string) $date);
            
            // Intentar varios formatos de fecha
            $formats = [
                'Y-m-d',
                'd/m/Y',
                'd-m-Y', 
                'm/d/Y',
                'd/m/y',
                'd-m-y',
                'Y/m/d',
                'Y-m-d H:i:s',
                'd/m/Y H:i:s'
            ];
            
            foreach ($formats as $format) {
                try {
                    $parsed = Carbon::createFromFormat($format, $dateString);
                    if ($parsed && $parsed->year >= 1900 && $parsed->year <= 2100) {
                        $result = $parsed->format('Y-m-d');
                        Log::info("Fecha parseada con formato", ['format' => $format, 'input' => $dateString, 'result' => $result]);
                        return $result;
                    }
                } catch (Exception $e) {
                    // Continuar con el siguiente formato
                    continue;
                }
            }
            
            // Intentar parseo automático de Carbon
            try {
                $parsed = Carbon::parse($dateString);
                if ($parsed && $parsed->year >= 1900 && $parsed->year <= 2100) {
                    $result = $parsed->format('Y-m-d');
                    Log::info("Fecha parseada automáticamente", ['input' => $dateString, 'result' => $result]);
                    return $result;
                }
            } catch (Exception $e) {
                // Continuar
            }
            
        } catch (Exception $e) {
            Log::warning("Error parseando fecha", ['date' => $date, 'error' => $e->getMessage()]);
        }
        
        Log::warning("No se pudo parsear la fecha", ['date' => $date]);
        return null;
    }

    /**
     * Parsear decimal
     */
    private function parseDecimal($value): float
    {
        // Log del valor original para debugging
        Log::info("Parseando valor de salario", ['original_value' => $value, 'type' => gettype($value)]);
        
        if (is_numeric($value)) {
            $result = (float) $value;
            Log::info("Valor numérico procesado", ['result' => $result]);
            return $result;
        }
        
        // Convertir a string si no lo es
        $stringValue = (string) $value;
        
        // Remover símbolos de moneda específicos (S/., $, etc.) y espacios
        $cleaned = preg_replace('/[S\/\$\s]/', '', $stringValue);
        
        // Remover otros caracteres no numéricos excepto punto y coma
        $cleaned = preg_replace('/[^0-9.,]/', '', $cleaned);
        
        // Manejar formato peruano: 1,130.00 (coma como separador de miles, punto como decimal)
        if (preg_match('/^\d{1,3}(,\d{3})*\.\d{2}$/', $cleaned)) {
            // Formato: 1,130.00 - remover comas de miles
            $cleaned = str_replace(',', '', $cleaned);
        } else {
            // Para otros casos, reemplazar coma por punto
            $cleaned = str_replace(',', '.', $cleaned);
        }
        
        $result = (float) $cleaned;
        Log::info("Valor procesado", ['cleaned_value' => $cleaned, 'result' => $result]);
        
        return $result;
    }

    /**
     * Remover acentos - Mejorado para normalizar nombres completos
     * Ahora se usa tanto para usernames como para nombres y apellidos
     */
    private function removeAccents(string $string): string
    {
        $accents = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N'
        ];
        
        return strtr(trim($string), $accents);
    }

    /**
     * Validar estructura del archivo Excel
     */
    public function validateExcelStructure(array $headers): array
    {
        $requiredHeaders = ['N°', 'COLABORADOR', 'DNI', 'CORREO', 'SUELDO BASICO', 'FECHA DE INICIO', 'AREA', 'EQUIPO', 'ACCESOS', 'OFICINA'];
        $missingHeaders = [];
        
        foreach ($requiredHeaders as $required) {
            if (!in_array($required, $headers)) {
                $missingHeaders[] = $required;
            }
        }
        
        if (!empty($missingHeaders)) {
            return [
                'valid' => false,
                'error' => 'Faltan las siguientes columnas requeridas: ' . implode(', ', $missingHeaders)
            ];
        }
        
        return ['valid' => true];
    }

    /**
     * Enviar email de bienvenida con credenciales
     */
    private function sendWelcomeEmail(User $user, string $temporaryPassword): void
    {
        $loginUrl = config('app.frontend_url') ?? env('FRONTEND_URL', 'http://localhost:4200');
        
        if (config('infobip.email_via_api')) {
            app(\App\Services\InfobipMailer::class)->send(
                $user->email,
                new NewUserCredentialsMail($user, $temporaryPassword, $loginUrl)
            );
        } else {
            Mail::to($user->email)->send(
                new NewUserCredentialsMail($user, $temporaryPassword, $loginUrl)
            );
        }
    }

    /**
     * Obtener preview de la importación - Analiza qué se va a crear
     */
    public function getImportPreview(array $excelData): array
    {
        $preview = [
            'total_employees' => count($excelData),
            'new_employees' => 0,
            'existing_employees' => 0,
            'offices' => [],
            'teams' => [],
            'areas' => [],
            'positions' => [],
            'employees' => [],
        ];

        $officeNames = [];
        $teamNames = [];
        $areaNames = [];
        $positionNames = [];
        $processedEmployees = [];

        foreach ($excelData as $index => $row) {
            // Contar empleados nuevos vs existentes
            $dni = $row['DNI'] ?? null;
            $email = $row['CORREO'] ?? null;
            
            $existingUser = null;
            if ($dni || $email) {
                $existingUser = User::where('dni', $dni)
                    ->orWhere('email', $email)
                    ->first();
            }

            if ($existingUser) {
                $preview['existing_employees']++;
                $status = 'update';
            } else {
                $preview['new_employees']++;
                $status = 'new';
            }

            // Agregar al preview de empleados (máximo 15 filas)
            if (count($processedEmployees) < 15) {
                $processedEmployees[] = [
                    'row' => $index + 2,
                    'name' => $row['COLABORADOR'] ?? 'Sin nombre',
                    'dni' => $dni,
                    'email' => $email,
                    'office' => $row['OFICINA'] ?? '-',
                    'team' => $row['EQUIPO'] ?? '-',
                    'area' => $row['AREA'] ?? '-',
                    'position' => $row['CARGO'] ?? '-',
                    'status' => $status,
                ];
            }

            // Recolectar oficinas únicas
            if (!empty($row['OFICINA']) && !in_array(strtolower(trim($row['OFICINA'])), array_map('strtolower', $officeNames))) {
                $officeNames[] = trim($row['OFICINA']);
            }

            // Recolectar equipos únicos
            if (!empty($row['EQUIPO']) && !in_array(strtolower(trim($row['EQUIPO'])), array_map('strtolower', $teamNames))) {
                $teamNames[] = trim($row['EQUIPO']);
            }

            // Recolectar áreas únicas
            if (!empty($row['AREA']) && !in_array(strtolower(trim($row['AREA'])), array_map('strtolower', $areaNames))) {
                $areaNames[] = trim($row['AREA']);
            }

            // Recolectar cargos únicos
            if (!empty($row['CARGO']) && !in_array(strtolower(trim($row['CARGO'])), array_map('strtolower', $positionNames))) {
                $positionNames[] = trim($row['CARGO']);
            }
        }

        // Verificar qué oficinas existen
        foreach ($officeNames as $name) {
            $exists = Office::whereRaw('LOWER(name) = ?', [strtolower($name)])->exists();
            $preview['offices'][] = [
                'name' => $name,
                'is_new' => !$exists,
            ];
        }

        // Verificar qué equipos existen
        foreach ($teamNames as $name) {
            $exists = Team::whereRaw('LOWER(team_name) = ?', [strtolower($name)])->exists();
            $preview['teams'][] = [
                'name' => $name,
                'is_new' => !$exists,
            ];
        }

        // Verificar qué áreas existen
        foreach ($areaNames as $name) {
            $exists = Area::whereRaw('LOWER(name) = ?', [strtolower($name)])->exists();
            $preview['areas'][] = [
                'name' => $name,
                'is_new' => !$exists,
            ];
        }

        // Verificar qué cargos existen
        foreach ($positionNames as $name) {
            $exists = Position::where('name_normalized', strtolower($name))->exists();
            $category = Position::guessCategoryFromName($name);
            $preview['positions'][] = [
                'name' => $name,
                'is_new' => !$exists,
                'category' => $category,
            ];
        }

        $preview['employees'] = $processedEmployees;

        // Contadores adicionales
        $preview['new_offices'] = count(array_filter($preview['offices'], fn($o) => $o['is_new']));
        $preview['new_teams'] = count(array_filter($preview['teams'], fn($t) => $t['is_new']));
        $preview['new_areas'] = count(array_filter($preview['areas'], fn($a) => $a['is_new']));
        $preview['new_positions'] = count(array_filter($preview['positions'], fn($p) => $p['is_new']));

        return $preview;
    }
}
