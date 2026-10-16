<?php
require_once 'config.php';

// Kalau sudah login, langsung ke dashboard (perilaku sama seperti index.php lama)
if (isset($_SESSION['admin_id']))   redirect('/admin/dashboard.php');
if (isset($_SESSION['peserta_id'])) redirect('/peserta/dashboard.php');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BBU Tryout — Bharuna Bhakti Utama</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--deep:#0E3A5F;--sea:#145A8C;--foam:#8FD4F0;--amber:#F5A623;--ink:#0E2A3F;--muted:#5B6B7C;--paper:#F7FAFC}
body{font-family:'Inter','Segoe UI',system-ui,sans-serif;background:var(--paper);color:var(--ink);line-height:1.6;min-height:100vh;display:flex;flex-direction:column}

.hero{position:relative;overflow:hidden;background:linear-gradient(180deg,var(--deep),var(--sea));color:#fff;text-align:center;padding:56px 24px 110px}
/* cincin tipis ala gelombang radio di pojok kanan atas */
.hero::before{content:"";position:absolute;top:-180px;right:-180px;width:520px;height:520px;border-radius:50%;pointer-events:none;
  box-shadow:0 0 0 60px rgba(143,212,240,.06),0 0 0 120px rgba(143,212,240,.045),0 0 0 180px rgba(143,212,240,.03)}
.hero-inner{position:relative;max-width:560px;margin:0 auto}

.logo{display:inline-block;background:#fff;border-radius:14px;padding:10px 14px;margin-bottom:28px;box-shadow:0 6px 20px rgba(0,0,0,.18)}
.logo img{display:block;width:150px;height:auto}

.kicker{color:var(--foam);font-size:13px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;margin-bottom:12px}
h1{font-size:clamp(26px,5vw,36px);line-height:1.25;font-weight:700;letter-spacing:-.01em;margin-bottom:14px}
.sub{color:rgba(255,255,255,.8);font-size:17px;max-width:440px;margin:0 auto 34px}

.cta{display:inline-flex;align-items:center;gap:12px;background:var(--amber);color:#2D1B00;text-decoration:none;font-size:19px;font-weight:700;padding:17px 40px;border-radius:12px;box-shadow:0 6px 18px rgba(245,166,35,.35);transition:transform .15s,box-shadow .15s}
.cta:hover{transform:translateY(-1px);box-shadow:0 8px 22px rgba(245,166,35,.45)}
.cta:focus-visible{outline:3px solid #fff;outline-offset:3px}
.cta svg{width:20px;height:20px}
.hint{margin-top:16px;color:rgba(255,255,255,.7);font-size:14px}

.wave{position:absolute;left:0;right:0;bottom:-1px;line-height:0}
.wave svg{display:block;width:100%;height:64px}

.features{flex:1;max-width:880px;width:100%;margin:0 auto;padding:44px 24px 56px;display:grid;grid-template-columns:repeat(3,1fr);gap:28px}
.feature{text-align:center}
.icon{width:48px;height:48px;border-radius:12px;background:#E3F1FA;color:var(--sea);display:flex;align-items:center;justify-content:center;margin:0 auto 14px}
.icon svg{width:24px;height:24px}
.feature h2{font-size:16px;font-weight:700;margin-bottom:6px}
.feature p{font-size:14px;color:var(--muted)}

footer{border-top:1px solid #E2E8F0;text-align:center;padding:18px 24px;font-size:13px;color:#8A97A6}

@media(max-width:640px){
  .hero{padding:40px 20px 96px}
  .features{grid-template-columns:1fr;gap:24px}
  .cta{width:100%;justify-content:center}
}
@media(prefers-reduced-motion:reduce){.cta{transition:none}}
</style>
</head>
<body>

<header class="hero">
  <div class="hero-inner">
    <div class="logo">
      <img src="<?= url('/assets/img/logo-bbu.jpeg') ?>" alt="Logo Bharuna Bhakti Utama">
    </div>
    <p class="kicker">Computer Assisted Test</p>
    <h1>Persiapan ujian sertifikasi radio maritim</h1>
    <p class="sub">Tryout resmi untuk program SOU dan COC GMDSS, dikerjakan langsung di kantor pelatihan.</p>

    <a class="cta" href="<?= url('/login.php') ?>">
      Mulai Tryout
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </a>
    <p class="hint">Siapkan username dan password dari pengawas ujian.</p>
  </div>

  <div class="wave" aria-hidden="true">
    <svg viewBox="0 0 1440 64" preserveAspectRatio="none"><path d="M0,32 C240,64 480,0 720,28 C960,56 1200,8 1440,36 L1440,64 L0,64 Z" fill="#F7FAFC"/></svg>
  </div>
</header>

<main class="features">
  <section class="feature">
    <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8.5 9l1.5 1.5L13 7.5M8.5 15h7"/></svg></div>
    <h2>Soal sesuai materi</h2>
    <p>Soal disusun dari bank soal diklat SOU dan GMDSS.</p>
  </section>
  <section class="feature">
    <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></div>
    <h2>Review jawaban</h2>
    <p>Setelah selesai, lihat jawaban Anda beserta kunci jawabannya.</p>
  </section>
  <section class="feature">
    <div class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 9a15 15 0 0 1 20 0M5.5 12.5a10 10 0 0 1 13 0M9 16a5 5 0 0 1 6 0"/><circle cx="12" cy="19.5" r="1" fill="currentColor"/></svg></div>
    <h2>Stabil di jaringan lokal</h2>
    <p>Berjalan di jaringan kantor tanpa bergantung pada internet.</p>
  </section>
</main>

<footer>LKP Bharuna Bhakti Utama · Surabaya</footer>

</body>
</html>