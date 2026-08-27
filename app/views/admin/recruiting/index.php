<?php
require APPROOT . '/views/inc/header.php';
$_v = $d['vacancy'];
$_apps = $d['applications'];
$_f = $d['filters'];
$_k = $d['kpis'];
$_statusLabels = ['draft' => 'Borrador', 'published' => 'Publicada', 'paused' => 'Pausada', 'closed' => 'Cerrada'];
$_statusBadges = ['draft' => 'text-bg-secondary', 'published' => 'text-bg-success', 'paused' => 'text-bg-warning', 'closed' => 'text-bg-dark'];
$_vStages = $_v ? (json_decode($_v->pipeline_json ?? '', true) ?: org_default_pipeline()) : org_default_pipeline();
$_estadoOpts = $d['estado_opts'] ?? org_default_pipeline();
$_qsArr = array_filter(['q' => $_f['q'], 'estado' => $_f['estado'], 'vacante_id' => $_f['vacante_id'] ?: '', 'desde' => $_f['desde'], 'hasta' => $_f['hasta'], 'vacancy_id' => $d['selected'] ?: ''], fn($x) => $x !== '' && $x !== 0);
$_backQs = htmlspecialchars(http_build_query(array_merge($_qsArr, ['page' => $_apps['page']])));
$_qs = fn(array $extra) => htmlspecialchars(http_build_query(array_merge($_qsArr, $extra)));
?>
<div class="app-content"><div class="container-fluid py-4">
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h1 class="h3 mb-1">Reclutamiento</h1>
    <p class="text-secondary mb-0">Candidatos y búsquedas de toda la organización · ranking asistido con revisión humana.</p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-primary" href="<?= URLROOT ?>/careers/<?= htmlspecialchars($d['org']) ?>" target="_blank">Portal público</a>
    <a class="btn btn-outline-primary" href="<?= URLROOT ?>/careers/unete/<?= htmlspecialchars($d['org']) ?>" target="_blank">Formulario Unite</a>
  </div>
</div>
<?php if (!empty($_SESSION['flash_error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div><?php endif; ?>
<?php if (!empty($_SESSION['flash_success'])): ?><div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div><?php endif; ?>

<div class="row g-3 mb-4">
  <?php foreach ([['Búsquedas abiertas', $_k['abiertas']], ['Postulaciones', $_k['total']], ['Nuevos', $_k['nuevos']], ['En entrevista', $_k['entrevista']], ['Contratados', $_k['contratados']]] as [$kl, $kv]): ?>
  <div class="col-6 col-md"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
    <div class="fs-3 fw-bold"><?= (int)$kv ?></div>
    <div class="small text-secondary"><?= $kl ?></div>
  </div></div></div>
  <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <h2 class="h5 mb-0">Candidatos <span class="badge text-bg-light"><?= (int)$_apps['total'] ?></span></h2>
      <form class="d-flex flex-wrap gap-2" method="get" action="<?= URLROOT ?>/recruiting/index">
        <?php if ($d['selected']): ?><input type="hidden" name="vacancy_id" value="<?= (int)$d['selected'] ?>"><?php endif; ?>
        <input class="form-control form-control-sm" style="width:180px" name="q" placeholder="Nombre o email" value="<?= htmlspecialchars($_f['q']) ?>">
        <select class="form-select form-select-sm" style="width:140px" name="estado">
          <option value="">Todo estado</option>
          <?php foreach ($_estadoOpts as $s): ?><option value="<?= htmlspecialchars($s) ?>" <?= $_f['estado'] === $s ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($s)) ?></option><?php endforeach; ?>
        </select>
        <select class="form-select form-select-sm" style="width:200px" name="vacante_id">
          <option value="0">Toda búsqueda</option>
          <?php foreach ($d['vacancies'] as $vv): ?><option value="<?= (int)$vv->id ?>" <?= (int)$_f['vacante_id'] === (int)$vv->id ? 'selected' : '' ?>><?= htmlspecialchars(mb_strimwidth($vv->title, 0, 34, '…')) ?></option><?php endforeach; ?>
        </select>
        <input class="form-control form-control-sm" style="width:140px" type="date" name="desde" value="<?= htmlspecialchars($_f['desde']) ?>" title="Desde">
        <input class="form-control form-control-sm" style="width:140px" type="date" name="hasta" value="<?= htmlspecialchars($_f['hasta']) ?>" title="Hasta">
        <button class="btn btn-sm btn-outline-primary">Filtrar</button>
        <a class="btn btn-sm btn-outline-secondary" href="<?= URLROOT ?>/recruiting/index">Limpiar</a>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead><tr><th>Candidato</th><th>Búsqueda</th><th>Fecha</th><th>Estado</th><th>IA</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($_apps['rows'] as $a):
          $rowStages = json_decode($a->pipeline_json ?? '', true) ?: org_default_pipeline();
          $orphan = !in_array($a->current_stage, $rowStages, true);
          $result = json_decode($a->ai_result_json ?? '', true);
          $esEspontanea = strpos($a->vacancy_slug, 'espontanea-') === 0; ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($a->full_name) ?></strong>
            <small class="d-block text-secondary"><?= htmlspecialchars($a->email) ?></small>
          </td>
          <td>
            <?= $esEspontanea ? '<span class="badge text-bg-info">Espontánea</span>' : htmlspecialchars(mb_strimwidth($a->vacancy_title, 0, 32, '…')) ?>
          </td>
          <td class="text-nowrap"><small><?= date('d/m/Y', strtotime($a->created_at)) ?></small></td>
          <td>
            <form method="post" action="<?= URLROOT ?>/recruiting/move/<?= (int)$a->id ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="back_qs" value="<?= $_backQs ?>">
              <select class="form-select form-select-sm" name="stage" style="min-width:130px" onchange="this.form.submit()">
                <?php if ($orphan): ?><option value="<?= htmlspecialchars($a->current_stage) ?>" selected><?= htmlspecialchars(ucfirst($a->current_stage)) ?> (fuera del pipeline)</option><?php endif; ?>
                <?php foreach ($rowStages as $s): ?><option value="<?= htmlspecialchars($s) ?>" <?= $a->current_stage === $s ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($s)) ?></option><?php endforeach; ?>
              </select>
            </form>
          </td>
          <td><?= $a->ai_score !== null ? '<strong>' . number_format($a->ai_score, 1) . '</strong>' : '—' ?><?php if ($result): ?><small class="d-block text-secondary">Con evidencia</small><?php endif; ?></td>
          <td>
            <div class="d-flex flex-wrap gap-1">
              <button class="btn btn-sm btn-outline-secondary" type="button" onclick="rcFicha(<?= (int)$a->id ?>)">Ficha</button>
              <a class="btn btn-sm btn-outline-secondary" href="<?= URLROOT ?>/recruiting/downloadCv/<?= (int)$a->id ?>">CV</a>
              <form method="post" action="<?= URLROOT ?>/recruiting/score/<?= (int)$a->id ?>"><?= csrf_field() ?><input type="hidden" name="back_qs" value="<?= $_backQs ?>"><button class="btn btn-sm btn-outline-primary">IA</button></form>
              <form method="post" action="<?= URLROOT ?>/recruiting/onboard/<?= (int)$a->id ?>" onsubmit="return confirm('¿Crear preingreso?')"><?= csrf_field() ?><input type="hidden" name="back_qs" value="<?= $_backQs ?>"><button class="btn btn-sm btn-success">Preingreso</button></form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$_apps['rows']): ?><tr><td colspan="6" class="text-secondary">Sin postulaciones<?= array_filter($_f) ? ' con esos filtros' : ' todavía' ?>.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($_apps['pages'] > 1): ?>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for ($p = 1; $p <= $_apps['pages']; $p++): ?>
      <li class="page-item <?= $p === $_apps['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= URLROOT ?>/recruiting/index?<?= $_qs(['page' => $p]) ?>"><?= $p ?></a></li>
      <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
  </div>
</div>

<div class="row g-4">
<section class="col-xl-4">
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <?php if ($_v): ?>
      <div class="d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Editar búsqueda</h2>
        <a class="small" href="<?= URLROOT ?>/recruiting/index">+ Nueva</a>
      </div>
      <p class="small text-secondary mb-3">
        <?= htmlspecialchars($_v->company_name ?? '') ?>
        <?php if ($_v->status === 'published'): ?> · <a href="<?= URLROOT ?>/careers/vacancy/<?= rawurlencode($_v->slug) ?>" target="_blank">ver en el portal</a><?php endif; ?>
      </p>
      <?php else: ?>
      <h2 class="h5 mb-3">Nueva búsqueda</h2>
      <?php endif; ?>
      <form method="post" action="<?= URLROOT ?>/recruiting/<?= $_v ? 'updateVacancy/' . (int)$_v->id : 'saveVacancy' ?>">
        <?= csrf_field() ?>
        <?php if (!$_v): ?>
        <label class="form-label">Sociedad</label>
        <select class="form-select mb-3" name="company_id">
          <?php $_activa = function_exists('adminCompanyId') ? adminCompanyId() : 0; ?>
          <?php foreach ($d['companies'] as $c): ?><option value="<?= (int)$c->id ?>" <?= (int)$c->id === $_activa ? 'selected' : '' ?>><?= htmlspecialchars($c->name) ?></option><?php endforeach; ?>
        </select>
        <?php endif; ?>
        <label class="form-label">Título</label>
        <input class="form-control mb-3" name="title" required value="<?= htmlspecialchars($_v->title ?? '') ?>">
        <label class="form-label">Descripción pública</label>
        <textarea class="form-control mb-3" rows="4" name="description" required><?= htmlspecialchars($_v->description ?? '') ?></textarea>
        <label class="form-label">Criterios publicados (uno por línea)</label>
        <textarea class="form-control mb-3" rows="3" name="criteria"><?= htmlspecialchars($_v ? implode("\n", json_decode($_v->requirements_json ?? '', true) ?: []) : '') ?></textarea>
        <label class="form-label">Etapas (una por línea)</label>
        <textarea class="form-control mb-3" rows="4" name="pipeline"><?= htmlspecialchars($_v ? implode("\n", $_vStages) : implode("\n", org_default_pipeline())) ?></textarea>
        <?php if ($_v): ?>
        <div class="row g-2">
          <div class="col-6">
            <label class="form-label">Sucursal</label>
            <select class="form-select mb-3" name="branch_id">
              <option value="0">— Sin especificar</option>
              <?php $branchIds = array_map(fn($b) => (int)$b->id, $d['branches']);
              if ((int)$_v->branch_id && !in_array((int)$_v->branch_id, $branchIds, true)): ?>
              <option value="<?= (int)$_v->branch_id ?>" selected>Sucursal actual (inactiva)</option>
              <?php endif; ?>
              <?php foreach ($d['branches'] as $b): ?><option value="<?= (int)$b->id ?>" <?= (int)$_v->branch_id === (int)$b->id ? 'selected' : '' ?>><?= htmlspecialchars($b->name) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Puesto</label>
            <select class="form-select mb-3" name="position_id">
              <option value="0">— Sin especificar</option>
              <?php foreach ($d['positions'] as $p): ?><option value="<?= (int)$p->id ?>" <?= (int)$_v->position_id === (int)$p->id ? 'selected' : '' ?>><?= htmlspecialchars($p->name) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <?php endif; ?>
        <label class="form-label">Cierre de postulaciones</label>
        <input type="date" class="form-control mb-3" name="closes_at" value="<?= htmlspecialchars($_v && $_v->closes_at ? substr($_v->closes_at, 0, 10) : '') ?>">
        <label class="form-label">Estado</label>
        <select class="form-select mb-3" name="status">
          <?php foreach ($_v ? $_statusLabels : ['draft' => 'Borrador', 'published' => 'Publicar'] as $sv => $sl): ?>
          <option value="<?= $sv ?>" <?= $_v && $_v->status === $sv ? 'selected' : '' ?>><?= $sl ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary w-100"><?= $_v ? 'Guardar cambios' : 'Crear búsqueda' ?></button>
      </form>
    </div>
  </div>
</section>
<section class="col-xl-8">
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <h2 class="h5">Búsquedas</h2>
      <div class="list-group list-group-flush">
        <?php foreach ($d['vacancies'] as $v): ?>
        <a class="list-group-item list-group-item-action d-flex justify-content-between <?= (int)$d['selected'] === (int)$v->id ? 'active' : '' ?>" href="<?= URLROOT ?>/recruiting/index?vacancy_id=<?= (int)$v->id ?>">
          <span><?= strpos($v->slug, 'espontanea-') === 0 ? 'Postulación espontánea' : htmlspecialchars($v->title) ?>
            <small class="d-block opacity-75"><?= htmlspecialchars($v->company_name) ?><?= $v->branch_name ? ' · ' . htmlspecialchars($v->branch_name) : '' ?></small>
          </span>
          <span class="align-self-center text-nowrap">
            <span class="badge <?= $_statusBadges[$v->status] ?? 'text-bg-light' ?>"><?= $_statusLabels[$v->status] ?? htmlspecialchars($v->status) ?></span>
            <span class="badge text-bg-light"><?= (int)$v->application_count ?></span>
          </span>
        </a>
        <?php endforeach; ?>
        <?php if (!$d['vacancies']): ?><p class="text-secondary mb-0">Todavía no hay búsquedas. Creá la primera con el formulario.</p><?php endif; ?>
      </div>
    </div>
  </div>
</section>
</div>
</div></div>
<!-- Ficha del candidato -->
<div class="modal fade" id="rcFichaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h3 class="modal-title h5">Ficha del candidato</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
    <div class="modal-body" id="rcFichaBody"><p class="text-secondary">Cargando…</p></div>
  </div></div>
</div>
<script>
function rcFicha(id){
  var modal=new bootstrap.Modal(document.getElementById('rcFichaModal'));
  var body=document.getElementById('rcFichaBody');
  body.innerHTML='<p class="text-secondary">Cargando…</p>';
  modal.show();
  fetch('<?= URLROOT ?>/recruiting/candidate/'+id,{headers:{'Accept':'application/json'}})
    .then(function(r){return r.json();})
    .then(function(d){
      if(!d.ok){body.innerHTML='<p class="text-danger">No se pudo cargar la ficha.</p>';return;}
      var esc=function(s){var e=document.createElement('span');e.textContent=s==null?'':String(s);return e.innerHTML;};
      var h='<dl class="row mb-3">'
        +'<dt class="col-sm-3">Nombre</dt><dd class="col-sm-9">'+esc(d.candidate.full_name)+'</dd>'
        +'<dt class="col-sm-3">Email</dt><dd class="col-sm-9">'+esc(d.candidate.email)+'</dd>'
        +'<dt class="col-sm-3">Teléfono</dt><dd class="col-sm-9">'+esc(d.candidate.phone||'—')+'</dd>'
        +'<dt class="col-sm-3">CV</dt><dd class="col-sm-9">'+esc(d.application.cv_original_name)+'</dd>'
        +'<dt class="col-sm-3">Estado</dt><dd class="col-sm-9">'+esc(d.application.current_stage)+' · '+esc(d.application.status)+'</dd>'
        +'<dt class="col-sm-3">Retención</dt><dd class="col-sm-9">hasta '+esc(d.candidate.retention_until)+'</dd>'
        +'</dl>';
      if(d.application.ai_score!==null&&d.application.ai_result){
        var ai=d.application.ai_result;
        h+='<h4 class="h6">Análisis IA <span class="badge text-bg-primary">'+esc(d.application.ai_score)+'</span> <small class="text-secondary fw-normal">'+esc(d.application.ai_model||'')+' · revisión humana obligatoria</small></h4>';
        if(ai.summary)h+='<p class="small">'+esc(ai.summary)+'</p>';
        if(ai.matches&&ai.matches.length)h+='<p class="small mb-1 text-success">Coincide: '+ai.matches.map(esc).join(' · ')+'</p>';
        if(ai.gaps&&ai.gaps.length)h+='<p class="small text-danger">Faltantes: '+ai.gaps.map(esc).join(' · ')+'</p>';
      }
      if(d.other_applications&&d.other_applications.length){
        h+='<h4 class="h6 mt-3">Otras postulaciones del candidato</h4><ul class="small">';
        d.other_applications.forEach(function(o){h+='<li>'+esc(o.title)+' — '+esc(o.current_stage)+' ('+esc(o.status)+')</li>';});
        h+='</ul>';
      }
      h+='<h4 class="h6 mt-3">Historial</h4>';
      if(d.events.length){
        h+='<ul class="list-unstyled small mb-0">';
        d.events.forEach(function(e){h+='<li class="mb-1"><strong>'+(e.from_stage?esc(e.from_stage)+' → ':'')+esc(e.to_stage||e.event_type)+'</strong> · '+esc(e.created_at)+(e.actor?' · '+esc(e.actor):'')+(e.notes?'<span class="d-block text-secondary">'+esc(e.notes)+'</span>':'')+'</li>';});
        h+='</ul>';
      }else{h+='<p class="small text-secondary mb-0">Sin movimientos todavía.</p>';}
      body.innerHTML=h;
    })
    .catch(function(){body.innerHTML='<p class="text-danger">No se pudo cargar la ficha.</p>';});
}
</script>
<?php require APPROOT . '/views/inc/footer.php'; ?>
