<?php // Selector de organización (solo cuando hay más de un portal habilitado). ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Trabajá con nosotros · Suite P&M</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f4f6f9}
.org-card{border:0;border-radius:1rem;box-shadow:0 2px 14px rgba(15,23,42,.08);transition:transform .15s ease}
.org-card:hover{transform:translateY(-3px)}
.org-card img{height:56px;width:auto}
</style>
</head>
<body>
<main class="container py-5" style="max-width:860px">
  <header class="text-center mb-5">
    <h1 class="display-5 fw-bold">Trabajá con nosotros</h1>
    <p class="lead text-secondary">Elegí la organización para ver sus búsquedas abiertas.</p>
  </header>
  <div class="row g-4 justify-content-center">
    <?php foreach ($d['groups'] as $g): $b = org_careers_brand($g); ?>
    <div class="col-md-5">
      <a class="card org-card h-100 text-decoration-none" href="<?= URLROOT ?>/careers/<?= $g ?>">
        <div class="card-body p-4 text-center">
          <img src="<?= htmlspecialchars($b['logo']) ?>" alt="" class="mb-3">
          <h2 class="h5 mb-1" style="color:<?= $b['dark'] ?>"><?= htmlspecialchars($b['name']) ?></h2>
          <span class="small text-secondary">Ver búsquedas abiertas</span>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</main>
</body>
</html>
