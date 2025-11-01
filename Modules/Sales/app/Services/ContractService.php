<?php

namespace Modules\Sales\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Models\Client;
use Modules\HumanResources\Models\Employee;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotFinancialTemplate;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\Reservation;
use Modules\Collections\Models\PaymentSchedule;
use Modules\Sales\Repositories\ContractRepository;

class ContractService
{
    public function __construct(
        protected ContractRepository $contractRepo
    ) {}

    /**
     * Crear contrato directamente con cliente y lote (sin reserva)
     */
    public function createDirectContract(array $data): Contract
    {
        return DB::transaction(function () use ($data) {
            // Validar que se proporcionen client_id y lot_id
            if (empty($data['client_id']) || empty($data['lot_id'])) {
                throw new Exception('client_id y lot_id son requeridos para contratos directos');
            }

            // Validar que el cliente existe
            $client = Client::find($data['client_id']);
            if (!$client) {
                throw new Exception('Cliente no encontrado');
            }

            // Validar que el lote existe y está disponible
            $lot = Lot::with('financialTemplate')->find($data['lot_id']);
            if (!$lot) {
                throw new Exception('Lote no encontrado');
            }

            if ($lot->status !== 'disponible') {
                throw new Exception('El lote no está disponible para venta');
            }

            // Obtener datos financieros del template del lote si no se proporcionan
            if ($lot->financialTemplate) {
                $template = $lot->financialTemplate;
                
                // Solo usar datos del template si no se proporcionan en $data
                $data['total_price'] = $data['total_price'] ?? $template->precio_lista;
                $data['down_payment'] = $data['down_payment'] ?? $template->enganche;
                $data['financing_amount'] = $data['financing_amount'] ?? $template->monto_financiar;
                $data['monthly_payment'] = $data['monthly_payment'] ?? $template->cuota_mensual;
                $data['balloon_payment'] = $data['balloon_payment'] ?? $template->globo;
            }

            // Asegurar que reservation_id sea null para contratos directos
            $data['reservation_id'] = null;

            // Crear el contrato
            $contract = $this->contractRepo->create($data);

            // Actualizar estado del lote
            $lot->update(['status' => 'vendido']);

            Log::info('Contrato directo creado', [
                'contract_id' => $contract->contract_id,
                'client_id' => $data['client_id'],
                'lot_id' => $data['lot_id']
            ]);

            return $contract->load(['client', 'lot', 'advisor']);
        });
    }

    /**
     * Crear contrato desde reserva (método existente)
     */
    public function createFromReservation(array $data): Contract
    {
        return DB::transaction(function () use ($data) {
            // Validar que se proporcione reservation_id
            if (empty($data['reservation_id'])) {
                throw new Exception('reservation_id es requerido para contratos desde reserva');
            }

            // Validar que la reserva existe
            $reservation = Reservation::with(['client', 'lot.financialTemplate'])->find($data['reservation_id']);
            if (!$reservation) {
                throw new Exception('Reserva no encontrada');
            }

            // Obtener datos financieros del template del lote si no se proporcionan
            if ($reservation->lot && $reservation->lot->financialTemplate) {
                $template = $reservation->lot->financialTemplate;
                
                // Solo usar datos del template si no se proporcionan en $data
                $data['total_price'] = $data['total_price'] ?? $template->precio_lista;
                $data['down_payment'] = $data['down_payment'] ?? $template->enganche;
                $data['financing_amount'] = $data['financing_amount'] ?? $template->monto_financiar;
                $data['monthly_payment'] = $data['monthly_payment'] ?? $template->cuota_mensual;
                $data['balloon_payment'] = $data['balloon_payment'] ?? $template->globo;
            }

            // Asegurar que client_id y lot_id sean null para contratos desde reserva
            $data['client_id'] = null;
            $data['lot_id'] = null;

            // Crear el contrato
            $contract = $this->contractRepo->create($data);

            // Actualizar estado del lote
            if ($reservation->lot) {
                $reservation->lot->update(['status' => 'vendido']);
            }

            Log::info('Contrato creado desde reserva', [
                'contract_id' => $contract->contract_id,
                'reservation_id' => $data['reservation_id']
            ]);

            return $contract->load(['reservation.client', 'reservation.lot', 'advisor']);
        });
    }

    /**
     * Crear contrato (método unificado que decide el tipo según los datos)
     */
    public function create(array $data): Contract
    {
        // Si tiene reservation_id, crear desde reserva
        if (!empty($data['reservation_id'])) {
            return $this->createFromReservation($data);
        }
        
        // Si tiene client_id y lot_id, crear contrato directo
        if (!empty($data['client_id']) && !empty($data['lot_id'])) {
            return $this->createDirectContract($data);
        }

        throw new Exception('Debe proporcionar reservation_id O (client_id Y lot_id) para crear un contrato');
    }

    /**
     * Actualizar contrato
     */
    public function update(Contract $contract, array $data): Contract
    {
        return $this->contractRepo->update($contract, $data);
    }

    /**
     * Eliminar contrato
     */
    public function delete(Contract $contract): void
    {
        DB::transaction(function () use ($contract) {
            // Actualizar estado del lote según el tipo de contrato
            if ($contract->isDirectContract()) {
                $lot = $contract->lot;
                if ($lot) {
                    // Para contratos directos, verificar si hay otras reservas
                    $newStatus = $lot->reservations()->exists() ? 'reservado' : 'disponible';
                    $lot->update(['status' => $newStatus]);
                }
            } else {
                // Para contratos desde reserva, usar la lógica existente del repository
                $this->contractRepo->delete($contract);
                return;
            }

            // Eliminar el contrato
            $contract->delete();
        });
    }

    /**
     * Buscar contrato por ID
     */
    public function find(int $id): ?Contract
    {
        return $this->contractRepo->find($id);
    }

    /**
     * Obtener contratos paginados con filtros
     */
    public function paginate(int $perPage = 15, array $filters = [])
    {
        return $this->contractRepo->paginate($perPage, $filters);
    }
}