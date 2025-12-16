<?php

namespace Modules\HumanResources\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewUserCredentialsMail;
use Illuminate\Support\Str;
use Modules\HumanResources\Http\Requests\StoreEmployeeRequest;
use Modules\HumanResources\Http\Requests\UpdateEmployeeRequest;
use Modules\HumanResources\Repositories\EmployeeRepository;
use Modules\HumanResources\Services\BonusService;
use Modules\HumanResources\Services\CommissionService;
use Modules\HumanResources\Transformers\EmployeeResource;
use Modules\Security\Models\User;
use Modules\Security\Transformers\UserResource;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeRepository $employeeRepo,
        protected CommissionService $commissionService,
        protected BonusService $bonusService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['employee_type', 'team_id', 'status', 'search']);
        if ($request->has('paginate') && $request->paginate === 'true') {
            $employees = $this->employeeRepo->getPaginated($filters, $request->get('per_page', 15));
            return response()->json([
                'success' => true,
                'data' => EmployeeResource::collection($employees->items()),
                'meta' => [
                    'current_page' => $employees->currentPage(),
                    'last_page' => $employees->lastPage(),
                    'per_page' => $employees->perPage(),
                    'total' => $employees->total()
                ],
                'message' => 'Empleados obtenidos exitosamente'
            ]);
        } else {
            $employees = $this->employeeRepo->getAll($filters);
            return response()->json([
                'success' => true,
                'data' => EmployeeResource::collection($employees),
                'message' => 'Empleados obtenidos exitosamente'
            ]);
        }
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            if (!isset($data['employee_code'])) {
                $data['employee_code'] = $this->employeeRepo->generateEmployeeCode();
            }

            $employee = $this->employeeRepo->create($data);

            return response()->json([
                'success' => true,
                'data' => new EmployeeResource($employee->load(['user', 'team'])),
                'message' => 'Empleado creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        $id = (int) $id;
        $employee = $this->employeeRepo->findById($id);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Empleado no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new EmployeeResource($employee->load(['user', 'team', 'currentMonthCommissions', 'currentMonthBonuses'])),
            'message' => 'Empleado obtenido exitosamente'
        ]);
    }

    public function update(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        try {
            $employee = $this->employeeRepo->update($id, $request->validated());

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empleado no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new EmployeeResource($employee->load(['user', 'team'])),
                'message' => 'Empleado actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->employeeRepo->delete($id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empleado no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Empleado eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar empleado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function advisors(): JsonResponse
    {
        $advisors = $this->employeeRepo->getAdvisors();

        return response()->json([
            'success' => true,
            'data' => EmployeeResource::collection($advisors),
            'message' => 'Asesores obtenidos exitosamente'
        ]);
    }

    public function dashboard(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        try {
            // Verificar que el empleado existe
            $employee = $this->employeeRepo->findById($id);
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empleado no encontrado'
                ], 404);
            }

            // Si no es admin, verificar que solo pueda ver su propio dashboard
            if (!$user->hasRole('admin') && $user->employee && $user->employee->employee_id !== $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para ver este dashboard'
                ], 403);
            }

            $dashboard = $this->commissionService->getAdvisorDashboard($id, $month, $year);
            $dashboard['bonuses'] = $this->bonusService->getBonusesForDashboard($id);

            return response()->json([
                'success' => true,
                'data' => $dashboard,
                'message' => 'Dashboard obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error en dashboard: ' . $e->getMessage(), [
                'exception' => $e,
                'employee_id' => $id,
                'month' => $month,
                'year' => $year,
                'user_id' => $user->user_id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function adminDashboard(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        try {
            $dashboard = $this->commissionService->getAdminDashboard($month, $year);
            $dashboard['bonuses'] = $this->bonusService->getBonusesForAdminDashboard($month, $year);
            //$dashboard['employees'] = $this->employeeRepo->getAdvisors();
            $dashboard['employees'] = $this->employeeRepo->getAll(['month' => $month, 'year' => $year]);

            return response()->json([
                'success' => true,
                'data' => $dashboard,
                'message' => 'Admin dashboard obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::info('Error en adminDashboard: ' . $e->getMessage(), [
                'exception' => $e,
                'month' => $month,
                'year' => $year,
                'user_id' => $request->user() ? $request->user()->user_id : null
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function topPerformers(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $limit = $request->get('limit', 10);

        $topPerformers = $this->commissionService->getTopPerformers($month, $year, $limit);

        return response()->json([
            'success' => true,
            'data' => EmployeeResource::collection($topPerformers),
            'message' => 'Top performers obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener empleados que no tienen usuario asociado
     */
    public function getEmployeesWithoutUser(): JsonResponse
    {
        try {
            $employees = $this->employeeRepo->getEmployeesWithoutUser();

            return response()->json([
                'success' => true,
                'data' => EmployeeResource::collection($employees),
                'message' => 'Empleados sin usuario obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener empleados: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener empleados con sus comisiones para integración HR-Collections
     */
    public function withCommissions(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'verification_status', 'payment_status', 'period_start', 'period_end']);
            
            // Obtener empleados que tienen comisiones
            $employees = $this->employeeRepo->getEmployeesWithCommissions($filters);
            
            $employeesData = $employees->map(function($employee) {
                return [
                    'employee_id' => $employee->employee_id,
                    'employee_code' => $employee->employee_code,
                    'full_name' => $employee->full_name,
                    'email' => $employee->user?->email,
                    'team' => $employee->team?->name,
                    'commissions' => $employee->commissions->map(function($commission) {
                        return [
                            'commission_id' => $commission->commission_id,
                            'amount' => $commission->amount,
                            'verification_status' => $commission->verification_status,
                            'payment_status' => $commission->payment_status,
                            'period_start' => $commission->period_start,
                            'period_end' => $commission->period_end,
                            'customer_id' => $commission->customer_id,
                            'customer_name' => $commission->customer?->name,
                            'verified_at' => $commission->verified_at,
                            'eligible_date' => $commission->eligible_date
                        ];
                    }),
                    'total_commission_amount' => $employee->commissions->sum('amount'),
                    'verified_commission_amount' => $employee->commissions->where('verification_status', 'verified')->sum('amount'),
                    'pending_commission_amount' => $employee->commissions->where('verification_status', 'pending')->sum('amount')
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $employeesData,
                'total_employees' => $employeesData->count(),
                'total_commission_amount' => $employeesData->sum('total_commission_amount'),
                'message' => 'Empleados con comisiones obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener empleados con comisiones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reenviar correo de credenciales o resetear contraseña del usuario asociado al empleado
     */
    public function notifyUserCredentials(Request $request, int $employeeId): JsonResponse
    {
        try {
            $employee = $this->employeeRepo->findById($employeeId);
            if (!$employee || !$employee->user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empleado o usuario no encontrado'
                ], 404);
            }

            $user = $employee->user;
            $newPassword = $request->get('password');
            if ($newPassword) {
                $user->password_hash = \Illuminate\Support\Facades\Hash::make($newPassword);
                $user->must_change_password = true;
                $user->save();
            }

            $loginUrl = config('app.frontend_url') ?? env('FRONTEND_URL', 'http://localhost:4200');
            if (config('clicklab.email_via_api')) {
                $html = view('emails.new-user-credentials', [
                    'user' => $user,
                    'temporaryPassword' => $newPassword,
                    'loginUrl' => $loginUrl,
                ])->render();
                app(\App\Services\ClicklabClient::class)->sendEmail($user->email, 'Tus Credenciales de Acceso', $html);
            } else {
                Mail::to($user->email)->send(new NewUserCredentialsMail($user, $newPassword, $loginUrl));
            }

            return response()->json([
                'success' => true,
                'message' => 'Correo de credenciales enviado'
            ]);
        } catch (\Exception $e) {
            Log::error('Error enviando credenciales', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error enviando credenciales: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar usuario para un empleado existente
     */
    public function generateUser(Request $request, int $employeeId): JsonResponse
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'username' => 'required|string|max:60|unique:users,username',
                'email' => 'required|email|max:120|unique:users,email',
                'password' => 'required|string|min:6',
                'first_name' => 'required|string|max:50',
                'last_name' => 'required|string|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar que el empleado existe
            $employee = $this->employeeRepo->findById($employeeId);
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empleado no encontrado'
                ], 404);
            }

            // Verificar que el empleado no tenga usuario
            if ($employee->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este empleado ya tiene un usuario asociado'
                ], 422);
            }

            \Illuminate\Support\Facades\DB::beginTransaction();

            try {
                // Crear usuario
                $userData = $request->only(['username', 'email', 'first_name', 'last_name']);
                $plainPassword = $request->password ?: Str::random(12);
                $userData['password_hash'] = \Illuminate\Support\Facades\Hash::make($plainPassword);
                $userData['status'] = 'active';
                $userData['must_change_password'] = true;

                $user = User::create($userData);

                // Actualizar empleado con el user_id
                $employee->update(['user_id' => $user->user_id]);

                \Illuminate\Support\Facades\DB::commit();

                // Enviar correo de bienvenida con credenciales
                $emailSent = false;
                try {
                    $loginUrl = config('app.frontend_url') ?? env('FRONTEND_URL', 'http://localhost:4200');
                    if (config('clicklab.email_via_api')) {
                        $html = view('emails.new-user-credentials', [
                            'user' => $user,
                            'temporaryPassword' => $plainPassword,
                            'loginUrl' => $loginUrl,
                        ])->render();
                        app(\App\Services\ClicklabClient::class)->sendEmail($user->email, 'Tus Credenciales de Acceso', $html);
                    } else {
                        Mail::to($user->email)->send(new NewUserCredentialsMail($user, $plainPassword, $loginUrl));
                    }
                    $emailSent = true;
                } catch (\Exception $mailError) {
                    Log::error('Error enviando correo de bienvenida', [
                        'user_id' => $user->user_id,
                        'email' => $user->email,
                        'error' => $mailError->getMessage()
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'user' => new UserResource($user),
                        'employee' => new EmployeeResource($employee->load('user')),
                        'email_sent' => $emailSent
                    ],
                    'message' => 'Usuario creado y asociado exitosamente'
                ], 201);

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar usuario: ' . $e->getMessage()
            ], 500);
        }
    }
}
