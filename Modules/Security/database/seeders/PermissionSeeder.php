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



        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'sanctum',
            ]);
        }
    }
}
