-- Script para actualizar los números de cuota en payment_schedules existentes
-- Este script asigna números de cuota basados en el orden de las fechas de vencimiento por contrato

UPDATE payment_schedules ps1
SET installment_number = (
    SELECT COUNT(*) + 1
    FROM payment_schedules ps2
    WHERE ps2.contract_id = ps1.contract_id
    AND ps2.due_date < ps1.due_date
)
WHERE installment_number IS NULL;

-- Verificar los resultados
SELECT 
    contract_id,
    installment_number,
    due_date,
    amount,
    status
FROM payment_schedules 
WHERE contract_id IN (
    SELECT DISTINCT contract_id 
    FROM payment_schedules 
    WHERE installment_number IS NOT NULL
)
ORDER BY contract_id, installment_number;