<?php
/**
 * NovaHire — Admin Revenue Dashboard
 * ---------------------------------------------------------------------------
 * The business story in one screen: platform revenue by stream, recurring
 * revenue (MRR), marketplace GMV, health of each marketplace, and the live
 * transaction ledger. Reads the unified `payments` table plus the per-stream
 * helpers (sessions, certificates, placements).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['admin_username'])) { header('Location: admin_login.php'); exit(); }

$pricing = nh_pricing();

/* ── Revenue by stream from the unified payments ledger ────────────────────── */
$pay_sum = ['pro_subscription'=>0.0,'subscription'=>0.0,'featured_job'=>0.0,'certificate'=>0.0,'session'=>0.0];
$pay_cnt = ['pro_subscription'=>0,'subscription'=>0,'featured_job'=>0,'certificate'=>0,'session'=>0];
$tx_total = 0;
if (nh_table_exists($con, 'payments')) {
    $r = @mysqli_query($con, "SELECT purpose, COUNT(*) c, COALESCE(SUM(amount),0) s FROM payments WHERE status='completed' GROUP BY purpose");
    if ($r) while ($row = mysqli_fetch_assoc($r)) {
        if (isset($pay_sum[$row['purpose']])) { $pay_sum[$row['purpose']] = (float)$row['s']; $pay_cnt[$row['purpose']] = (int)$row['c']; }
        $tx_total += (int)$row['c'];
    }
}

$sess = nh_session_revenue($con);      // bookings, gross, commission (platform take = commission)
$cert = nh_certificate_revenue($con);  // issued, paid_count, revenue
$plc  = nh_placement_stats($con);      // total, fee_pending, fee_paid, revenue

/* Platform take per stream (what NovaHire actually keeps) */
$streams = [
    ['key'=>'pro',      'name'=>'NovaHire Pro',       'sub'=>'Job-seeker memberships',   'amt'=>$pay_sum['pro_subscription'], 'cnt'=>$pay_cnt['pro_subscription'], 'icon'=>'fa-crown',          'c1'=>'#d97706','c2'=>'#f97316'],
    ['key'=>'employer', 'name'=>'Employer Plans',     'sub'=>'Company subscriptions',    'amt'=>$pay_sum['subscription'],     'cnt'=>$pay_cnt['subscription'],     'icon'=>'fa-building',       'c1'=>'#3b82f6','c2'=>'#06b6d4'],
    ['key'=>'featured', 'name'=>'Featured Jobs',      'sub'=>'Job-post boosts',          'amt'=>$pay_sum['featured_job'],     'cnt'=>$pay_cnt['featured_job'],     'icon'=>'fa-bolt',           'c1'=>'#0ea5e9','c2'=>'#06b6d4'],
    ['key'=>'cert',     'name'=>'Certificates',       'sub'=>'Verified credentials',     'amt'=>(float)$cert['revenue'],      'cnt'=>(int)$cert['paid_count'],     'icon'=>'fa-award',          'c1'=>'#059669','c2'=>'#059669'],
    ['key'=>'mentor',   'name'=>'Mentor Commission',  'sub'=>$pricing['session_commission_pct'].'% of each session', 'amt'=>(float)$sess['commission'], 'cnt'=>(int)$sess['bookings'], 'icon'=>'fa-chalkboard-user','c1'=>'#ec4899','c2'=>'#db2777'],
    ['key'=>'placement','name'=>'Placement Fees',     'sub'=>'Success fee per hire',     'amt'=>(float)$plc['revenue'],       'cnt'=>(int)$plc['fee_paid'],        'icon'=>'fa-handshake',      'c1'=>'#14b8a6','c2'=>'#0d9488'],
];
$total_rev = array_sum(array_column($streams, 'amt'));
$max_amt = max(1, max(array_column($streams, 'amt')));

/* GMV = total value flowing through the platform (session GROSS, not just commission) */
$gmv = $pay_sum['pro_subscription'] + $pay_sum['subscription'] + $pay_sum['featured_job'] + (float)$cert['revenue'] + (float)$sess['gross'] + (float)$plc['revenue'];

/* ── MRR (monthly recurring revenue) ───────────────────────────────────────── */
$active_pro = 0;
if (nh_table_exists($con, 'user_subscriptions')) {
    $r = @mysqli_query($con, "SELECT COUNT(*) c FROM user_subscriptions WHERE status='active' AND expires_at > NOW()");
    if ($r && ($row = mysqli_fetch_assoc($r))) $active_pro = (int)$row['c'];
}
$mrr = $active_pro * $pricing['pro_price'];
$plans = get_subscription_plans();
if (nh_table_exists($con, 'company_subscriptions')) {
    $r = @mysqli_query($con, "SELECT plan_type, COUNT(*) c FROM company_subscriptions WHERE status='active' AND expires_at > NOW() GROUP BY plan_type");
    if ($r) while ($row = mysqli_fetch_assoc($r)) {
        $pt = $row['plan_type'];
        if (isset($plans[$pt]) && $plans[$pt]['price'] > 0) {
            $monthly = $plans[$pt]['price'] * 30 / max(1, (int)$plans[$pt]['duration']);
            $mrr += $monthly * (int)$row['c'];
        }
    }
}

/* ── Marketplace health ────────────────────────────────────────────────────── */
$mentor_approved = 0; $mentor_pending = 0;
$r = @mysqli_query($con, "SELECT status, COUNT(*) c FROM mentors GROUP BY status");
if ($r) while ($row = mysqli_fetch_assoc($r)) { if ($row['status']==='approved') $mentor_approved=(int)$row['c']; if ($row['status']==='pending') $mentor_pending=(int)$row['c']; }

/* ── Recent transactions ───────────────────────────────────────────────────── */
$purpose_label = ['pro_subscription'=>'NovaHire Pro','subscription'=>'Employer Plan','featured_job'=>'Featured Job','certificate'=>'Certificate','session'=>'Mentor Session','placement_fee'=>'Placement Fee'];
$tx = [];
if (nh_table_exists($con, 'payments')) {
    $r = @mysqli_query($con, "SELECT payment_id, payer_type, purpose, amount, status, created_at FROM payments WHERE status='completed' ORDER BY created_at DESC LIMIT 12");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $tx[] = $row;
}

include 'header.php';
?>
<style>
  .rv-wrap{padding:0 0 50px}
  .rv-hero{margin-top:-72px;padding:96px 0 92px;background:linear-gradient(120deg,#0f766e,#0d9488 45%,#059669 120%);position:relative;overflow:hidden}
  .rv-hero::before{content:'';position:absolute;top:-120px;right:-60px;width:380px;height:380px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.14),transparent 70%)}
  .rv-hero h1{color:#fff;font-size:2rem;font-weight:800;letter-spacing:-.5px;margin:0 0 6px}
  .rv-hero p{color:rgba(255,255,255,.85);margin:0}
  .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:-32px;position:relative;z-index:2}
  @media(max-width:820px){.kpis{grid-template-columns:1fr 1fr}}
  .kpi{background:var(--bg-card);border:1px solid var(--border-light);border-radius:16px;padding:20px;box-shadow:var(--shadow-md);position:relative;z-index:2}
  .kpi .ic{width:40px;height:40px;border-radius:11px;display:grid;place-items:center;font-size:1rem;color:#fff;margin-bottom:12px}
  .kpi .v{font-weight:800;color:var(--text);font-size:1.6rem;letter-spacing:-.5px;line-height:1}
  .kpi .l{color:var(--text-muted);font-size:.82rem;font-weight:600;margin-top:5px}
  .kpi .sub{color:var(--text-light);font-size:.74rem;margin-top:3px}
  .grid2{display:grid;grid-template-columns:1.5fr 1fr;gap:20px;margin-top:22px;position:relative;z-index:1}
  @media(max-width:900px){.grid2{grid-template-columns:1fr}}
  .card{background:var(--bg-card);border:1px solid var(--border-light);border-radius:16px;padding:24px;box-shadow:var(--shadow-sm)}
  .card h3{font-weight:800;color:var(--text);font-size:1.1rem;margin:0 0 4px;display:flex;align-items:center;gap:9px}
  .card .hint{color:var(--text-muted);font-size:.84rem;margin-bottom:20px}
  .stream{margin-bottom:18px}
  .stream .st-top{display:flex;align-items:center;gap:12px;margin-bottom:8px}
  .stream .st-ic{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;color:#fff;font-size:.95rem;flex-shrink:0}
  .stream .st-nm{font-weight:700;color:var(--text)}
  .stream .st-sub{color:var(--text-muted);font-size:.76rem}
  .stream .st-amt{margin-left:auto;text-align:right}
  .stream .st-amt b{font-weight:800;color:var(--text);font-size:1.02rem}
  .stream .st-amt span{display:block;color:var(--text-light);font-size:.72rem}
  .stream .bar{height:9px;border-radius:99px;background:var(--border-light);overflow:hidden}
  .stream .bar i{display:block;height:100%;border-radius:99px}
  .health{display:flex;flex-direction:column;gap:12px}
  .hitem{display:flex;align-items:center;gap:13px;padding:13px 0;border-bottom:1px solid var(--border-light)}
  .hitem:last-child{border-bottom:none}
  .hitem .hic{width:40px;height:40px;border-radius:11px;display:grid;place-items:center;font-size:1rem;flex-shrink:0}
  .hitem .hv{font-weight:800;color:var(--text);font-size:1.15rem}
  .hitem .hl{color:var(--text-muted);font-size:.82rem}
  .hitem .tag{margin-left:auto;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:99px}
  .tx-table{width:100%;border-collapse:collapse;margin-top:6px}
  .tx-table th{text-align:left;color:var(--text-muted);font-size:.74rem;text-transform:uppercase;letter-spacing:.5px;font-weight:700;padding:8px 10px;border-bottom:1px solid var(--border-light)}
  .tx-table td{padding:11px 10px;border-bottom:1px solid var(--border-light);color:var(--text);font-size:.86rem}
  .tx-table tr:last-child td{border-bottom:none}
  .tx-pill{font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:99px;background:var(--bg-hover);color:var(--text-muted)}
  .tx-amt{font-weight:800;color:#059669}
  .empty-tx{text-align:center;padding:34px;color:var(--text-muted)}
  .mono{font-family:monospace;color:var(--text-light);font-size:.78rem}
</style>

<div class="rv-wrap">
  <div class="rv-hero">
    <div class="container"><h1><i class="fas fa-chart-line mr-2"></i>Revenue</h1><p>Six revenue streams, one marketplace. Here's the health of the NovaHire business.</p></div>
  </div>

  <div class="container">
    <div class="kpis">
      <div class="kpi">
        <div class="ic" style="background:linear-gradient(135deg,#059669,#059669)"><i class="fas fa-sack-dollar"></i></div>
        <div class="v"><?= nh_price($total_rev) ?></div>
        <div class="l">Total platform revenue</div>
        <div class="sub"><?= $tx_total ?> completed transactions</div>
      </div>
      <div class="kpi">
        <div class="ic" style="background:linear-gradient(135deg,#3b82f6,#06b6d4)"><i class="fas fa-arrows-rotate"></i></div>
        <div class="v"><?= nh_price($mrr) ?></div>
        <div class="l">Monthly recurring revenue</div>
        <div class="sub"><?= $active_pro ?> active Pro member<?= $active_pro==1?'':'s' ?></div>
      </div>
      <div class="kpi">
        <div class="ic" style="background:linear-gradient(135deg,#0ea5e9,#06b6d4)"><i class="fas fa-money-bill-trend-up"></i></div>
        <div class="v"><?= nh_price($gmv) ?></div>
        <div class="l">Marketplace GMV</div>
        <div class="sub">Total value processed</div>
      </div>
      <div class="kpi">
        <div class="ic" style="background:linear-gradient(135deg,#d97706,#f97316)"><i class="fas fa-handshake"></i></div>
        <div class="v"><?= (int)$plc['total'] ?></div>
        <div class="l">Successful placements</div>
        <div class="sub"><?= $plc['fee_pending'] ?> fee<?= $plc['fee_pending']==1?'':'s' ?> pending</div>
      </div>
    </div>

    <div class="grid2">
      <div class="card">
        <h3><i class="fas fa-layer-group" style="color:#0d9488"></i>Revenue by stream</h3>
        <div class="hint">What NovaHire keeps from each part of the marketplace.</div>
        <?php foreach ($streams as $s): $pct = $total_rev>0 ? round($s['amt']/$total_rev*100) : 0; $barpct = round($s['amt']/$max_amt*100); ?>
          <div class="stream">
            <div class="st-top">
              <div class="st-ic" style="background:linear-gradient(135deg,<?= $s['c1'] ?>,<?= $s['c2'] ?>)"><i class="fas <?= $s['icon'] ?>"></i></div>
              <div><div class="st-nm"><?= htmlspecialchars($s['name']) ?></div><div class="st-sub"><?= htmlspecialchars($s['sub']) ?> · <?= (int)$s['cnt'] ?> sale<?= $s['cnt']==1?'':'s' ?></div></div>
              <div class="st-amt"><b><?= nh_price($s['amt']) ?></b><span><?= $pct ?>% of revenue</span></div>
            </div>
            <div class="bar"><i style="width:<?= max(2,$barpct) ?>%;background:linear-gradient(90deg,<?= $s['c1'] ?>,<?= $s['c2'] ?>)"></i></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="card">
        <h3><i class="fas fa-heart-pulse" style="color:#ec4899"></i>Marketplace health</h3>
        <div class="hint">The engines behind the revenue.</div>
        <div class="health">
          <div class="hitem">
            <div class="hic" style="background:rgba(236,72,153,.12);color:#db2777"><i class="fas fa-chalkboard-user"></i></div>
            <div><div class="hv"><?= $mentor_approved ?></div><div class="hl">Active mentors</div></div>
            <?php if ($mentor_pending): ?><a href="mentors.php?status=pending" class="tag" style="background:rgba(217,119,6,.14);color:#b45309;text-decoration:none"><?= $mentor_pending ?> pending</a><?php endif; ?>
          </div>
          <div class="hitem">
            <div class="hic" style="background:rgba(59,130,246,.12);color:#3b82f6"><i class="fas fa-video"></i></div>
            <div><div class="hv"><?= (int)$sess['bookings'] ?></div><div class="hl">Sessions booked</div></div>
            <span class="tag" style="background:rgba(5,150,105,.12);color:#059669"><?= nh_price($sess['gross']) ?> GMV</span>
          </div>
          <div class="hitem">
            <div class="hic" style="background:rgba(5,150,105,.12);color:#059669"><i class="fas fa-award"></i></div>
            <div><div class="hv"><?= (int)$cert['issued'] ?></div><div class="hl">Certificates issued</div></div>
            <span class="tag" style="background:rgba(5,150,105,.12);color:#059669"><?= (int)$cert['paid_count'] ?> paid</span>
          </div>
          <div class="hitem">
            <div class="hic" style="background:rgba(20,184,166,.12);color:#0d9488"><i class="fas fa-handshake"></i></div>
            <div><div class="hv"><?= (int)$plc['total'] ?></div><div class="hl">Hires made</div></div>
            <span class="tag" style="background:rgba(20,184,166,.12);color:#0d9488"><?= nh_price($plc['revenue']) ?> earned</span>
          </div>
        </div>
      </div>
    </div>

    <div class="card" style="margin-top:22px">
      <h3><i class="fas fa-receipt" style="color:#3b82f6"></i>Recent transactions</h3>
      <div class="hint">Latest completed payments across every stream.</div>
      <?php if (empty($tx)): ?>
        <div class="empty-tx"><i class="fas fa-receipt fa-2x mb-2" style="opacity:.4"></i><p>No transactions yet. They'll appear here as users pay for Pro, sessions, certificates and boosts.</p></div>
      <?php else: ?>
        <table class="tx-table">
          <thead><tr><th>Transaction</th><th>Type</th><th>Payer</th><th>Date</th><th style="text-align:right">Amount</th></tr></thead>
          <tbody>
            <?php foreach ($tx as $t): ?>
              <tr>
                <td class="mono"><?= htmlspecialchars($t['payment_id']) ?></td>
                <td><span class="tx-pill"><?= htmlspecialchars($purpose_label[$t['purpose']] ?? ucfirst($t['purpose'])) ?></span></td>
                <td><?= $t['payer_type']==='company' ? '<i class="fas fa-building mr-1" style="color:var(--text-light)"></i>Company' : '<i class="fas fa-user mr-1" style="color:var(--text-light)"></i>Seeker' ?></td>
                <td><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                <td style="text-align:right" class="tx-amt"><?= nh_price($t['amount']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>
