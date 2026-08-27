<?php
/**
 * Pruebas de aceptación transaccionales para vacaciones v2.
 * Usa la base local configurada y revierte todos los datos al finalizar.
 */
chdir(__DIR__ . '/../public');
require_once '../app/bootstrap.php';

function assertVacation($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "OK  {$message}\n";
}

$db = new Database();
$db->beginTransaction();
try {
    $db->query("SELECT id FROM users WHERE company_id IS NOT NULL ORDER BY id LIMIT 1 FOR UPDATE");
    $user = $db->single();
    assertVacation((bool)$user, 'Existe un empleado de prueba');
    $userId = (int)$user->id;

    $db->query("SELECT id FROM collective_agreements WHERE code='CEC'");
    $cec = $db->single();
    assertVacation((bool)$cec, 'Comercio está precargado');
    $db->query("UPDATE users SET hire_date='2010-01-01', agreement_id=:aid WHERE id=:uid");
    $db->bind(':aid', (int)$cec->id);
    $db->bind(':uid', $userId);
    $db->execute();

    $balances = new VacationBalance($db);
    $p2025 = $balances->createPeriod([
        'user_id'=>$userId, 'period_label'=>'2025', 'period_start'=>'2025-01-01', 'period_end'=>'2025-12-31',
        'balance_type'=>'annual', 'agreement_id'=>(int)$cec->id, 'count_mode_snapshot'=>'calendar',
        'days_entitled'=>7, 'days_taken'=>0, 'liquidated_by'=>$userId,
    ]);
    $p2026 = $balances->createPeriod([
        'user_id'=>$userId, 'period_label'=>'2026', 'period_start'=>'2026-01-01', 'period_end'=>'2026-12-31',
        'balance_type'=>'annual', 'agreement_id'=>(int)$cec->id, 'count_mode_snapshot'=>'calendar',
        'days_entitled'=>21, 'days_taken'=>0, 'liquidated_by'=>$userId,
    ]);
    assertVacation($p2025 > 0 && $p2026 > 0, 'Se crean períodos anuales separados');

    $dates10 = [];
    for ($i=0; $i<10; $i++) $dates10[] = date('Y-m-d', strtotime('2026-09-01 +' . $i . ' days'));
    $workSchedule = new WorkSchedule($db);
    $workSchedule->saveDaySchedule($userId, $dates10[0], [[
        'type'=>'custom', 'shift_id'=>null, 'start_time'=>'09:00:00', 'end_time'=>'17:00:00', 'notes'=>'Turno original',
    ]]);
    $ledger = new VacationLedgerService($db);
    $take10 = $ledger->applyTake($userId, $dates10, 'request', $userId, 900001, 'Prueba FIFO', false);
    assertVacation($take10['ok'], 'Solicitud de 10 días aprobable');
    $duringVacation = $workSchedule->getPlannerEntriesForUserOnDate($userId, $dates10[0]);
    assertVacation(count($duringVacation) === 1 && $duringVacation[0]->type === 'vacation', 'La aprobación actualiza el planificador');
    $old = $balances->getPeriodByUserLabel($userId, '2025', 'annual');
    $current = $balances->getPeriodByUserLabel($userId, '2026', 'annual');
    assertVacation((float)$old->days_pending === 0.0, 'FIFO consume primero los 7 días de 2025');
    assertVacation((float)$current->days_pending === 18.0, 'FIFO consume luego 3 días de 2026');

    $reversal = $ledger->reverseRequest(900001, $userId, 'Prueba de cancelación');
    assertVacation($reversal['ok'], 'La cancelación revierte el consumo exacto');
    $restoredSchedule = $workSchedule->getPlannerEntriesForUserOnDate($userId, $dates10[0]);
    assertVacation(count($restoredSchedule) === 1 && $restoredSchedule[0]->type === 'custom'
        && $restoredSchedule[0]->notes === 'Turno original', 'La cancelación restaura el horario anterior');
    $old = $balances->getPeriodByUserLabel($userId, '2025', 'annual');
    $current = $balances->getPeriodByUserLabel($userId, '2026', 'annual');
    assertVacation((float)$old->days_pending === 7.0 && (float)$current->days_pending === 21.0,
        'La cancelación restaura 2025 y 2026 a sus saldos originales');

    $dates7 = array_slice($dates10, 0, 7);
    $db->query("INSERT INTO requests (user_id,request_type_id,start_date,end_date,reason)
        VALUES (:uid,1,:start,:end,'Prueba parcial')");
    $db->bind(':uid', $userId); $db->bind(':start', $dates7[0]); $db->bind(':end', $dates7[6]); $db->execute();
    $partialRequestId = (int)$db->lastInsertId();
    $partialRequest = (new Request($db))->getRequestByIdForUpdate($partialRequestId);
    $take7 = $ledger->applyTakeFromRequest($partialRequest, $userId, 'Excepción de anticipación para prueba');
    assertVacation($take7['ok'], 'Una solicitud parcial de 7 días es válida');
    $old = $balances->getPeriodByUserLabel($userId, '2025', 'annual');
    $current = $balances->getPeriodByUserLabel($userId, '2026', 'annual');
    assertVacation((float)$old->days_pending === 0.0 && (float)$current->days_pending === 21.0,
        'Solicitar 7 conserva intactos los 21 días del período siguiente');
    $db->query("SELECT COUNT(*) AS total FROM vacation_balance_movements
        WHERE request_id=:rid AND movement_type='exception'");
    $db->bind(':rid', $partialRequestId);
    assertVacation((int)$db->single()->total === 1, 'La excepción queda registrada en el ledger');
    $duplicate = $ledger->applyTake($userId, $dates7, 'request', $userId, $partialRequestId, 'Duplicado', true);
    assertVacation(!$duplicate['ok'], 'Un mismo request_id no puede descontarse dos veces');

    $entitlement = new VacationEntitlementService($db);
    $historical = $entitlement->addHistoricalBalance($userId, 2026, 2, $userId, 'Prueba saldo separado');
    assertVacation($historical['ok'], 'Se puede reconocer saldo histórico auditado');
    $annual2026 = $balances->getPeriodByUserLabel($userId, '2026', 'annual');
    $historical2026 = $balances->getPeriodByUserLabel($userId, '2026', 'historical');
    assertVacation($annual2026 && $historical2026 && (int)$annual2026->id !== (int)$historical2026->id,
        'Saldo anual e histórico del mismo año permanecen separados');
    $credit = $entitlement->addConventionalCredit($userId, 2026, 3, '2026-01-31', $userId, 'Prueba UTEDYC');
    assertVacation($credit['ok'], 'Se registra un crédito convencional con vencimiento');
    $expiry = $ledger->expireConventionalCredits($userId, $userId);
    assertVacation($expiry['ok'] && $expiry['expired'] === 1, 'El vencimiento se registra una sola vez y cierra el crédito');

    $soecraDates = vacation_dates_in_range('2026-08-15', '2026-08-16', 'business_mon_sat', 0, $db);
    assertVacation($soecraDates === ['2026-08-15'], 'SOECRA cuenta sábado y excluye domingo');

    $db->query("DELETE FROM vacation_balance_movements WHERE user_id=:uid");
    $db->bind(':uid', $userId); $db->execute();
    $db->query("DELETE FROM vacation_balance_periods WHERE user_id=:uid");
    $db->bind(':uid', $userId); $db->execute();
    $db->query("SELECT id FROM collective_agreements WHERE code='SOECRA-761-19'");
    $soecra = $db->single();
    $db->query("UPDATE users SET agreement_id=:aid WHERE id=:uid");
    $db->bind(':aid', (int)$soecra->id); $db->bind(':uid', $userId); $db->execute();
    $soecraPeriod = $balances->createPeriod([
        'user_id'=>$userId, 'period_label'=>'2026', 'period_start'=>'2026-01-01', 'period_end'=>'2026-12-31',
        'balance_type'=>'annual', 'agreement_id'=>(int)$soecra->id, 'count_mode_snapshot'=>'business_mon_sat',
        'days_entitled'=>21, 'days_taken'=>0, 'liquidated_by'=>$userId,
    ]);
    assertVacation($soecraPeriod > 0, 'Se crea saldo SOECRA de 21 días hábiles');
    $debugUser = (new User($db))->getUserById($userId);
    assertVacation((bool)$entitlement->getEffectiveAgreement($debugUser), 'El empleado resuelve convenio SOECRA');
    assertVacation((bool)$entitlement->getApplicableRule($userId, '2027-03-01'), 'El empleado resuelve regla SOECRA');
    $ledger = new VacationLedgerService($db);

    $companyId = (int)(new User($db))->getUserById($userId)->company_id;
    $findEnd = function($start, $wanted) use ($companyId, $db) {
        $end = $start;
        while (count(vacation_dates_in_range($start, $end, 'business_mon_sat', $companyId, $db)) < $wanted) {
            $end = date('Y-m-d', strtotime($end . ' +1 day'));
        }
        return $end;
    };
    $initial7 = $ledger->previewRequest((object)['user_id'=>$userId, 'start_date'=>'2027-03-01', 'end_date'=>$findEnd('2027-03-01', 7)]);
    assertVacation($initial7['ok'] && $initial7['requires_override'], 'SOECRA: un tramo inicial de 7 requiere excepción');
    $end14 = $findEnd('2027-03-01', 14);
    $valid14 = $ledger->previewRequest((object)['user_id'=>$userId, 'start_date'=>'2027-03-01', 'end_date'=>$end14]);
    assertVacation($valid14['ok'] && !$valid14['requires_override'], 'SOECRA: el primer tramo de 14 es válido');
    $taken14 = $ledger->applyTake($userId, $valid14['counted_dates'], 'request', $userId, 900003, 'SOECRA 14', true);
    assertVacation($taken14['ok'], 'SOECRA descuenta el tramo de 14');
    $remaining7 = $ledger->previewRequest((object)['user_id'=>$userId, 'start_date'=>'2027-04-01', 'end_date'=>$findEnd('2027-04-01', 7)]);
    assertVacation($remaining7['ok'] && !$remaining7['requires_override'], 'SOECRA: el remanente final de 7 es válido');

    $report = $balances->getPendingReport([
        'company_id'=>0, 'agreement_id'=>(int)$soecra->id, 'area_id'=>0, 'active'=>'all',
        'balance_status'=>'with', 'sort'=>'pending_desc', 'page'=>1, 'per_page'=>50,
    ]);
    assertVacation($report['total'] >= 1, 'El reporte agregado filtra por convenio');
    assertVacation((int)$report['rows'][0]->user_id === $userId, 'El reporte ordena por mayor saldo');

    $db->rollBack();
    echo "\nTodas las pruebas pasaron; la transacción fue revertida.\n";
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, "FALLO: " . $e->getMessage() . "\n");
    exit(1);
}
