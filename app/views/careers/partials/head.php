<?php
/**
 * Cabecera compartida del portal público de vacantes.
 * Espera: $d['brand'] (org_careers_brand) y opcionalmente $d['org'].
 * $titulo lo define cada vista antes de incluir este partial.
 */
$_b = $d['brand'];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($titulo ?? ('Trabajá con nosotros · ' . $_b['name'])) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{--cp:<?= $_b['primary'] ?>;--cpd:<?= $_b['dark'] ?>;--cpa:<?= $_b['accent'] ?>}
body{background:#f4f6f9}
.careers-top{background:linear-gradient(135deg,var(--cpd),var(--cp));color:#fff}
.careers-top .navbar-brand{color:#fff;font-weight:700;display:flex;align-items:center;gap:.6rem}
.careers-top .navbar-brand img{height:34px;width:auto;background:#fff;border-radius:.4rem;padding:3px}
.careers-hero{padding:2.6rem 0 2.2rem}
.careers-hero .eyebrow{text-transform:uppercase;letter-spacing:.08em;font-size:.78rem;font-weight:700;opacity:.85}
.btn-brand{background:var(--cp);border-color:var(--cp);color:#fff}
.btn-brand:hover,.btn-brand:focus{background:var(--cpd);border-color:var(--cpd);color:#fff}
.text-brand{color:var(--cp)!important}
.badge-brand{background:var(--cp)}
.careers-card{border:0;border-radius:.9rem;box-shadow:0 2px 14px rgba(15,23,42,.08);transition:transform .15s ease,box-shadow .15s ease}
.careers-card:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(15,23,42,.12)}
.careers-foot{color:#64748b;font-size:.82rem}
a{color:var(--cp)}
</style>
</head>
<body>
<header class="careers-top">
  <nav class="navbar container">
    <a class="navbar-brand" href="<?= URLROOT ?>/careers<?= !empty($d['org']) ? '/' . $d['org'] : '' ?>">
      <img src="<?= htmlspecialchars($_b['logo']) ?>" alt="">
      <span><?= htmlspecialchars($_b['name']) ?></span>
    </a>
    <span class="navbar-text text-white-50 small">Trabajá con nosotros</span>
  </nav>
</header>
