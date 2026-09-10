<?php
$user = $data['user'];
$goce = $data['goce'];
$agreement = $data['agreement'];
$periods = $data['summary']['periods'] ?? [];
$pending = $data['summary']['total_pending'] ?? 0;
$isGoce = $data['kind'] === 'goce';
$title = $isGoce ? 'Comunicación de vacaciones' : 'Planilla de vacaciones';
$pdfUrl = $data['is_staff']
    ? vacation_planilla_staff_url((int)$user->id, $goce ? (int)$goce['request_id'] : 0, true)
    : vacation_planilla_employee_url($goce ? (int)$goce['request_id'] : 0, true);
$hireLabel = !empty($user->hire_date) ? date('d/m/Y', strtotime($user->hire_date)) : '—';
$doc = trim((string)($user->document_number ?? ''));
$cuil = trim((string)($user->cuil ?? ''));
$statusLabels = ['Pendiente' => 'Pendiente de aprobación', 'Aprobado' => 'Aprobado / a gozar', 'Rechazado' => 'Rechazado'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> — <?php echo htmlspecialchars($user->full_name); ?></title>
    <style>
        :root { --brand: <?php echo htmlspecialchars($data['brand_color']); ?>; }
        * { box-sizing: border-box; }
        body { font-family: Georgia, "Times New Roman", serif; color: #111; margin: 1.25rem; background: #f3f4f6; }
        .sheet { max-width: 800px; margin: 0 auto; background: #fff; border: 2px solid #222; padding: 1.75rem 2rem 2rem; }
        .toolbar { max-width: 800px; margin: 0 auto 1rem; display: flex; flex-wrap: wrap; gap: .5rem; justify-content: flex-end; }
        .toolbar a, .toolbar button {
            font-family: system-ui, sans-serif; font-size: .9rem; border: 1px solid #334155; background: #fff;
            color: #0f172a; padding: .45rem .8rem; border-radius: 6px; cursor: pointer; text-decoration: none;
        }
        .toolbar .is-primary { background: var(--brand); color: #fff; border-color: var(--brand); }
        .head { display: flex; align-items: center; gap: 1rem; border-bottom: 2px solid var(--brand); padding-bottom: .85rem; margin-bottom: 1.1rem; }
        .head img { max-height: 56px; max-width: 120px; object-fit: contain; }
        .head h1 { font-size: 1.2rem; margin: 0 0 .2rem; letter-spacing: .03em; text-transform: uppercase; }
        .head p { margin: 0; font-size: .85rem; color: #444; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: .35rem 1.5rem; font-size: .92rem; margin-bottom: 1.1rem; }
        .meta div { border-bottom: 1px dotted #ccc; padding: .2rem 0; }
        .section-title { font-size: .8rem; letter-spacing: .08em; text-transform: uppercase; margin: 1.1rem 0 .45rem; color: #333; }
        table { width: 100%; border-collapse: collapse; font-size: .88rem; }
        th, td { border: 1px solid #333; padding: .4rem .5rem; text-align: left; }
        th { background: #f4f4f4; font-weight: 600; }
        .num { text-align: right; }
        .body-text { font-size: .98rem; line-height: 1.55; text-align: justify; margin: .75rem 0 0; }
        .fill-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem 1.25rem; margin-top: .6rem; font-size: .92rem; }
        .fill-line { border-bottom: 1px solid #333; min-height: 1.6rem; }
        .hint { font-size: .78rem; color: #555; margin-top: .35rem; }
        .signatures { display: flex; gap: 2rem; margin-top: 2.6rem; }
        .sign { flex: 1; text-align: center; font-size: .82rem; }
        .sign-space { border-bottom: 1px solid #333; height: 3.2rem; margin-bottom: .4rem; }
        .legal { font-size: .78rem; color: #444; margin-top: 1.2rem; }
        .foot { margin-top: 1.4rem; font-size: .75rem; color: #666; border-top: 1px solid #ddd; padding-top: .5rem; }
        @media print {
            body { background: #fff; margin: 0; }
            .toolbar { display: none !important; }
            .sheet { border-width: 0; max-width: none; padding: 0; }
        }
        @media (max-width: 640px) {
            .meta, .fill-grid, .signatures { grid-template-columns: 1fr; display: grid; }
            .signatures { display: grid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="is-primary" onclick="window.print()">Imprimir / Guardar PDF</button>
        <a href="<?php echo htmlspecialchars($pdfUrl); ?>">Descargar PDF</a>
        <button type="button" onclick="window.close()">Cerrar</button>
    </div>

    <article class="sheet">
        <header class="head">
            <?php if (!empty($data['logo_url'])): ?>
            <img src="<?php echo htmlspecialchars($data['logo_url']); ?>" alt="">
            <?php endif; ?>
            <div>
                <h1><?php echo htmlspecialchars($title); ?></h1>
                <p><?php echo htmlspecialchars($data['company_name']); ?>
                    · <?php echo $isGoce ? 'Período de goce' : 'Liquidación / saldo'; ?></p>
            </div>
        </header>

        <h2 class="section-title">Datos del trabajador</h2>
        <div class="meta">
            <div><strong>Apellido y nombre:</strong> <?php echo htmlspecialchars($user->full_name); ?></div>
            <div><strong>Documento:</strong> <?php echo htmlspecialchars($doc !== '' ? $doc : '—'); ?></div>
            <div><strong>CUIL:</strong> <?php echo htmlspecialchars($cuil !== '' ? $cuil : '—'); ?></div>
            <div><strong>Área:</strong> <?php echo htmlspecialchars($data['area_name'] !== '' ? $data['area_name'] : '—'); ?></div>
            <div><strong>Ingreso formal:</strong> <?php echo htmlspecialchars($hireLabel); ?></div>
            <div><strong>Convenio:</strong>
                <?php if ($agreement): ?>
                    <?php echo htmlspecialchars($agreement->code . ' — ' . $agreement->name); ?>
                <?php else: ?>
                    Sin convenio efectivo
                <?php endif; ?>
            </div>
        </div>

        <h2 class="section-title">Saldo de vacaciones</h2>
        <table>
            <thead>
                <tr>
                    <th>Período</th>
                    <th>Tipo</th>
                    <th class="num">Corresponden</th>
                    <th class="num">Tomados</th>
                    <th class="num">Pendientes</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$periods): ?>
                <tr><td colspan="5">Sin períodos liquidados. Completar a mano si corresponde.</td></tr>
            <?php else: foreach ($periods as $period): ?>
                <tr>
                    <td><?php echo htmlspecialchars($period->period_label); ?></td>
                    <td><?php echo htmlspecialchars($data['type_labels'][$period->balance_type ?? 'annual'] ?? ($period->balance_type ?? '')); ?></td>
                    <td class="num"><?php echo vacation_format_days($period->days_entitled); ?></td>
                    <td class="num"><?php echo vacation_format_days($period->days_taken); ?></td>
                    <td class="num"><strong><?php echo vacation_format_days(vacation_period_pending($period)); ?></strong></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <p class="hint">
            Pendiente total: <strong><?php echo vacation_format_days($pending); ?></strong> días
            · Cómputo: <?php echo htmlspecialchars($data['mode_label']); ?>.
            El consumo es FIFO (período abierto más antiguo primero).
        </p>

        <?php if ($isGoce): ?>
        <h2 class="section-title">Comunicación de goce</h2>
        <p class="body-text">
            Se comunica que <strong><?php echo htmlspecialchars(mb_strtoupper($user->full_name, 'UTF-8')); ?></strong>
            gozará vacaciones desde el
            <strong><?php echo date('d/m/Y', strtotime($goce['start_date'])); ?></strong>
            hasta el
            <strong><?php echo date('d/m/Y', strtotime($goce['end_date'])); ?></strong>,
            computándose <strong><?php echo vacation_format_days($goce['days']); ?></strong> día(s)
            según <?php echo htmlspecialchars($data['mode_label']); ?>.
            Estado: <strong><?php echo htmlspecialchars($statusLabels[$goce['status']] ?? $goce['status']); ?></strong>.
        </p>
        <?php if (!empty($goce['allocations'])): ?>
        <p class="hint">Imputación FIFO:
            <?php
            $bits = [];
            foreach ($goce['allocations'] as $alloc) {
                $bits[] = ($alloc['period_label'] ?? '') . ': ' . vacation_format_days($alloc['days'] ?? 0);
            }
            echo htmlspecialchars(implode(' · ', $bits));
            ?>
        </p>
        <?php endif; ?>
        <?php if (!empty($goce['reason'])): ?>
        <p class="hint">Observación del empleado: <?php echo htmlspecialchars($goce['reason']); ?></p>
        <?php endif; ?>
        <?php else: ?>
        <h2 class="section-title">Período de goce (completar)</h2>
        <p class="body-text">
            El saldo precedente queda reconocido en el sistema. Las fechas de goce se completan aquí
            o mediante la solicitud del empleado.
        </p>
        <div class="fill-grid">
            <div>Desde:<div class="fill-line"></div></div>
            <div>Hasta:<div class="fill-line"></div></div>
            <div>Días computables:<div class="fill-line"></div></div>
            <div>Reintegro el:<div class="fill-line"></div></div>
        </div>
        <?php endif; ?>

        <p class="legal">
            El presente documento es informativo de días de vacaciones. No liquida haberes ni sustituye
            el recibo de sueldo. La firma manuscrita acredita conformidad con el saldo y, si corresponde,
            con el período de goce indicado. No constituye firma digital.
        </p>

        <div class="signatures">
            <div class="sign">
                <div class="sign-space"></div>
                Conformidad del empleado<br>Firma, aclaración y DNI
            </div>
            <div class="sign">
                <div class="sign-space"></div>
                Empleador / RR. HH.<br>Firma y sello
            </div>
        </div>

        <p class="foot">
            Emitido el <?php echo htmlspecialchars($data['printed_at']); ?>
            · Documento interno de RR. HH.
            <?php if ($isGoce): ?> · Solicitud #<?php echo (int)$goce['request_id']; ?><?php endif; ?>
        </p>
    </article>
</body>
</html>
