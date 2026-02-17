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
        
        // Helper para normalizar texto (eliminar espacios dobles)
        $normalizeText = function($text) {
            return trim(preg_replace('/\s+/', ' ', $text ?? ''));
        };

        // Obtener/Crear Office ID
        $officeId = null;
        $officeName = $normalizeText($row['OFICINA'] ?? '');
        if (!empty($officeName)) {
            $office = Office::findOrCreateByName($officeName);
            $officeId = $office->office_id;
            Log::info("Office Found/Created: {$officeName} -> ID: {$officeId}");
        }

        // Obtener/Crear Area ID
        $areaId = null;
        $areaName = $normalizeText($row['AREA'] ?? '');
        if (!empty($areaName)) {
            $area = Area::findOrCreateByName($areaName);
            $areaId = $area->area_id;
            Log::info("Area Found/Created: {$areaName} -> ID: {$areaId}");
        }

        // Obtener/Crear Team ID
        $teamId = null;
        $teamName = $normalizeText($row['EQUIPO'] ?? '');
        if (!empty($teamName)) {
            $team = Team::findOrCreateByName($teamName);
            $teamId = $team->team_id;
            Log::info("Team Found/Created: {$teamName} -> ID: {$teamId}");
        }

        // Obtener/Crear Position ID usando findOrCreateSmart
        $positionNameRaw = $normalizeText($row['CARGO'] ?? '');
        $positionName = $this->normalizePositionName($positionNameRaw);
        
        if (!empty($positionName)) {
            $position = Position::findOrCreateSmart($positionName);
            $positionId = $position->position_id;
            Log::info("Position Found/Created: {$positionName} (Raw: {$positionNameRaw}) -> ID: {$positionId} (Category: {$position->category})");
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
                'position' => $positionName,
                'department' => $areaName, 
                'address' => $row['DIRECCION'] ?? null,
                'hire_date' => $hireDate,
            ],
            'employee_data' => [
                'employee_code' => $employeeCode,
                'employee_type' => $this->mapEmployeeType($positionName),
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
                'office_id' => $officeId,
                'area_id' => $areaId,
                'position_id' => $positionId,
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
     * Trabaja con nombres YA normalizados por normalizePositionName()
     */
    private function mapEmployeeType(string $cargo): string
    {
        $cargo = mb_strtolower(trim($cargo), 'UTF-8');
        // Normalizar caracteres especiales
        $cargo = $this->removeAccents($cargo);
        
        // Mapeo específico de cargos a tipos de empleado
        // Nota: Los nombres llegan ya normalizados (ej: "Asesor Inmobiliario", "Jefe de Ventas")
        if (str_contains($cargo, 'asesor') && (str_contains($cargo, 'inmobiliario') || str_contains($cargo, 'ventas'))) {
            return 'asesor_inmobiliario';
        }
        if (str_contains($cargo, 'jefe') && str_contains($cargo, 'ventas')) {
            return 'jefa_de_ventas';
        }
        if (str_contains($cargo, 'jefa') && str_contains($cargo, 'ventas')) {
            return 'jefa_de_ventas';
        }
        if (str_contains($cargo, 'gerente') && str_contains($cargo, 'ventas')) {
            return 'jefa_de_ventas';
        }
        if (str_contains($cargo, 'arquitecta')) {
            return 'arquitecta';
        }
        if (str_contains($cargo, 'arquitecto')) {
            return 'arquitecto';
        }
        if ((str_contains($cargo, 'community') || str_contains($cargo, 'comunity')) && str_contains($cargo, 'manager')) {
            return 'community_manager_corporativo';
        }
        if (str_contains($cargo, 'ingeniero') && str_contains($cargo, 'sistemas')) {
            return 'ingeniero_de_sistemas';
        }
        if (str_contains($cargo, 'ing.') && str_contains($cargo, 'sistemas')) {
            return 'ingeniero_de_sistemas';
        }
        if (str_contains($cargo, 'analista') && str_contains($cargo, 'datos')) {
            return 'ingeniero_de_sistemas';
        }
        if (str_contains($cargo, 'audiovisual') || (str_contains($cargo, 'disenador') && str_contains($cargo, 'marketing'))) {
            return 'diseñador_audiovisual_area_de_marketing';
        }
        if (str_contains($cargo, 'encargado') && str_contains($cargo, 'ti')) {
            return 'encargado_de_ti';
        }
        if (str_contains($cargo, 'director')) {
            return 'director';
        }
        if (str_contains($cargo, 'gerente general')) {
            return 'director';
        }
        if (str_contains($cargo, 'jefe') && str_contains($cargo, 'administracion')) {
            return 'analista_de_administracion';
        }
        if (str_contains($cargo, 'finanzas') || (str_contains($cargo, 'analista') && str_contains($cargo, 'administracion'))) {
            return 'analista_de_administracion';
        }
        if (str_contains($cargo, 'contadora') || str_contains($cargo, 'contador')) {
            return 'contadora_junior';
        }
        if (str_contains($cargo, 'gestor') && str_contains($cargo, 'cobranza')) {
            return 'analista_de_administracion';
        }
        if (str_contains($cargo, 'backoffice') || str_contains($cargo, 'back office')) {
            return 'analista_de_administracion';
        }
        if (str_contains($cargo, 'servicio') && str_contains($cargo, 'cliente')) {
            return 'analista_de_administracion';
        }
        if (str_contains($cargo, 'tracker')) {
            return 'tracker';
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
     * Normalizar nombre del cargo basado en reglas de negocio
     * Los nombres retornados deben coincidir con los name_normalized de la tabla positions
     */
    private function normalizePositionName(string $positionName): string
    {
        $normalized = mb_strtolower(trim($positionName), 'UTF-8');
        $normalized = $this->removeAccents($normalized);

        // Regla 1: Unificar Asesores
        if ($normalized === 'asesor de ventas' || $normalized === 'asesor inmobiliario' || $normalized === 'asesora inmobiliaria' || $normalized === 'asesora de ventas') {
            return 'Asesor Inmobiliario';
        }

        // Regla 2: Unificar Jefes de Ventas (remover oficinas específicas)
        // Ejemplo: "Jefe de Ventas Of Piura" -> "Jefe de Ventas"
        if (str_contains($normalized, 'jefe de ventas') || str_contains($normalized, 'jefa de ventas')) {
            return 'Jefe de Ventas';
        }

        // Regla 3: Gerente de Ventas
        if (str_contains($normalized, 'gerente de ventas') || str_contains($normalized, 'gerente ventas')) {
            return 'Gerente de Ventas';
        }

        // Regla 4: Gerente General
        if (str_contains($normalized, 'gerente general')) {
            return 'Gerente General';
        }

        // Regla 5: Ingeniero de Sistemas y Analista de Datos SON DISTINTOS
        if (str_contains($normalized, 'analista de datos') || str_contains($normalized, 'analista datos')) {
            return 'Ing. Sistemas y Analista de Datos'; 
        }

        // Regla 6: Ingeniero de Sistemas (solo)
        if (str_contains($normalized, 'ingeniero de sistemas') || str_contains($normalized, 'ingeniero sistemas') || str_contains($normalized, 'encargado de ti') || str_contains($normalized, 'encargado ti')) {
            return 'Ingeniero de Sistemas';
        }

        // Regla 7: Community Manager
        if (str_contains($normalized, 'community') && str_contains($normalized, 'manager')) {
            return 'Community Manager';
        }

        // Regla 8: Arquitectos - mantener género separado (así están en la DB)
        if (str_contains($normalized, 'arquitecta')) {
            return 'Arquitecta'; 
        }
        if (str_contains($normalized, 'arquitecto')) {
            return 'Arquitecto'; 
        }

        // Regla 9: Audiovisual / Diseñador (en DB está como "Audiovisual")
        if (str_contains($normalized, 'audiovisual') || (str_contains($normalized, 'disenador') && str_contains($normalized, 'marketing'))) {
            return 'Audiovisual';
        }

        // Regla 10: Director de Tecnología
        if (str_contains($normalized, 'director') && (str_contains($normalized, 'tecnologia') || str_contains($normalized, 'ti') || str_contains($normalized, 'tech'))) {
            return 'Director de Tecnología';
        }

        // Regla 11: Director de Desarrollo Comercial
        if (str_contains($normalized, 'director') && (str_contains($normalized, 'comercial') || str_contains($normalized, 'desarrollo'))) {
            return 'Director de Desarrollo Comercial e Institucional';
        }

        // Regla 12: Jefe de Administración y Finanzas (ANTES de la regla general de Finanzas)
        if (str_contains($normalized, 'jefe') && str_contains($normalized, 'administracion')) {
            return 'Jefe de Administración y Finanzas';
        }

        // Regla 13: Finanzas / Contadora / Analista de Administración
        if (str_contains($normalized, 'finanzas') || str_contains($normalized, 'contadora') || str_contains($normalized, 'contador') || (str_contains($normalized, 'analista') && str_contains($normalized, 'administracion'))) {
            return 'Finanzas';
        }

        // Regla 14: Gestor de Cobranzas
        if (str_contains($normalized, 'gestor') && str_contains($normalized, 'cobranza')) {
            return 'Gestor de Cobranzas';
        }

        // Regla 15: Servicio al Cliente
        if (str_contains($normalized, 'servicio') && str_contains($normalized, 'cliente')) {
            return 'Servicio al Cliente';
        }

        // Regla 16: BackOffice
        if (str_contains($normalized, 'backoffice') || str_contains($normalized, 'back office')) {
            return 'BackOffice';
        }

        // Regla 17: Tracker
        if (str_contains($normalized, 'tracker')) {
            return 'Tracker';
        }

        // Default: Retornar nombre original limpio con mayúsculas iniciales
        return mb_convert_case($positionName, MB_CASE_TITLE, "UTF-8");
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

        // Helper para normalizar texto (eliminar espacios dobles)
        $normalizeText = function($text) {
            return trim(preg_replace('/\s+/', ' ', $text ?? ''));
        };

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

            $officeName = $normalizeText($row['OFICINA'] ?? '');
            $teamName = $normalizeText($row['EQUIPO'] ?? '');
            $areaName = $normalizeText($row['AREA'] ?? '');
            $positionNameRaw = $normalizeText($row['CARGO'] ?? '');
            // Normalizar el cargo igual que en la importación real
            $positionName = !empty($positionNameRaw) ? $this->normalizePositionName($positionNameRaw) : '';

            // Agregar al preview de empleados (máximo 15 filas)
            if (count($processedEmployees) < 15) {
                $processedEmployees[] = [
                    'row' => $index + 2,
                    'name' => $row['COLABORADOR'] ?? 'Sin nombre',
                    'dni' => $dni,
                    'email' => $email,
                    'office' => $officeName ?: '-',
                    'team' => $teamName ?: '-',
                    'area' => $areaName ?: '-',
                    'position' => $positionName ?: '-',
                    'status' => $status,
                ];
            }

            // Recolectar oficinas únicas (case insensitive check)
            if (!empty($officeName) && !in_array(mb_strtolower($officeName), array_map('mb_strtolower', $officeNames))) {
                $officeNames[] = $officeName;
            }

            // Recolectar equipos únicos
            if (!empty($teamName) && !in_array(mb_strtolower($teamName), array_map('mb_strtolower', $teamNames))) {
                $teamNames[] = $teamName;
            }

            // Recolectar áreas únicas
            if (!empty($areaName) && !in_array(mb_strtolower($areaName), array_map('mb_strtolower', $areaNames))) {
                $areaNames[] = $areaName;
            }

            // Recolectar cargos únicos
            if (!empty($positionName) && !in_array(mb_strtolower($positionName), array_map('mb_strtolower', $positionNames))) {
                $positionNames[] = $positionName;
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

        // Verificar qué cargos existen (usando nombre normalizado)
        foreach ($positionNames as $name) {
            $normalizedCheck = mb_strtolower(trim($name), 'UTF-8');
            $exists = Position::where('name_normalized', $normalizedCheck)->exists();
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
