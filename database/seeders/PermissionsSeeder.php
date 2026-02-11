<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Modules\Security\Models\Role;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Este seeder crea todos los permisos necesarios para el sistema
     * y los asigna al rol Administrador.
     * 
     * Ejecutar con: php artisan db:seed --class=PermissionsSeeder
     */
    public function run(): void
    {
        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = $this->getPermissions();
        
        // Create all permissions
        $createdCount = 0;
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                ['guard_name' => 'sanctum']
            );
            $createdCount++;
        }

        $this->command->info("Created/verified {$createdCount} permissions.");

        // Create or get Admin role
        $adminRole = Role::firstOrCreate(
            ['name' => 'Administrador'],
            ['guard_name' => 'sanctum', 'description' => 'Acceso completo al sistema']
        );
        
        // Refresh to ensure role_id is populated
        $adminRole->refresh();
        
        if (!$adminRole->role_id) {
            $this->command->error("Error: No se pudo crear el rol Administrador");
            return;
        }

        // Assign all permissions to Admin role
        $allPermissionNames = collect($permissions)->pluck('name')->toArray();
        $adminRole->syncPermissions($allPermissionNames);

        $this->command->info("Admin role 'Administrador' has been assigned all {$createdCount} permissions.");

        // Create additional common roles
        $this->createCommonRoles($permissions);
    }

    /**
     * Get all system permissions organized by module
     */
    private function getPermissions(): array
    {
        return array_merge(
            $this->getSecurityPermissions(),
            $this->getSalesPermissions(),
            $this->getCollectionsPermissions(),
            $this->getInventoryPermissions(),
            $this->getServiceDeskPermissions(),
            $this->getHumanResourcesPermissions(),
            $this->getReportsPermissions(),
            $this->getCrmPermissions(),
            $this->getAuditPermissions(),
            $this->getIntegrationsPermissions(),
            $this->getIntegrationsPermissions(),
            $this->getBillingPermissions(), // Facturación Electrónica
            $this->getFrontendSidebarPermissions() // Permisos usados por el frontend sidebar
        );
    }

    /**
     * Billing Module Permissions (SUNAT)
     */
    private function getBillingPermissions(): array
    {
        return [
            ['name' => 'billing.access', 'description' => 'Acceso al módulo de Facturación'],
            ['name' => 'billing.emit', 'description' => 'Emitir comprobantes (Boletas/Facturas)'],
            ['name' => 'billing.view-reports', 'description' => 'Ver reportes de facturación'],
        ];
    }


    /**
     * Security Module Permissions
     */
    private function getSecurityPermissions(): array
    {
        return [
            // Users - Include all naming variants used in controllers
            ['name' => 'security.users.view', 'description' => 'Ver lista de usuarios'],
            ['name' => 'security.users.index', 'description' => 'Ver lista de usuarios (alias)'],
            ['name' => 'security.users.create', 'description' => 'Crear usuarios'],
            ['name' => 'security.users.store', 'description' => 'Crear usuarios (alias)'],
            ['name' => 'security.users.edit', 'description' => 'Editar usuarios'],
            ['name' => 'security.users.update', 'description' => 'Editar usuarios (alias)'],
            ['name' => 'security.users.delete', 'description' => 'Eliminar usuarios'],
            ['name' => 'security.users.destroy', 'description' => 'Eliminar usuarios (alias)'],
            ['name' => 'security.users.change-password', 'description' => 'Cambiar contraseña de usuarios'],
            ['name' => 'security.users.toggle-status', 'description' => 'Activar/Desactivar usuarios'],
            ['name' => 'security.users.sync-roles', 'description' => 'Asignar roles a usuarios'],
            
            // Roles
            ['name' => 'security.roles.view', 'description' => 'Ver lista de roles'],
            ['name' => 'security.roles.create', 'description' => 'Crear roles'],
            ['name' => 'security.roles.store', 'description' => 'Crear roles (alias)'],
            ['name' => 'security.roles.edit', 'description' => 'Editar roles'],
            ['name' => 'security.roles.update', 'description' => 'Editar roles (alias)'],
            ['name' => 'security.roles.delete', 'description' => 'Eliminar roles'],
            ['name' => 'security.roles.destroy', 'description' => 'Eliminar roles (alias)'],
            ['name' => 'security.roles.sync-permissions', 'description' => 'Asignar permisos a roles'],
            
            // Permissions
            ['name' => 'security.permissions.view', 'description' => 'Ver lista de permisos'],
            ['name' => 'security.permissions.create', 'description' => 'Crear permisos'],
            ['name' => 'security.permissions.store', 'description' => 'Crear permisos (alias)'],
            ['name' => 'security.permissions.edit', 'description' => 'Editar permisos'],
            ['name' => 'security.permissions.update', 'description' => 'Editar permisos (alias)'],
            ['name' => 'security.permissions.delete', 'description' => 'Eliminar permisos'],
            ['name' => 'security.permissions.destroy', 'description' => 'Eliminar permisos (alias)'],
            
            // Activity Logs
            ['name' => 'security.activity-logs.view', 'description' => 'Ver logs de actividad'],
            ['name' => 'security.audit.view', 'description' => 'Ver logs de auditoría'],
            
            // Notifications
            ['name' => 'security.notifications.view', 'description' => 'Ver notificaciones'],
            ['name' => 'security.notifications.manage', 'description' => 'Gestionar notificaciones'],
        ];
    }

    /**
     * Sales Module Permissions
     */
    private function getSalesPermissions(): array
    {
        return [
            // Contracts - Include all naming variants
            ['name' => 'sales.contracts.view', 'description' => 'Ver contratos'],
            ['name' => 'sales.contracts.create', 'description' => 'Crear contratos'],
            ['name' => 'sales.contracts.store', 'description' => 'Crear contratos (alias)'],
            ['name' => 'sales.contracts.edit', 'description' => 'Editar contratos'],
            ['name' => 'sales.contracts.update', 'description' => 'Editar contratos (alias)'],
            ['name' => 'sales.contracts.delete', 'description' => 'Eliminar contratos'],
            ['name' => 'sales.contracts.destroy', 'description' => 'Eliminar contratos (alias)'],
            ['name' => 'sales.contracts.preview', 'description' => 'Previsualizar contratos'],
            ['name' => 'sales.contracts.generate-schedule', 'description' => 'Generar cronograma de pagos'],
            ['name' => 'sales.contracts.import', 'description' => 'Importar contratos'],
            
            // Reservations
            ['name' => 'sales.reservations.view', 'description' => 'Ver reservaciones'],
            ['name' => 'sales.reservations.create', 'description' => 'Crear reservaciones'],
            ['name' => 'sales.reservations.store', 'description' => 'Crear reservaciones (alias)'],
            ['name' => 'sales.reservations.edit', 'description' => 'Editar reservaciones'],
            ['name' => 'sales.reservations.update', 'description' => 'Editar reservaciones (alias)'],
            ['name' => 'sales.reservations.delete', 'description' => 'Eliminar reservaciones'],
            ['name' => 'sales.reservations.destroy', 'description' => 'Eliminar reservaciones (alias)'],
            ['name' => 'sales.reservations.convert', 'description' => 'Convertir reservación a contrato'],
            ['name' => 'sales.reservations.confirm-payment', 'description' => 'Confirmar pago de reservación'],
            
            // Payment Schedules
            ['name' => 'sales.schedules.view', 'description' => 'Ver cronogramas de pago'],
            ['name' => 'sales.schedules.index', 'description' => 'Ver cronogramas de pago (alias)'],
            ['name' => 'sales.schedules.create', 'description' => 'Crear cronogramas de pago'],
            ['name' => 'sales.schedules.store', 'description' => 'Crear cronogramas de pago (alias)'],
            ['name' => 'sales.schedules.edit', 'description' => 'Editar cronogramas de pago'],
            ['name' => 'sales.schedules.update', 'description' => 'Editar cronogramas de pago (alias)'],
            ['name' => 'sales.schedules.delete', 'description' => 'Eliminar cronogramas de pago'],
            ['name' => 'sales.schedules.destroy', 'description' => 'Eliminar cronogramas de pago (alias)'],
            ['name' => 'sales.schedules.mark-paid', 'description' => 'Marcar cuotas como pagadas'],
            
            // Payments
            ['name' => 'sales.payments.view', 'description' => 'Ver pagos'],
            ['name' => 'sales.payments.create', 'description' => 'Registrar pagos'],
            ['name' => 'sales.payments.store', 'description' => 'Registrar pagos (alias)'],
            ['name' => 'sales.payments.edit', 'description' => 'Editar pagos'],
            ['name' => 'sales.payments.update', 'description' => 'Editar pagos (alias)'],
            ['name' => 'sales.payments.delete', 'description' => 'Eliminar pagos'],
            ['name' => 'sales.payments.destroy', 'description' => 'Eliminar pagos (alias)'],
            ['name' => 'sales.payments.upload-voucher', 'description' => 'Subir comprobantes de pago'],
            
            // Contract Approvals
            ['name' => 'sales.approvals.view', 'description' => 'Ver aprobaciones de contratos'],
            ['name' => 'sales.approvals.approve', 'description' => 'Aprobar contratos'],
            ['name' => 'sales.approvals.reject', 'description' => 'Rechazar contratos'],
        ];
    }

    /**
     * Collections Module Permissions
     */
    private function getCollectionsPermissions(): array
    {
        return [
            // Dashboard
            ['name' => 'collections.dashboard.view', 'description' => 'Ver dashboard de cobranzas'],
            
            // Schedules
            ['name' => 'collections.schedules.view', 'description' => 'Ver cronogramas de cuotas'],
            ['name' => 'collections.schedules.create', 'description' => 'Crear cronogramas de cuotas'],
            ['name' => 'collections.schedules.edit', 'description' => 'Editar cronogramas de cuotas'],
            ['name' => 'collections.schedules.delete', 'description' => 'Eliminar cronogramas de cuotas'],
            
            // Reports
            ['name' => 'collections.reports.view', 'description' => 'Ver reportes de cobranzas'],
            ['name' => 'collections.reports.export', 'description' => 'Exportar reportes de cobranzas'],
            
            // Customer Payments
            ['name' => 'collections.customer-payments.view', 'description' => 'Ver pagos de clientes'],
            ['name' => 'collections.customer-payments.create', 'description' => 'Crear pagos de clientes'],
            ['name' => 'collections.customer-payments.update', 'description' => 'Actualizar pagos de clientes'],
            ['name' => 'collections.customer-payments.delete', 'description' => 'Eliminar pagos de clientes'],
            ['name' => 'collections.customer-payments.redetect', 'description' => 'Re-detectar cuota de pago'],
            
            // Accounts Receivable
            ['name' => 'collections.accounts-receivable.view', 'description' => 'Ver cuentas por cobrar'],
            
            // HR Integration
            ['name' => 'collections.hr-integration.view', 'description' => 'Ver integración HR-Collections'],
            ['name' => 'collections.hr-integration.sync', 'description' => 'Sincronizar HR-Collections'],
            ['name' => 'collections.hr-integration.process', 'description' => 'Procesar elegibles HR'],
            ['name' => 'collections.hr-integration.mark', 'description' => 'Marcar como elegible HR'],
            
            // Followups
            ['name' => 'collections.followups.view', 'description' => 'Ver seguimientos de cobranza'],
            ['name' => 'collections.followups.create', 'description' => 'Crear seguimientos de cobranza'],
            ['name' => 'collections.followups.edit', 'description' => 'Editar seguimientos de cobranza'],
            
            // Collections View (general)
            ['name' => 'collections.view', 'description' => 'Ver módulo de cobranzas'],
        ];
    }

    /**
     * Inventory Module Permissions
     */
    private function getInventoryPermissions(): array
    {
        return [
            // Lots - Include all naming variants
            ['name' => 'inventory.lots.view', 'description' => 'Ver lotes'],
            ['name' => 'inventory.lots.create', 'description' => 'Crear lotes'],
            ['name' => 'inventory.lots.store', 'description' => 'Crear lotes (alias)'],
            ['name' => 'inventory.lots.edit', 'description' => 'Editar lotes'],
            ['name' => 'inventory.lots.update', 'description' => 'Editar lotes (alias)'],
            ['name' => 'inventory.lots.delete', 'description' => 'Eliminar lotes'],
            ['name' => 'inventory.lots.destroy', 'description' => 'Eliminar lotes (alias)'],
            ['name' => 'inventory.lots.import', 'description' => 'Importar lotes'],
            ['name' => 'inventory.lots.import-external', 'description' => 'Importar lotes externos (Logicware)'],
            
            // Manzanas
            ['name' => 'inventory.manzanas.view', 'description' => 'Ver manzanas'],
            ['name' => 'inventory.manzanas.create', 'description' => 'Crear manzanas'],
            ['name' => 'inventory.manzanas.store', 'description' => 'Crear manzanas (alias)'],
            ['name' => 'inventory.manzanas.edit', 'description' => 'Editar manzanas'],
            ['name' => 'inventory.manzanas.update', 'description' => 'Editar manzanas (alias)'],
            ['name' => 'inventory.manzanas.delete', 'description' => 'Eliminar manzanas'],
            ['name' => 'inventory.manzanas.destroy', 'description' => 'Eliminar manzanas (alias)'],
            
            // Street Types
            ['name' => 'inventory.street-types.view', 'description' => 'Ver tipos de calle'],
            ['name' => 'inventory.street-types.create', 'description' => 'Crear tipos de calle'],
            ['name' => 'inventory.street-types.store', 'description' => 'Crear tipos de calle (alias)'],
            ['name' => 'inventory.street-types.edit', 'description' => 'Editar tipos de calle'],
            ['name' => 'inventory.street-types.update', 'description' => 'Editar tipos de calle (alias)'],
            ['name' => 'inventory.street-types.delete', 'description' => 'Eliminar tipos de calle'],
            ['name' => 'inventory.street-types.destroy', 'description' => 'Eliminar tipos de calle (alias)'],
            
            // Lot Media - Use the actual permission names from controllers
            ['name' => 'inventory.media.index', 'description' => 'Ver medios de lotes'],
            ['name' => 'inventory.media.store', 'description' => 'Subir medios de lotes'],
            ['name' => 'inventory.media.update', 'description' => 'Editar medios de lotes'],
            ['name' => 'inventory.media.destroy', 'description' => 'Eliminar medios de lotes'],
            ['name' => 'inventory.lot-media.view', 'description' => 'Ver medios de lotes (alias)'],
            ['name' => 'inventory.lot-media.create', 'description' => 'Subir medios de lotes (alias)'],
            ['name' => 'inventory.lot-media.delete', 'description' => 'Eliminar medios de lotes (alias)'],
        ];
    }

    /**
     * Service Desk Module Permissions
     */
    private function getServiceDeskPermissions(): array
    {
        return [
            // Dashboard
            ['name' => 'service-desk.dashboard.view', 'description' => 'Ver dashboard de Service Desk'],
            
            // Tickets (Requests) - Include all naming variants used in controllers
            ['name' => 'service-desk.tickets.view', 'description' => 'Ver tickets'],
            ['name' => 'service-desk.tickets.create', 'description' => 'Crear tickets'],
            ['name' => 'service-desk.tickets.store', 'description' => 'Crear tickets (alias)'],
            ['name' => 'service-desk.tickets.edit', 'description' => 'Editar tickets'],
            ['name' => 'service-desk.tickets.update', 'description' => 'Editar tickets (alias)'],
            ['name' => 'service-desk.tickets.delete', 'description' => 'Eliminar tickets'],
            ['name' => 'service-desk.tickets.destroy', 'description' => 'Eliminar tickets (alias)'],
            ['name' => 'service-desk.tickets.assign', 'description' => 'Asignar técnicos a tickets'],
            ['name' => 'service-desk.tickets.escalate', 'description' => 'Escalar tickets'],
            ['name' => 'service-desk.tickets.close', 'description' => 'Cerrar tickets'],
            ['name' => 'service-desk.tickets.comment', 'description' => 'Agregar comentarios a tickets'],
            ['name' => 'service-desk.tickets.actions', 'description' => 'Gestionar acciones en tickets'],
            
            // Actions
            ['name' => 'service-desk.actions.view', 'description' => 'Ver acciones de tickets'],
            ['name' => 'service-desk.actions.create', 'description' => 'Crear acciones'],
            
            // SLA Configuration
            ['name' => 'service-desk.sla.view', 'description' => 'Ver configuración de SLA'],
            ['name' => 'service-desk.sla.edit', 'description' => 'Editar configuración de SLA'],
            
            // Categories
            ['name' => 'service-desk.categories.view', 'description' => 'Ver categorías de servicio'],
            ['name' => 'service-desk.categories.create', 'description' => 'Crear categorías de servicio'],
            ['name' => 'service-desk.categories.edit', 'description' => 'Editar categorías de servicio'],
            ['name' => 'service-desk.categories.delete', 'description' => 'Eliminar categorías de servicio'],
            
            // Notification Permissions
            ['name' => 'service-desk.receive-escalations', 'description' => 'Recibir notificaciones de escalaciones'],
            ['name' => 'service-desk.receive-high-priority', 'description' => 'Recibir notificaciones de tickets de alta prioridad'],
            
            // Attachments
            ['name' => 'service-desk.attachments.view', 'description' => 'Ver archivos adjuntos de tickets'],
            ['name' => 'service-desk.attachments.create', 'description' => 'Subir archivos adjuntos a tickets'],
            ['name' => 'service-desk.attachments.delete', 'description' => 'Eliminar archivos adjuntos de tickets'],
        ];
    }

    /**
     * Human Resources Module Permissions
     */
    private function getHumanResourcesPermissions(): array
    {
        return [
            // Employees
            ['name' => 'hr.employees.view', 'description' => 'Ver empleados'],
            ['name' => 'hr.employees.create', 'description' => 'Crear empleados'],
            ['name' => 'hr.employees.edit', 'description' => 'Editar empleados'],
            ['name' => 'hr.employees.delete', 'description' => 'Eliminar empleados'],
            ['name' => 'hr.employees.generate-user', 'description' => 'Generar usuario para empleado'],
            ['name' => 'hr.employees.commissions.view', 'description' => 'Ver comisiones de empleados'],
            ['name' => 'hr.employees.import', 'description' => 'Importar empleados'],
            
            // Commissions
            ['name' => 'hr.commissions.view', 'description' => 'Ver comisiones'],
            ['name' => 'hr.commissions.create', 'description' => 'Crear comisiones'],
            ['name' => 'hr.commissions.process', 'description' => 'Procesar comisiones'],
            ['name' => 'hr.commissions.pay', 'description' => 'Pagar comisiones'],
            ['name' => 'hr.commissions.verify', 'description' => 'Verificar pagos de comisiones'],
            
            // Payroll
            ['name' => 'hr.payroll.view', 'description' => 'Ver nómina'],
            ['name' => 'hr.payroll.generate', 'description' => 'Generar nómina'],
            ['name' => 'hr.payroll.process', 'description' => 'Procesar nómina'],
            ['name' => 'hr.payroll.approve', 'description' => 'Aprobar nómina'],
            
            // Bonuses
            ['name' => 'hr.bonuses.view', 'description' => 'Ver bonos'],
            ['name' => 'hr.bonuses.create', 'description' => 'Crear bonos'],
            ['name' => 'hr.bonuses.process', 'description' => 'Procesar bonos automáticos'],
            
            // Bonus Types
            ['name' => 'hr.bonus-types.view', 'description' => 'Ver tipos de bono'],
            ['name' => 'hr.bonus-types.create', 'description' => 'Crear tipos de bono'],
            ['name' => 'hr.bonus-types.edit', 'description' => 'Editar tipos de bono'],
            ['name' => 'hr.bonus-types.delete', 'description' => 'Eliminar tipos de bono'],
            
            // Bonus Goals
            ['name' => 'hr.bonus-goals.view', 'description' => 'Ver metas de bono'],
            ['name' => 'hr.bonus-goals.create', 'description' => 'Crear metas de bono'],
            ['name' => 'hr.bonus-goals.edit', 'description' => 'Editar metas de bono'],
            ['name' => 'hr.bonus-goals.delete', 'description' => 'Eliminar metas de bono'],
            
            // Teams
            ['name' => 'hr.teams.view', 'description' => 'Ver equipos'],
            ['name' => 'hr.teams.create', 'description' => 'Crear equipos'],
            ['name' => 'hr.teams.edit', 'description' => 'Editar equipos'],
            ['name' => 'hr.teams.delete', 'description' => 'Eliminar equipos'],
            ['name' => 'hr.teams.assign-leader', 'description' => 'Asignar líder de equipo'],
            
            // Tax Parameters
            ['name' => 'hr.tax-parameters.view', 'description' => 'Ver parámetros tributarios'],
            ['name' => 'hr.tax-parameters.edit', 'description' => 'Editar parámetros tributarios'],
            
            // Offices
            ['name' => 'hr.offices.view', 'description' => 'Ver oficinas'],
            ['name' => 'hr.offices.create', 'description' => 'Crear oficinas'],
            ['name' => 'hr.offices.edit', 'description' => 'Editar oficinas'],
            ['name' => 'hr.offices.delete', 'description' => 'Eliminar oficinas'],
            
            // Areas
            ['name' => 'hr.areas.view', 'description' => 'Ver áreas'],
            ['name' => 'hr.areas.create', 'description' => 'Crear áreas'],
            ['name' => 'hr.areas.edit', 'description' => 'Editar áreas'],
            ['name' => 'hr.areas.delete', 'description' => 'Eliminar áreas'],
            
            // Positions (Cargos)
            ['name' => 'hr.positions.view', 'description' => 'Ver cargos'],
            ['name' => 'hr.positions.create', 'description' => 'Crear cargos'],
            ['name' => 'hr.positions.edit', 'description' => 'Editar cargos'],
            ['name' => 'hr.positions.delete', 'description' => 'Eliminar cargos'],
        ];
    }

    /**
     * Reports Module Permissions
     */
    private function getReportsPermissions(): array
    {
        return [
            // General Reports
            ['name' => 'reports.view', 'description' => 'Ver reportes'],
            ['name' => 'reports.export', 'description' => 'Exportar reportes'],
            ['name' => 'reports.download', 'description' => 'Descargar reportes'],
            
            // Sales Reports
            ['name' => 'reports.sales.view', 'description' => 'Ver reportes de ventas'],
            ['name' => 'reports.sales.export', 'description' => 'Exportar reportes de ventas'],
            
            // Payment Schedule Reports
            ['name' => 'reports.payment-schedules.view', 'description' => 'Ver reportes de cronogramas'],
            
            // Projections
            ['name' => 'reports.projections.view', 'description' => 'Ver proyecciones'],
            ['name' => 'reports.projections.export', 'description' => 'Exportar proyecciones'],
        ];
    }

    /**
     * CRM Module Permissions
     */
    private function getCrmPermissions(): array
    {
        return [
            // General Access
            ['name' => 'crm.access', 'description' => 'Acceso al módulo CRM'],
            
            // Clients
            ['name' => 'crm.clients.view', 'description' => 'Ver clientes'],
            ['name' => 'crm.clients.create', 'description' => 'Crear clientes'],
            ['name' => 'crm.clients.edit', 'description' => 'Editar clientes'],
            ['name' => 'crm.clients.delete', 'description' => 'Eliminar clientes'],
            ['name' => 'crm.clients.export', 'description' => 'Exportar clientes'],
            ['name' => 'crm.clients.verify', 'description' => 'Verificar clientes'],
            
            // Addresses
            ['name' => 'crm.addresses.view', 'description' => 'Ver direcciones'],
            ['name' => 'crm.addresses.create', 'description' => 'Crear direcciones'],
            ['name' => 'crm.addresses.edit', 'description' => 'Editar direcciones'],
            ['name' => 'crm.addresses.delete', 'description' => 'Eliminar direcciones'],
            
            // Interactions
            ['name' => 'crm.interactions.view', 'description' => 'Ver interacciones'],
            ['name' => 'crm.interactions.create', 'description' => 'Crear interacciones'],
            ['name' => 'crm.interactions.edit', 'description' => 'Editar interacciones'],
            ['name' => 'crm.interactions.delete', 'description' => 'Eliminar interacciones'],
            
            // Family Members
            ['name' => 'crm.family-members.view', 'description' => 'Ver miembros de familia'],
            ['name' => 'crm.family-members.create', 'description' => 'Crear miembros de familia'],
            ['name' => 'crm.family-members.edit', 'description' => 'Editar miembros de familia'],
            ['name' => 'crm.family-members.delete', 'description' => 'Eliminar miembros de familia'],
        ];
    }

    /**
     * Audit Module Permissions
     */
    private function getAuditPermissions(): array
    {
        return [
            ['name' => 'audit.view', 'description' => 'Ver registros de auditoría'],
            ['name' => 'audit.logs.view', 'description' => 'Ver logs de auditoría'],
            ['name' => 'audit.export', 'description' => 'Exportar auditoría'],
        ];
    }

    /**
     * Integrations Module Permissions
     */
    private function getIntegrationsPermissions(): array
    {
        return [
            ['name' => 'integrations.view', 'description' => 'Ver integraciones'],
            ['name' => 'integrations.manage', 'description' => 'Gestionar integraciones'],
            ['name' => 'integrations.logs.view', 'description' => 'Ver logs de integración'],
            ['name' => 'integrations.signatures.view', 'description' => 'Ver firmas digitales'],
            ['name' => 'integrations.signatures.create', 'description' => 'Crear firmas digitales'],
        ];
    }

    /**
     * Frontend Sidebar Permissions
     * These permissions are used by the Angular frontend sidebar to show/hide menu items
     * They match EXACTLY what sidebar.component.ts staticNavItems expects
     */
    private function getFrontendSidebarPermissions(): array
    {
        return [
            // ==============================================
            // MODULE .access PERMISSIONS (Required to show modules in sidebar)
            // ==============================================
            ['name' => 'crm.access', 'description' => 'Acceso al módulo CRM'],
            ['name' => 'security.access', 'description' => 'Acceso al módulo Seguridad'],
            ['name' => 'sales.access', 'description' => 'Acceso al módulo Ventas'],
            ['name' => 'inventory.access', 'description' => 'Acceso al módulo Inventario'],
            ['name' => 'finance.access', 'description' => 'Acceso al módulo Finanzas'],
            ['name' => 'collections.access', 'description' => 'Acceso al módulo Cobranzas'],
            ['name' => 'hr.access', 'description' => 'Acceso al módulo RRHH'],
            ['name' => 'accounting.access', 'description' => 'Acceso al módulo Contabilidad'],
            ['name' => 'reports.access', 'description' => 'Acceso al módulo Reportes'],
            ['name' => 'service-desk.access', 'description' => 'Acceso al módulo Service Desk'],
            ['name' => 'audit.access', 'description' => 'Acceso al módulo Auditoría'],
            
            // ==============================================
            // CRM CHILD PERMISSIONS
            // ==============================================
            ['name' => 'crm.clients.list', 'description' => 'Ver lista de clientes'],
            ['name' => 'crm.clients.update', 'description' => 'Actualizar clientes'],
            
            // ==============================================
            // SECURITY CHILD PERMISSIONS
            // ==============================================
            ['name' => 'security.users.list', 'description' => 'Ver lista de usuarios'],
            ['name' => 'security.users.manage', 'description' => 'Gestionar usuarios'],
            ['name' => 'security.roles.list', 'description' => 'Ver lista de roles'],
            ['name' => 'security.roles.manage', 'description' => 'Gestionar roles'],
            ['name' => 'security.audit.view', 'description' => 'Ver auditoría de seguridad'],
            
            // ==============================================
            // SALES CHILD PERMISSIONS
            // ==============================================
            ['name' => 'sales.contracts.list', 'description' => 'Ver lista de contratos'],
            ['name' => 'sales.reservations.access', 'description' => 'Acceso a reservaciones'],
            ['name' => 'sales.cuts.view', 'description' => 'Ver cortes de venta'],
            ['name' => 'sales.lots.list', 'description' => 'Ver lista de lotes'],
            ['name' => 'sales.lots.manage', 'description' => 'Gestionar lotes'],
            
            // ==============================================
            // FINANCE CHILD PERMISSIONS
            // ==============================================
            ['name' => 'finance.payments.list', 'description' => 'Ver lista de pagos'],
            ['name' => 'finance.payments.create', 'description' => 'Crear pagos'],
            ['name' => 'finance.payments.update', 'description' => 'Actualizar pagos'],
            ['name' => 'finance.commissions.list', 'description' => 'Ver lista de comisiones'],
            ['name' => 'finance.commissions.manage', 'description' => 'Gestionar comisiones'],
            
            // ==============================================
            // COLLECTIONS CHILD PERMISSIONS
            // ==============================================
            ['name' => 'collections.view', 'description' => 'Ver cobranzas'],
            ['name' => 'collections.create', 'description' => 'Crear cronogramas'],
            ['name' => 'collections.reports', 'description' => 'Ver reportes de cobranzas'],
            ['name' => 'collections.schedules.list', 'description' => 'Ver lista de cronogramas'],
            ['name' => 'collections.schedules.manage', 'description' => 'Gestionar cronogramas'],
            ['name' => 'collections.followups.view', 'description' => 'Ver seguimientos de cobranza'],
            
            // ==============================================
            // INVENTORY CHILD PERMISSIONS
            // ==============================================
            ['name' => 'inventory.lots.list', 'description' => 'Ver lista de lotes'],
            ['name' => 'inventory.lots.manage', 'description' => 'Gestionar lotes'],
            
            // ==============================================
            // HR CHILD PERMISSIONS
            // ==============================================
            ['name' => 'hr.employees.dashboard', 'description' => 'Ver dashboard de empleados'],
            
            // ==============================================
            // ACCOUNTING CHILD PERMISSIONS
            // ==============================================
            ['name' => 'accounting.reports.view', 'description' => 'Ver reportes contables'],
            ['name' => 'accounting.transactions.list', 'description' => 'Ver transacciones'],
            
            // ==============================================
            // SERVICE DESK CHILD PERMISSIONS
            // ==============================================
            ['name' => 'servicedesk.tickets.list', 'description' => 'Ver lista de tickets'],
            ['name' => 'servicedesk.tickets.create', 'description' => 'Crear tickets'],
            ['name' => 'servicedesk.tickets.update', 'description' => 'Actualizar tickets'],
            
            // ==============================================
            // REPORTS CHILD PERMISSIONS
            // ==============================================
            ['name' => 'reports.view', 'description' => 'Ver reportes'],
            ['name' => 'reports.view_dashboard', 'description' => 'Ver dashboard de reportes'],
            ['name' => 'reports.view_sales', 'description' => 'Ver reportes de ventas'],
            ['name' => 'reports.view_payments', 'description' => 'Ver reportes de pagos'],
            ['name' => 'reports.view_projections', 'description' => 'Ver proyecciones'],
            ['name' => 'reports.export', 'description' => 'Exportar reportes'],
            
            // ==============================================
            // AUDIT CHILD PERMISSIONS
            // ==============================================
            ['name' => 'audit.logs.view', 'description' => 'Ver logs de auditoría'],
        ];
    }

    /**
     * Create additional common roles
     */
    private function createCommonRoles(array $allPermissions): void
    {
        // Vendedor Role
        $vendedorRole = Role::firstOrCreate(
            ['name' => 'Vendedor'],
            ['guard_name' => 'sanctum', 'description' => 'Rol de vendedor/asesor comercial']
        );
        $vendedorRole->refresh();
        
        if ($vendedorRole->role_id) {
            $vendedorPermissions = [
                'sales.contracts.view', 'sales.contracts.create',
                'sales.reservations.view', 'sales.reservations.create', 'sales.reservations.convert',
                'sales.payments.view', 'sales.payments.create',
                'crm.access', 'crm.clients.view', 'crm.clients.create', 'crm.clients.edit',
                'crm.interactions.view', 'crm.interactions.create',
                'inventory.lots.view',
                'security.notifications.view',
            ];
            $vendedorRole->syncPermissions($vendedorPermissions);
            $this->command->info("Created 'Vendedor' role with " . count($vendedorPermissions) . " permissions.");
        }

        // Cobranzas Role
        $cobranzasRole = Role::firstOrCreate(
            ['name' => 'Cobranzas'],
            ['guard_name' => 'sanctum', 'description' => 'Rol de personal de cobranzas']
        );
        $cobranzasRole->refresh();
        
        if ($cobranzasRole->role_id) {
            $cobranzasPermissions = [
                'collections.dashboard.view',
                'collections.schedules.view', 'collections.schedules.edit',
                'collections.reports.view',
                'collections.customer-payments.view', 'collections.customer-payments.create', 'collections.customer-payments.update',
                'collections.accounts-receivable.view',
                'collections.followups.view', 'collections.followups.create', 'collections.followups.edit',
                'collections.view',
                'crm.clients.view',
                'sales.contracts.view', 'sales.schedules.view',
                'security.notifications.view',
            ];
            $cobranzasRole->syncPermissions($cobranzasPermissions);
            $this->command->info("Created 'Cobranzas' role with " . count($cobranzasPermissions) . " permissions.");
        }

        // Técnico Service Desk Role
        $tecnicoRole = Role::firstOrCreate(
            ['name' => 'Tecnico'],
            ['guard_name' => 'sanctum', 'description' => 'Rol de técnico de servicio']
        );
        $tecnicoRole->refresh();
        
        $tecnicoPermissions = [
            'service-desk.dashboard.view',
            'service-desk.tickets.view', 'service-desk.tickets.edit',
            'service-desk.tickets.comment', 'service-desk.tickets.close',
            'service-desk.actions.view', 'service-desk.actions.create',
            'service-desk.categories.view',
            'security.notifications.view',
        ];
        
        if ($tecnicoRole->role_id) {
            $tecnicoRole->syncPermissions($tecnicoPermissions);
            $this->command->info("Created 'Tecnico' role with " . count($tecnicoPermissions) . " permissions.");
        }

        // Supervisor Service Desk Role
        $supervisorRole = Role::firstOrCreate(
            ['name' => 'Supervisor'],
            ['guard_name' => 'sanctum', 'description' => 'Rol de supervisor de servicio']
        );
        $supervisorRole->refresh();
        
        if ($supervisorRole->role_id) {
            $supervisorPermissions = array_merge($tecnicoPermissions, [
                'service-desk.tickets.assign', 'service-desk.tickets.escalate',
                'service-desk.tickets.create', 'service-desk.tickets.delete',
                'service-desk.sla.view', 'service-desk.sla.edit',
                'service-desk.categories.create', 'service-desk.categories.edit', 'service-desk.categories.delete',
                'service-desk.receive-escalations', 'service-desk.receive-high-priority',
            ]);
            $supervisorRole->syncPermissions($supervisorPermissions);
            $this->command->info("Created 'Supervisor' role with " . count($supervisorPermissions) . " permissions.");
        }

        // RRHH Role
        $rrhhRole = Role::firstOrCreate(
            ['name' => 'RRHH'],
            ['guard_name' => 'sanctum', 'description' => 'Rol de Recursos Humanos']
        );
        $rrhhRole->refresh();
        
        if ($rrhhRole->role_id) {
            $rrhhPermissions = [
                'hr.employees.view', 'hr.employees.create', 'hr.employees.edit', 'hr.employees.delete',
                'hr.employees.generate-user', 'hr.employees.commissions.view', 'hr.employees.import',
                'hr.commissions.view', 'hr.commissions.create', 'hr.commissions.process', 'hr.commissions.pay', 'hr.commissions.verify',
                'hr.payroll.view', 'hr.payroll.generate', 'hr.payroll.process', 'hr.payroll.approve',
                'hr.bonuses.view', 'hr.bonuses.create', 'hr.bonuses.process',
                'hr.bonus-types.view', 'hr.bonus-types.create', 'hr.bonus-types.edit', 'hr.bonus-types.delete',
                'hr.bonus-goals.view', 'hr.bonus-goals.create', 'hr.bonus-goals.edit', 'hr.bonus-goals.delete',
                'hr.teams.view', 'hr.teams.create', 'hr.teams.edit', 'hr.teams.delete', 'hr.teams.assign-leader',
                'hr.tax-parameters.view', 'hr.tax-parameters.edit',
                'hr.offices.view', 'hr.offices.create', 'hr.offices.edit', 'hr.offices.delete',
                'hr.areas.view', 'hr.areas.create', 'hr.areas.edit', 'hr.areas.delete',
                'hr.positions.view', 'hr.positions.create', 'hr.positions.edit', 'hr.positions.delete',
                'security.notifications.view',
            ];
            $rrhhRole->syncPermissions($rrhhPermissions);
            $this->command->info("Created 'RRHH' role with " . count($rrhhPermissions) . " permissions.");
        }
    }
}
