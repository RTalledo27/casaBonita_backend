<?php

namespace Modules\Security\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{


    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $permissions = [
            //MODULE SECURITY - PERMISSIONS
            'security.access', //Permiso para acceder a la sección de seguridad
            'security.permissions.view',
            'security.permissions.store',
            'security.permissions.update',
            'security.permissions.destroy',
            //MODULE SECURITY - ROLES
            'security.roles.view',
            'security.roles.store',
            'security.roles.update',
            'security.roles.destroy',
            //MODULE SECURITY - USERS
            'security.users.index',
            'security.users.store',
            'security.users.update',
            'security.users.destroy',
            'security.users.change-password',
            'security.users.toggle-status',
            //MODULE SECURITY - AUTH

            //MODULE CRM - ADDRESS
            'crm.addresses.view',
            'crm.addresses.store',
            'crm.addresses.update',
            'crm.addresses.destroy',
            //MODULE CRM - CLIENTS
            'crm.clients.view',
            'crm.clients.store',
            'crm.clients.update',
            'crm.clients.delete',

            'crm.clients.spouses.view',
            'crm.clients.spouses.store',
            'crm.clients.spouses.delete',
            'crm.clients.export',
            //MODULE CRM - INTERACTIONS
            'crm.access', //Permiso para acceder a la sección de CRM
            'crm.interactions.view',
            'crm.interactions.store',
            'crm.interactions.update',
            'crm.interactions.delete',


            // MODULE INVENTORY
            'inventory.access',
            'inventory.manzanas.view',
            'inventory.manzanas.store',
            'inventory.manzanas.update',
            'inventory.manzanas.delete',

           'inventory.street-types.view',
           'inventory.street-types.store',
            'inventory.street-types.update',
           'inventory.street-types.delete',

            'inventory.lots.view',
            'inventory.lots.store',
            'inventory.lots.update',
            'inventory.lots.delete',
            'inventory.media.manage',

            // MODULE SALES
            'sales.reservations.access',
            'sales.reservations.view',
            'sales.reservations.store',
            'sales.reservations.update',
            'sales.reservations.cancel',
            'sales.reservations.convert',
            'sales.access',
            'sales.contracts.view',
            'sales.contracts.store',
            'sales.contracts.update',
            'sales.contracts.delete',
            'sales.conversions.process',

            //lot-media:
            'inventory.media.index',
            'inventory.media.store',
            'inventory.media.update',
            'inventory.media.destroy',



            // MODULE SERVICE DESK - TICKETS
            'service-desk.tickets.view',       // Ver lista y detalle de tickets
            'service-desk.tickets.store',      // Crear ticket
            'service-desk.tickets.update',     // Editar ticket
            'service-desk.tickets.delete',     // Eliminar ticket
            'service-desk.tickets.assign',     // Asignar ticket
            'service-desk.tickets.actions',    // Agregar acción al ticket (comentario/cambio estado)
            'service-desk.tickets.close',      // Cerrar ticket

            // MODULE SERVICE DESK - ACCIONES (HISTORIAL)
            'service-desk.actions.view',       // Ver historial/acciones de tickets
            'service-desk.actions.store',      // Agregar acción (comentario, cambio de estado)
            'service-desk.actions.update',     // Editar acción
            'service-desk.actions.delete',     // Eliminar acción

            // MODULE HUMAN RESOURCES
            'hr.access',                       // Acceso general al módulo HR
            
            // MODULE HR - EMPLOYEES
            'hr.employees.view',               // Ver empleados
            'hr.employees.store',              // Crear empleados
            'hr.employees.update',             // Actualizar empleados
            'hr.employees.destroy',            // Eliminar empleados
            'hr.employees.generate-user',      // Generar usuario para empleado
            'hr.employees.dashboard',          // Ver dashboard de empleado
            
            // MODULE HR - COMMISSIONS
            'hr.commissions.view',             // Ver comisiones
            'hr.commissions.store',            // Crear comisiones
            'hr.commissions.update',           // Actualizar comisiones
            'hr.commissions.destroy',          // Eliminar comisiones
            'hr.commissions.pay',              // Pagar comisiones
            'hr.commissions.process',          // Procesar comisiones
            'hr.commissions.split-payment',   // Crear pagos divididos
            
            // MODULE HR - COMMISSION VERIFICATIONS
            'hr.commission-verifications.view',     // Ver verificaciones de comisiones
            'hr.commission-verifications.verify',   // Verificar pagos de comisiones
            'hr.commission-verifications.reverse',  // Revertir verificaciones
            'hr.commission-verifications.process',  // Procesar verificaciones automáticas
            'hr.commission-verifications.stats',    // Ver estadísticas de verificaciones
            
            // MODULE HR - PAYROLL
            'hr.payroll.view',                 // Ver nóminas
            'hr.payroll.generate',             // Generar nóminas
            'hr.payroll.process',              // Procesar nóminas
            'hr.payroll.approve',              // Aprobar nóminas
            
            // MODULE HR - BONUSES
            'hr.bonuses.view',                 // Ver bonos
            'hr.bonuses.store',                // Crear bonos
            'hr.bonuses.update',               // Actualizar bonos
            'hr.bonuses.destroy',              // Eliminar bonos
            'hr.bonuses.process',              // Procesar bonos automáticos
            
            // MODULE HR - BONUS TYPES
            'hr.bonus-types.view',             // Ver tipos de bonos
            'hr.bonus-types.store',            // Crear tipos de bonos
            'hr.bonus-types.update',           // Actualizar tipos de bonos
            'hr.bonus-types.destroy',          // Eliminar tipos de bonos
            
            // MODULE HR - BONUS GOALS
            'hr.bonus-goals.view',             // Ver metas de bonos
            'hr.bonus-goals.store',            // Crear metas de bonos
            'hr.bonus-goals.update',           // Actualizar metas de bonos
            'hr.bonus-goals.destroy',          // Eliminar metas de bonos
            
            // MODULE HR - TEAMS
            'hr.teams.view',                   // Ver equipos
            'hr.teams.store',                  // Crear equipos
            'hr.teams.update',                 // Actualizar equipos
            'hr.teams.destroy',                // Eliminar equipos
            'hr.teams.assign-leader',          // Asignar líder de equipo
            'hr.teams.toggle-status',          // Cambiar estado de equipo
            
            // MODULE HR - EMPLOYEE IMPORT
            'hr.employee-import.validate',     // Validar importación de empleados
            'hr.employee-import.import',       // Importar empleados
            'hr.employee-import.template',     // Descargar template de importación
            
            // MODULE COLLECTIONS
            'collections.access',                        // Acceso general al módulo Collections
            
            // MODULE COLLECTIONS - CUSTOMER PAYMENTS
            'collections.customer-payments.view',        // Ver pagos de clientes
            'collections.customer-payments.create',      // Crear pagos de clientes
            'collections.customer-payments.update',      // Actualizar pagos de clientes
            'collections.customer-payments.delete',      // Eliminar pagos de clientes
            'collections.customer-payments.redetect',    // Redetectar tipo de cuota
            'collections.customer-payments.stats',       // Ver estadísticas de detección
            
            // MODULE COLLECTIONS - ACCOUNTS RECEIVABLE
            'collections.accounts-receivable.view',      // Ver cuentas por cobrar
            'collections.accounts-receivable.overdue',   // Ver cuentas por cobrar vencidas
            
            // MODULE COLLECTIONS - SIMPLIFIED
            'collections.view',                          // Ver módulo Collections simplificado
            'collections.create',                        // Crear cronogramas de pago
            'collections.reports',                       // Ver reportes de Collections

        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'sanctum',
            ]);
        }
    }
}
