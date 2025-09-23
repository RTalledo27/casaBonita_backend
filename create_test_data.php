<?php

// Script para crear datos de prueba usando Artisan Tinker
echo "🔧 CREANDO DATOS DE PRUEBA PARA VERIFICACIÓN DE PAGOS\n";
echo "====================================================\n\n";

echo "Ejecuta los siguientes comandos en 'php artisan tinker':\n\n";

echo "// 1. Verificar contrato\n";
echo "\$contract = \\Modules\\Sales\\Models\\Contract::where('contract_id', 94)->first();\n";
echo "echo \"Contrato: \" . \$contract->contract_number;\n\n";

echo "// 2. Crear primera cuota (PAID)\n";
echo "\$ar1 = \\Modules\\Collections\\Models\\AccountReceivable::create([\n";
echo "    'client_id' => \$contract->client_id,\n";
echo "    'contract_id' => 94,\n";
echo "    'ar_number' => 'AR-94-001',\n";
echo "    'issue_date' => now()->subDays(60),\n";
echo "    'due_date' => now()->subDays(30),\n";
echo "    'original_amount' => 5000.00,\n";
echo "    'outstanding_amount' => 0.00,\n";
echo "    'currency' => 'PEN',\n";
echo "    'status' => 'PAID',\n";
echo "    'description' => 'Primera cuota - PRUEBA'\n";
echo "]);\n\n";

echo "// 3. Crear segunda cuota (PAID)\n";
echo "\$ar2 = \\Modules\\Collections\\Models\\AccountReceivable::create([\n";
echo "    'client_id' => \$contract->client_id,\n";
echo "    'contract_id' => 94,\n";
echo "    'ar_number' => 'AR-94-002',\n";
echo "    'issue_date' => now()->subDays(30),\n";
echo "    'due_date' => now(),\n";
echo "    'original_amount' => 5000.00,\n";
echo "    'outstanding_amount' => 0.00,\n";
echo "    'currency' => 'PEN',\n";
echo "    'status' => 'PAID',\n";
echo "    'description' => 'Segunda cuota - PRUEBA'\n";
echo "]);\n\n";

echo "// 4. Crear pago para primera cuota\n";
echo "\$payment1 = \\Modules\\Collections\\Models\\CustomerPayment::create([\n";
echo "    'client_id' => \$contract->client_id,\n";
echo "    'ar_id' => \$ar1->ar_id,\n";
echo "    'payment_number' => 'PAY-000001',\n";
echo "    'payment_date' => now()->subDays(25),\n";
echo "    'amount' => 5000.00,\n";
echo "    'currency' => 'PEN',\n";
echo "    'payment_method' => 'TRANSFER',\n";
echo "    'reference_number' => 'REF-123456',\n";
echo "    'notes' => 'Pago primera cuota - PRUEBA'\n";
echo "]);\n\n";

echo "// 5. Crear pago para segunda cuota\n";
echo "\$payment2 = \\Modules\\Collections\\Models\\CustomerPayment::create([\n";
echo "    'client_id' => \$contract->client_id,\n";
echo "    'ar_id' => \$ar2->ar_id,\n";
echo "    'payment_number' => 'PAY-000002',\n";
echo "    'payment_date' => now()->subDays(5),\n";
echo "    'amount' => 5000.00,\n";
echo "    'currency' => 'PEN',\n";
echo "    'payment_method' => 'TRANSFER',\n";
echo "    'reference_number' => 'REF-789012',\n";
echo "    'notes' => 'Pago segunda cuota - PRUEBA'\n";
echo "]);\n\n";

echo "// 6. Verificar datos creados\n";
echo "echo 'AR1 ID: ' . \$ar1->ar_id;\n";
echo "echo 'AR2 ID: ' . \$ar2->ar_id;\n";
echo "echo 'Payment1 ID: ' . \$payment1->payment_id;\n";
echo "echo 'Payment2 ID: ' . \$payment2->payment_id;\n";
echo "exit\n\n";

echo "Después de ejecutar estos comandos, los datos estarán listos para probar la verificación.\n";