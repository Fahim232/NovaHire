<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) { header('location: ' . BASE_URL . '/auth/login.php'); exit(); }
require_once __DIR__ . '/../includes/header.php';

$user_id = $_SESSION['id'];
$plan    = nh_pro_plan();
$sub     = get_user_subscription($con, $user_id);
$is_pro  = $sub !== null;
$success = isset($_GET['success']);
$pro_price = $plan['price'] ?? 499;
?>
<style>
  .pro-wrap{max-width:1060px;margin:0 auto;padding:0 20px 60px}

  .pro-hero{text-align:center;padding:48px 20px 40px;position:relative}
  .pro-hero::before{content:'';position:absolute;top:0;left:50%;transform:translateX(-50%);width:600px;height:400px;background:radial-gradient(circle,rgba(26,86,219,.08),transparent 70%);pointer-events:none}
  .pro-crown{width:76px;height:76px;border-radius:22px;background:linear-gradient(135deg,#d97706,#f97316);display:grid;place-items:center;margin:0 auto 18px;box-shadow:0 14px 36px rgba(217,119,6,.35);position:relative}
  .pro-crown i{font-size:2rem;color:#fff}
  .pro-hero h1{font-weight:800;color:var(--text);font-size:2.2rem;letter-spacing:-.5px}
  .pro-hero h1 span{background:linear-gradient(135deg,#1a56db,#0ea5e9);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
  .pro-hero>p{color:var(--text-muted);max-width:580px;margin:12px auto 0;font-size:1.05rem;line-height:1.6}
  .pro-hero .pro-stats{display:flex;justify-content:center;gap:40px;margin-top:28px}
  .pro-hero .pro-stat{text-align:center}
  .pro-hero .pro-stat strong{display:block;font-size:1.6rem;font-weight:800;color:var(--text)}
  .pro-hero .pro-stat span{font-size:.82rem;color:var(--text-muted);font-weight:600}

  .ok-banner{background:rgba(5,150,105,.12);border:1px solid rgba(5,150,105,.3);color:#047857;padding:16px 20px;border-radius:var(--radius-lg);margin-bottom:26px;display:flex;gap:12px;align-items:center;font-weight:600}

  /* ── Comparison Table ── */
  .compare-section{margin-bottom:40px}
  .compare-section h2{font-weight:800;color:var(--text);font-size:1.5rem;text-align:center;margin-bottom:6px}
  .compare-section>p{text-align:center;color:var(--text-muted);margin-bottom:28px}
  .compare-table{width:100%;border-collapse:separate;border-spacing:0;background:var(--bg-card);border:1px solid var(--border-light);border-radius:16px;overflow:hidden;box-shadow:var(--shadow-sm)}
  .compare-table th,.compare-table td{padding:14px 18px;text-align:left;font-size:.9rem}
  .compare-table thead th{background:var(--bg-hover);font-weight:700;color:var(--text);border-bottom:2px solid var(--border-light)}
  .compare-table thead th:nth-child(2){color:var(--text-muted)}
  .compare-table thead th:nth-child(3){color:#1a56db}
  .compare-table tbody tr{border-bottom:1px solid var(--border-light)}
  .compare-table tbody tr:last-child{border-bottom:none}
  .compare-table tbody td:first-child{font-weight:600;color:var(--text)}
  .compare-table tbody td:nth-child(2){color:var(--text-muted)}
  .compare-table tbody td:nth-child(3){color:#1a56db;font-weight:600}
  .compare-table .check{color:#059669}
  .compare-table .cross{color:#dc2626}
  .compare-table .unlimited{color:#1a56db;font-weight:700}

  /* ── Pricing Card ── */
  .pro-grid{display:grid;grid-template-columns:1.2fr 1fr;gap:28px;align-items:start;margin-top:40px}
  @media(max-width:820px){.pro-grid{grid-template-columns:1fr}}
  .pro-features-card{background:var(--bg-card);border:1px solid var(--border-light);border-radius:var(--radius-xl);padding:30px;box-shadow:var(--shadow-md)}
  .feat{display:flex;gap:13px;align-items:flex-start;padding:12px 0;border-bottom:1px solid var(--border-light)}
  .feat:last-child{border-bottom:none}
  .feat-icon{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;flex-shrink:0;font-size:1rem}
  .feat span{color:var(--text);font-weight:600;font-size:.92rem}
  .feat small{display:block;color:var(--text-muted);font-size:.8rem;font-weight:500;margin-top:2px}

  .price-card{background:linear-gradient(160deg,#1a56db,#0ea5e9);color:#fff;text-align:center;position:sticky;top:96px;border-radius:var(--radius-xl);overflow:hidden;box-shadow:0 20px 50px -12px rgba(26,86,219,.4)}
  .price-card-inner{padding:36px 28px}
  .price-card .amt{font-size:3.2rem;font-weight:800;line-height:1}
  .price-card .amt small{font-size:1rem;font-weight:600;opacity:.85}
  .price-card .cadence{opacity:.85;margin-bottom:24px;font-size:.92rem}
  .btn-upgrade{background:#fff;color:#1a56db;border:none;font-weight:700;padding:15px;border-radius:14px;width:100%;font-size:1.05rem;transition:.2s;text-decoration:none;display:block;text-align:center}
  .btn-upgrade:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(0,0,0,.25);color:#1e40af;text-decoration:none}
  .active-badge{background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3);padding:6px 16px;border-radius:var(--radius-full);font-weight:700;font-size:.85rem;display:inline-flex;gap:7px;align-items:center}
  .price-card .trust{display:flex;justify-content:center;gap:16px;margin-top:16px;opacity:.8;font-size:.78rem}
  .price-card .trust span{display:flex;align-items:center;gap:5px}

  /* ── Testimonials ── */
  .testimonials{margin-top:48px}
  .testimonials h2{font-weight:800;color:var(--text);font-size:1.5rem;text-align:center;margin-bottom:6px}
  .testimonials>p{text-align:center;color:var(--text-muted);margin-bottom:28px}
  .test-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
  @media(max-width:820px){.test-grid{grid-template-columns:1fr}}
  .test-card{background:var(--bg-card);border:1px solid var(--border-light);border-radius:16px;padding:22px;box-shadow:var(--shadow-xs)}
  .test-card .stars{color:#d97706;font-size:.85rem;margin-bottom:10px}
  .test-card p{color:var(--text);font-size:.9rem;line-height:1.6;margin-bottom:14px;font-style:italic}
  .test-card .author{display:flex;align-items:center;gap:10px}
  .test-card .author-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;display:grid;place-items:center;font-weight:700;font-size:.8rem}
  .test-card .author-name{font-weight:700;font-size:.85rem;color:var(--text)}
  .test-card .author-role{font-size:.78rem;color:var(--text-muted)}

  /* ── FAQ ── */
  .faq{margin-top:48px}
  .faq h2{font-weight:800;color:var(--text);font-size:1.5rem;text-align:center;margin-bottom:28px}
  .faq-item{background:var(--bg-card);border:1px solid var(--border-light);border-radius:14px;margin-bottom:12px;overflow:hidden}
  .faq-q{padding:16px 20px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;font-weight:700;color:var(--text);font-size:.95rem}
  .faq-q i{color:var(--text-muted);transition:.2s;font-size:.85rem}
  .faq-a{padding:0 20px 16px;color:var(--text-muted);font-size:.9rem;line-height:1.6;display:none}
  .faq-item.open .faq-a{display:block}
  .faq-item.open .faq-q i{transform:rotate(180deg)}
</style>

<div class="pro-wrap">
  <?php if ($success): ?>
    <div class="ok-banner"><i class="fas fa-circle-check fa-lg"></i> Welcome to NovaHire Pro! Your premium features are now unlocked.</div>
  <?php endif; ?>

  <?php if ($is_pro): ?>
    <!-- PRO Active State -->
    <div class="pro-hero">
      <div class="pro-crown"><i class="fas fa-crown"></i></div>
      <h1>You're on <span>Pro</span></h1>
      <p>Enjoy unlimited access to every premium feature across the platform.</p>
      <div style="margin-top:24px">
        <span class="active-badge"><i class="fas fa-circle-check"></i> Pro active until <?= date('M j, Y', strtotime($sub['expires_at'])) ?></span>
      </div>
      <div style="margin-top:20px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a href="ai_hub.php" class="btn-upgrade" style="width:auto;padding:12px 28px;border-radius:12px"><i class="fas fa-robot mr-2"></i>Go to AI Center</a>
        <a href="seeker_dashboard.php" style="display:inline-flex;align-items:center;gap:6px;padding:12px 24px;border-radius:12px;border:1.5px solid var(--border);color:var(--text);font-weight:700;font-size:.95rem;text-decoration:none;transition:.2s">Back to Dashboard</a>
      </div>
    </div>

  <?php else: ?>
    <!-- Upgrade Page -->
    <div class="pro-hero">
      <div class="pro-crown"><i class="fas fa-crown"></i></div>
      <h1>Unlock Your Full <span>Potential</span></h1>
      <p>Get unlimited AI tools, unlimited job applications, priority ranking, and free certificates — everything you need to land your dream job faster.</p>
      <div class="pro-stats">
        <div class="pro-stat"><strong>3x</strong><span>More interviews</span></div>
        <div class="pro-stat"><strong>85%</strong><span>Success rate</span></div>
        <div class="pro-stat"><strong>10K+</strong><span>Pro members</span></div>
      </div>
    </div>

    <!-- Feature Comparison -->
    <div class="compare-section">
      <h2>Free vs Pro</h2>
      <p>See exactly what you get with each plan</p>
      <table class="compare-table">
        <thead>
          <tr>
            <th>Feature</th>
            <th>Free</th>
            <th><i class="fas fa-crown mr-1"></i> Pro</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Browse & search jobs</td>
            <td><i class="fas fa-check check"></i> Unlimited</td>
            <td><i class="fas fa-check check"></i> Unlimited</td>
          </tr>
          <tr>
            <td>Job applications</td>
            <td>10 / month</td>
            <td class="unlimited"><i class="fas fa-infinity"></i> Unlimited</td>
          </tr>
          <tr>
            <td>AI Resume Analyzer</td>
            <td><i class="fas fa-lock cross"></i> Locked</td>
            <td class="unlimited"><i class="fas fa-infinity"></i> Unlimited</td>
          </tr>
          <tr>
            <td>AI Mock Interview</td>
            <td><i class="fas fa-lock cross"></i> Locked</td>
            <td class="unlimited"><i class="fas fa-infinity"></i> Unlimited</td>
          </tr>
          <tr>
            <td>AI Cover Letter Generator</td>
            <td><i class="fas fa-lock cross"></i> Locked</td>
            <td class="unlimited"><i class="fas fa-infinity"></i> Unlimited</td>
          </tr>
          <tr>
            <td>AI Career Assistant</td>
            <td><i class="fas fa-lock cross"></i> Locked</td>
            <td class="unlimited"><i class="fas fa-infinity"></i> 24/7 Access</td>
          </tr>
          <tr>
            <td>Skill Gap Analyzer</td>
            <td><i class="fas fa-lock cross"></i> Locked</td>
            <td class="unlimited"><i class="fas fa-infinity"></i> Unlimited</td>
          </tr>
          <tr>
            <td>Career Path Explorer</td>
            <td><i class="fas fa-lock cross"></i> Locked</td>
            <td class="unlimited"><i class="fas fa-infinity"></i> Unlimited</td>
          </tr>
          <tr>
            <td>AI Job Recommendations</td>
            <td><i class="fas fa-lock cross"></i> Locked</td>
            <td class="unlimited"><i class="fas fa-infinity"></i> Smart matches</td>
          </tr>
          <tr>
            <td>Resume Builder</td>
            <td><i class="fas fa-lock cross"></i> Locked</td>
            <td class="unlimited"><i class="fas fa-infinity"></i> All templates</td>
          </tr>
          <tr>
            <td>Verified Certificates</td>
            <td>৳299 each</td>
            <td class="unlimited"><i class="fas fa-check check"></i> Free</td>
          </tr>
          <tr>
            <td>Employer visibility</td>
            <td>Standard ranking</td>
            <td class="unlimited"><i class="fas fa-arrow-up check"></i> Priority ranking</td>
          </tr>
          <tr>
            <td>Pro badge</td>
            <td><i class="fas fa-times cross"></i></td>
            <td><i class="fas fa-check check"></i></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pricing + Features -->
    <div class="pro-grid">
      <div class="pro-features-card">
        <h5 style="font-weight:700;color:var(--text);margin-bottom:18px;font-size:1.1rem">Everything in Pro</h5>
        <?php
        $feat_list = [
          ['icon' => 'fa-infinity', 'color' => '#1a56db', 'bg' => 'rgba(26,86,219,.12)', 'label' => 'Unlimited AI Tools', 'desc' => 'Resume analyzer, mock interviews, cover letters, career coach — all unlocked.'],
          ['icon' => 'fa-paper-plane', 'color' => '#2563eb', 'bg' => 'rgba(37,99,235,.12)', 'label' => 'Unlimited Applications', 'desc' => 'Apply to as many jobs as you want without monthly limits.'],
          ['icon' => 'fa-arrow-trend-up', 'color' => '#059669', 'bg' => 'rgba(5,150,105,.12)', 'label' => 'Priority Ranking', 'desc' => 'Your profile appears at the top when employers search talent.'],
          ['icon' => 'fa-certificate', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,.12)', 'label' => 'Free Certificates', 'desc' => 'Earn verified, shareable skill certificates at no extra cost.'],
          ['icon' => 'fa-id-badge', 'color' => '#ec4899', 'bg' => 'rgba(236,72,153,.12)', 'label' => 'Pro Badge', 'desc' => 'Stand out with a verified Pro badge on your profile.'],
          ['icon' => 'fa-headset', 'color' => '#06b6d4', 'bg' => 'rgba(6,182,212,.12)', 'label' => 'Priority Support', 'desc' => 'Get faster responses from the NovaHire support team.'],
        ];
        foreach ($feat_list as $f): ?>
        <div class="feat">
          <div class="feat-icon" style="background:<?= $f['bg'] ?>;color:<?= $f['color'] ?>"><i class="fas <?= $f['icon'] ?>"></i></div>
          <div>
            <span><?= $f['label'] ?></span>
            <small><?= $f['desc'] ?></small>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="price-card">
        <div class="price-card-inner">
          <div style="margin-bottom:10px"><span style="background:rgba(255,255,255,.18);padding:5px 14px;border-radius:99px;font-size:.78rem;font-weight:700">MOST POPULAR</span></div>
          <div class="amt"><?= nh_price($pro_price) ?><small>/mo</small></div>
          <p class="cadence">30-day membership · cancel anytime</p>
          <a href="<?= BASE_URL ?>/api/checkout.php?purpose=pro_subscription" class="btn-upgrade"><i class="fas fa-crown mr-2"></i>Upgrade to Pro</a>
          <div class="trust">
            <span><i class="fas fa-shield-halved"></i> Secure</span>
            <span><i class="fas fa-lock"></i> Encrypted</span>
            <span><i class="fas fa-rotate-left"></i> Cancel anytime</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Testimonials -->
    <div class="testimonials">
      <h2>Loved by job seekers</h2>
      <p>See what Pro members say about their experience</p>
      <div class="test-grid">
        <div class="test-card">
          <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
          <p>"The AI mock interview tool completely changed how I prepare. I got callbacks from 3 companies in my first week!"</p>
          <div class="author">
            <div class="author-avatar">R</div>
            <div>
              <div class="author-name">Rafiq Ahmed</div>
              <div class="author-role">Software Engineer</div>
            </div>
          </div>
        </div>
        <div class="test-card">
          <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
          <p>"The cover letter generator saved me hours. Every letter is tailored to the specific job — employers noticed the difference."</p>
          <div class="author">
            <div class="author-avatar">S</div>
            <div>
              <div class="author-name">Sabrina Islam</div>
              <div class="author-role">Marketing Manager</div>
            </div>
          </div>
        </div>
        <div class="test-card">
          <div class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
          <p>"Priority ranking made a huge difference. I started getting noticed by top companies within days of upgrading."</p>
          <div class="author">
            <div class="author-avatar">T</div>
            <div>
              <div class="author-name">Tanvir Hossain</div>
              <div class="author-role">Data Analyst</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- FAQ -->
    <div class="faq">
      <h2>Frequently Asked Questions</h2>
      <div class="faq-item" onclick="this.classList.toggle('open')">
        <div class="faq-q">Can I cancel my subscription? <i class="fas fa-chevron-down"></i></div>
        <div class="faq-a">Yes, you can cancel anytime. Your Pro access continues until the end of your current billing period.</div>
      </div>
      <div class="faq-item" onclick="this.classList.toggle('open')">
        <div class="faq-q">What payment methods do you accept? <i class="fas fa-chevron-down"></i></div>
        <div class="faq-a">We accept credit/debit cards, bKash, and Nagad through our secure payment partner SSLCommerz.</div>
      </div>
      <div class="faq-item" onclick="this.classList.toggle('open')">
        <div class="faq-q">Do I get a refund if I'm not satisfied? <i class="fas fa-chevron-down"></i></div>
        <div class="faq-a">We offer a 7-day money-back guarantee. If you're not happy with Pro, contact us for a full refund.</div>
      </div>
      <div class="faq-item" onclick="this.classList.toggle('open')">
        <div class="faq-q">What happens to my data if I downgrade? <i class="fas fa-chevron-down"></i></div>
        <div class="faq-a">All your data, applications, and profile remain intact. You simply lose access to premium features.</div>
      </div>
    </div>
  <?php endif; ?>
</div>
