<?php
include('templates/header.php');

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'airlines';

$conn = new mysqli($host, $user, $pass, $db);

$quizzes = [];
$totalStudents = 0;
$sections = []; // [ { section: 'Section A', count: 12 }, ... ]
$dbError = null;

if ($conn->connect_error) {
    // We'll still render the page; JS will show fallback content.
    $dbError = $conn->connect_error;
    $quizzes = [];
    $totalStudents = 0;
    $totalSubmitted = 0;
  $dbError = $conn->connect_error;
} else {
    $dbError = null;
    // set proper charset to avoid surprises with utf8 data
    $conn->set_charset('utf8mb4');

    // Fetch quizzes along with item count and earliest deadline (if any)
    // Assumes quizzes table has `id`, `title`, `quiz_code`, `created_at`
    // and quiz_items has `quiz_id` and `deadline`.
    $quizzes = [];
    $sql = "
      SELECT
        q.id,
        q.title,
        q.quiz_code AS code,
        q.created_at,
        COALESCE(MIN(qi.deadline), '') AS deadline,
        COUNT(qi.id) AS num_items
      FROM quizzes q
      LEFT JOIN quiz_items qi ON qi.quiz_id = q.id
      GROUP BY q.id
      ORDER BY q.id DESC
    ";
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) $quizzes[] = $r;
        $res->free();
    } else {
        error_log('Quizzes query failed: ' . $conn->error);
    }

  // total students (for fallback / all sections)
  $r = $conn->query("SELECT COUNT(*) AS c FROM students");
  if ($r) {
    $row = $r->fetch_assoc();
    $totalStudents = intval($row['c']);
    $r->free();
  }

  // fetch sections and counts (group by section column)
  // If your students table uses a different column name for section, replace `section` below.
  $sections = [];
  $sqlSec = "SELECT COALESCE(NULLIF(TRIM(section),''),'(No section)') AS section, COUNT(*) AS c
             FROM students
             GROUP BY COALESCE(NULLIF(TRIM(section),''),'(No section)')
             ORDER BY section ASC";
  if ($resS = $conn->query($sqlSec)) {
    while ($r = $resS->fetch_assoc()) {
      $sections[] = ['section' => $r['section'], 'count' => (int)$r['c']];
    }
    $resS->free();
  }

  // per-quiz submitted counts (total submitted across all sections)
  if (count($quizzes)) {
    $ids = array_column($quizzes, 'id');
    $idList = implode(',', array_map('intval', $ids));
    $sql2 = "SELECT quiz_id, COUNT(DISTINCT student_id) AS submitted_count FROM submissions WHERE quiz_id IN ($idList) GROUP BY quiz_id";
    if ($res2 = $conn->query($sql2)) {
      $map = [];
      while ($r2 = $res2->fetch_assoc()) {
        $map[intval($r2['quiz_id'])] = intval($r2['submitted_count']);
      }
      foreach ($quizzes as &$q) {
        $qid = intval($q['id']);
        $q['submitted_count'] = isset($map[$qid]) ? $map[$qid] : 0;
      }
      unset($q);
      $res2->free();
    } else {
      foreach ($quizzes as &$q) $q['submitted_count'] = 0;
      unset($q);
    }
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin — Quizzes</title>
  <style>
  #adminApp * { box-sizing: border-box; }
  #adminApp {
    --blue:#0b5ed7;
    --muted:#6b7280;
    --card-bg:#ffffff;
    --soft-shadow:0 6px 18px rgba(11,94,215,0.04);
    --accent-gray:#e6eefc;
    --pill-gray:#cbd5e1;
    font-family: Inter, Roboto, Arial, sans-serif;
    background: linear-gradient(180deg,#fbfdff,#f2f6ff);
    padding: 18px;
  }
  #adminApp .page-wrap{ max-width:1400px; margin:0 auto; }
  .layout { display:grid; grid-template-columns:200px 1fr 360px; gap:18px; align-items:start; }
  @media (max-width:980px) { .layout{ grid-template-columns: 1fr; } }
  .left-col { padding:6px 0; }
  .left-create { display:inline-block; padding:10px 14px; background:var(--blue); color:#fff; border-radius:10px; text-decoration:none; font-weight:700; box-shadow: var(--soft-shadow); font-size:13px; transition: transform .12s ease, box-shadow .12s ease; }
  .left-create:hover{ transform: translateY(-3px); box-shadow: 0 12px 30px rgba(11,94,215,0.08); }
  .quiz-list { padding:6px 0; }
  .frame-card { background: var(--card-bg); border-radius:12px; padding:14px; margin-bottom:12px; display:flex; gap:12px; align-items:center; justify-content:space-between; box-shadow: var(--soft-shadow); border:1px solid rgba(15,23,36,0.03); transition: transform .12s ease, box-shadow .12s ease; }
  .frame-card:hover{ transform: translateY(-4px); box-shadow: 0 12px 36px rgba(11,94,215,0.06); cursor:pointer; }
  .quiz-left { display:flex; gap:12px; align-items:center; flex:1; min-width:0; }
  .quiz-chip { flex-shrink:0; width:40px; height:40px; border-radius:8px; background:#eef6ff; display:flex; align-items:center; justify-content:center; color:var(--blue); font-weight:800; font-size:11px; }
  .quiz-info { overflow:hidden; }
  .quiz-title { font-weight:800; font-size:15px; margin:0 0 3px 0; white-space:nowrap; text-overflow:ellipsis; overflow:hidden; }
  .quiz-meta { color:var(--muted); font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .quiz-actions { display:flex; gap:8px; align-items:center; margin-left:12px; flex-shrink:0; }
  .quiz-actions a { text-decoration:none; color:var(--muted); font-weight:700; padding:6px 10px; border-radius:8px; border:1px solid rgba(0,0,0,0.04); background:transparent; transition: transform .12s ease, box-shadow .12s ease; }
  .quiz-actions a.open{ color:var(--blue); border-color: rgba(11,94,215,0.08); background: rgba(11,94,215,0.03); }
  .quiz-actions a:hover{ transform: translateY(-3px); box-shadow: 0 8px 24px rgba(11,94,215,0.06); }
  .stats-panel { background: transparent; border-radius:12px; padding:0; }
  .stats-card { background: var(--card-bg); border-radius:12px; padding:14px; box-shadow:var(--soft-shadow); border:1px solid rgba(15,23,36,0.03); }
  .stats-header { display:flex; justify-content:space-between; align-items:flex-end; gap:12px; margin-bottom:8px; }
  .stats-title { margin:0; font-size:16px; font-weight:800; }
  .stats-sub { color:var(--muted); font-size:12px; margin-top:6px; font-weight:600; }
  .top-controls { display:flex; gap:8px; align-items:center; margin-bottom:10px; }
  .quiz-select { -webkit-appearance:none; -moz-appearance:none; appearance:none; padding:8px 10px; height:40px; font-weight:700; border-radius:8px; border:1px solid var(--accent-gray); background:#fff url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="14" height="9"><path fill="%230b5ed7" d="M7 9L0 0h14z"/></svg>') no-repeat calc(100% - 12px) center/10px; padding-right:36px; flex:1; font-size:13px; transition: box-shadow .12s ease, transform .12s ease; }
  .quiz-select:focus { box-shadow: 0 8px 20px rgba(11,94,215,0.06); transform: translateY(-2px); outline: none; }
  .section-select { -webkit-appearance:none; -moz-appearance:none; appearance:none; padding:8px 10px; height:40px; font-weight:700; border-radius:8px; border:1px solid var(--accent-gray); background:#fff url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="14" height="9"><path fill="%230b5ed7" d="M7 9L0 0h14z"/></svg>') no-repeat calc(100% - 12px) center/10px; padding-right:36px; font-size:13px; }
  .badge-pill { display:inline-flex; align-items:center; justify-content:center; min-width:42px; height:28px; padding:0 8px; border-radius:14px; background:#eef6ff; color:var(--blue); font-weight:800; font-size:13px; border:1px solid rgba(11,94,215,0.06); }
  .stats-chart-wrap { display:flex; justify-content:center; align-items:center; height:220px; padding:8px 0; position:relative; }
  .arrow-row { display:flex; gap:12px; justify-content:center; align-items:center; margin-top:10px; }
  .arrow-btn { width:44px; height:44px; border-radius:22px; display:inline-flex; align-items:center; justify-content:center; background:#fff; border:1px solid rgba(11,94,215,0.08); box-shadow: var(--soft-shadow); cursor:pointer; transition: transform .14s ease, box-shadow .14s ease; }
  .arrow-btn:hover{ transform: translateY(-4px) scale(1.03); box-shadow: 0 12px 30px rgba(11,94,215,0.08); }
  .arrow-btn:active{ transform: translateY(0.5px) scale(.99); }
  .arrow-svg { width:16px; height:16px; fill: var(--blue); }
  .btn-animated { transition: transform .12s ease, box-shadow .12s ease; }
  .btn-animated:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(11,94,215,0.06); }
  .legend-row { display:flex; gap:12px; align-items:center; justify-content:center; margin-top:12px; }
  .legend-pill { display:flex; gap:8px; align-items:center; font-weight:700; color:#111; }
  .legend-swatch { width:14px; height:14px; border-radius:4px; display:inline-block; }
  @media (max-width:640px) {
    .arrow-btn { width:40px; height:40px; border-radius:20px; }
    .quiz-chip{ width:36px; height:36px; font-size:10px; }
    .stats-chart-wrap { height:170px; }
  }
  </style>
</head>
<body>
<div id="adminApp">
  <div class="page-wrap">
    <div class="layout">
      <div class="left-col">
        <a class="left-create btn-animated" href="quizmaker.php">CREATE QUIZ</a>
      </div>
      <div>
        <div class="quiz-list">
          <div id="quizzesContainer">
            <?php if(isset($dbError)): ?>
              <div class="frame-card">DB error: <?php echo htmlspecialchars($dbError); ?> — falling back to demo list</div>
            <?php else: ?>
              <?php if(count($quizzes) === 0): ?>
                <div class="frame-card">No quizzes found. Click CREATE QUIZ to add one.</div>
              <?php else: ?>
                <?php foreach($quizzes as $q): ?>
                  <div class="frame-card" data-id="<?php echo (int)$q['id']; ?>">
                    <div class="quiz-left">
                      <div class="quiz-chip"><?php echo htmlspecialchars($q['code'] ?: 'Q'); ?></div>
                      <div class="quiz-info">
                        <div class="quiz-title"><?php echo htmlspecialchars($q['title']); ?></div>
                        <div class="quiz-meta">Deadline: <?php echo $q['deadline'] ? htmlspecialchars($q['deadline']) : '—'; ?> • Questions: <?php echo (int)$q['num_questions']; ?></div>
                      </div>
                    </div>
                    <div class="quiz-actions">
                      <a class="open btn-animated" href="submissions.php?id=<?php echo (int)$q['id']; ?>">Open</a>
                      <a class="edit btn-animated" href="quizmaker.php?id=<?php echo (int)$q['id']; ?>" title="Edit">✎</a>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div>
        <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
          <a href="Students.php" class="edit-students-btn btn-animated" style="padding:8px 10px;border-radius:8px;background:#eef6ff;color:var(--blue);text-decoration:none;font-weight:700;">EDIT STUDENTS</a>
        </div>
        <div class="stats-panel">
          <div class="stats-card">
            <div class="stats-header">
              <div>
                <div class="stats-title">STUDENTS WHO SUBMITTED</div>
                <div class="stats-sub">Select quiz to view its submissions; arrows below step through quizzes</div>
              </div>
              <div style="display:flex; gap:8px; align-items:center;">
                <select id="sectionSelect" class="section-select" aria-label="Select section">
                  <option value="__all__">All sections (<?php echo (int)$totalStudents; ?>)</option>
                </select>
                <div id="sectionCount" class="badge-pill"><?php echo (int)$totalStudents; ?></div>
              </div>
            </div>
            <div class="top-controls">
              <select id="quizDropdown" class="quiz-select" aria-label="Select quiz">
                <option value="" selected>— Select quiz —</option>
              </select>
              <div id="dropdownBadge" class="badge-pill">0</div>
              <a id="openSelected" class="btn-animated" style="display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:8px; border:1px solid rgba(11,94,215,0.08); background:transparent; color:var(--blue); font-weight:700; text-decoration:none;">Open</a>
            </div>
            <div id="selectedQuizName" style="font-weight:800; font-size:15px; margin-bottom:8px; min-height:20px;"></div>
            <div class="stats-chart-wrap">
              <canvas id="quizPieChart" width="300" height="300" aria-label="Submissions pie chart" role="img"></canvas>
            </div>
            <div class="arrow-row" role="group" aria-label="Previous Next quiz">
              <button id="prevArrow" class="arrow-btn" title="Previous quiz" aria-label="Previous">
                <svg class="arrow-svg" viewBox="0 0 24 24"><path d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
              </button>
              <button id="nextArrow" class="arrow-btn" title="Next quiz" aria-label="Next">
                <svg class="arrow-svg" viewBox="0 0 24 24"><path d="M8.59 16.59 13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>
              </button>
            </div>
            <div class="legend-row">
              <div class="legend-pill"><span class="legend-swatch" style="background:var(--blue)"></span> Submitted</div>
              <div class="legend-pill"><span class="legend-swatch" style="background:var(--pill-gray)"></span> Not submitted</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
  const allTotalStudents = <?php echo isset($totalStudents) ? (int)$totalStudents : 0; ?>;
  const quizzes = <?php foreach ($quizzes as &$qq) { if(!isset($qq['submitted_count'])) $qq['submitted_count'] = 0; } echo json_encode($quizzes); ?>;
  const sections = <?php echo json_encode($sections); ?>; // [{section, count}, ...]
  const dropdown = document.getElementById('quizDropdown');
  const openSelected = document.getElementById('openSelected');
  const selectedQuizName = document.getElementById('selectedQuizName');
  const prevArrow = document.getElementById('prevArrow');
  const nextArrow = document.getElementById('nextArrow');
  const dropdownBadge = document.getElementById('dropdownBadge');
  const sectionSelect = document.getElementById('sectionSelect');
  const sectionCountEl = document.getElementById('sectionCount');

  // populate sectionSelect
  sections.forEach(s => {
    const opt = document.createElement('option');
    opt.value = s.section;
    opt.textContent = s.section + ' (' + s.count + ')';
    opt.dataset.count = s.count;
    sectionSelect.appendChild(opt);
  });

  // when section changes, update the "sectionCount" pill and refresh the chart (use currently selected quiz)
  function getSelectedSectionCount(){
    const val = sectionSelect.value;
    if(!val || val === '__all__') return allTotalStudents;
    // find in sections
    const f = sections.find(x => x.section === val);
    return f ? f.count : allTotalStudents;
  }

  sectionSelect.addEventListener('change', function(){
    const c = getSelectedSectionCount();
    sectionCountEl.textContent = String(c);
    // refresh chart to recompute Not submitted for currently selected quiz
    if(dropdown.value){
      // trigger dropdown change logic by calling showQuizAtIndex for current quiz index
      const idx = findIndexById(dropdown.value);
      if(idx >= 0) showQuizAtIndex(idx);
    } else {
      // update pie with zero-submitted (no quiz selected)
      pieChart.data.datasets[0].data = [0, c];
      pieChart.update();
      dropdownBadge.textContent = '0';
      selectedQuizName.textContent = '';
      openSelected.dataset.qid = '';
    }
  });

  // populate quiz dropdown
  quizzes.forEach(q => {
    const opt = document.createElement('option');
    opt.value = q.id;
    opt.textContent = (q.code ? q.code + ' — ' : '') + q.title;
    dropdown.appendChild(opt);
  });

  const ctx = document.getElementById('quizPieChart').getContext('2d');
  const pieChart = new Chart(ctx, {
    type: 'pie',
    data: {
      labels: ['Submitted','Not submitted'],
      datasets: [{
        data: [0, allTotalStudents],
        backgroundColor: ['#0b5ed7', '#cbd5e1'],
        borderWidth: 0
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 700, easing: 'easeOutQuart' },
      plugins: {
        legend: { display:false },
        tooltip: { callbacks: { label: function(ctx){ return ctx.label + ': ' + ctx.parsed + ' students'; } } }
      }
    }
  });

  function findIndexById(id){ return quizzes.findIndex(q => String(q.id) === String(id)); }

  function showQuizAtIndex(idx){
    if(idx < 0 || idx >= quizzes.length) return;
    const q = quizzes[idx];
    const submitted = Number(q.submitted_count || 0);

    // compute notSubmitted relative to selected section's student count
    const sectionCount = getSelectedSectionCount();
    // Important: submitted counts in DB are not segmented by section in this implementation.
    // We will treat `submitted` as "students who submitted (all sections)". If you want submitted-by-section accuracy,
    // submissions query must be extended server-side to compute per-quiz per-section counts and supplied to JS.
    const notSubmitted = Math.max(0, sectionCount - submitted);

    pieChart.data.datasets[0].data = [submitted, notSubmitted];
    pieChart.update();

    dropdown.value = q.id;
    selectedQuizName.textContent = q.title || '';
    openSelected.dataset.qid = q.id;
    dropdownBadge.textContent = String(submitted);
    sectionCountEl.textContent = String(sectionCount);
  }

  dropdown.addEventListener('change', function(){
    const val = dropdown.value;
    if(!val) return;
    const idx = findIndexById(val);
    if(idx >= 0) showQuizAtIndex(idx);
  });

  prevArrow.addEventListener('click', function(){
    if(quizzes.length === 0) return;
    const cur = dropdown.value ? findIndexById(dropdown.value) : -1;
    const nextIdx = (cur <= 0) ? quizzes.length - 1 : cur - 1;
    showQuizAtIndex(nextIdx);
  });

  nextArrow.addEventListener('click', function(){
    if(quizzes.length === 0) return;
    const cur = dropdown.value ? findIndexById(dropdown.value) : -1;
    const nextIdx = (cur === -1 || cur >= quizzes.length - 1) ? 0 : cur + 1;
    showQuizAtIndex(nextIdx);
  });

  openSelected.addEventListener('click', function(e){
    const qid = this.dataset.qid || dropdown.value;
    if(!qid) return;
    window.location.href = 'submissions.php?id=' + qid + '&section=' + encodeURIComponent(sectionSelect.value || '__all__');
  });

  document.addEventListener('keydown', function(e){
    if(e.key === 'ArrowLeft') prevArrow.click();
    else if(e.key === 'ArrowRight') nextArrow.click();
  });

  // initialize: set section list first and default to All
  (function init(){
    const c = getSelectedSectionCount();
    sectionCountEl.textContent = String(c);
    if(quizzes.length > 0) showQuizAtIndex(0);
  })();

  // make quiz cards clickable to open Exam.php
  document.querySelectorAll('#adminApp #quizzesContainer .frame-card').forEach(card => {
    const id = card.dataset.id;
    if(id){
      card.addEventListener('click', (e) => {
        if(e.target.closest('.quiz-actions')) return;
        // open Exam with section param chosen
        const sec = encodeURIComponent(sectionSelect.value || '__all__');
        window.location.href = 'submissions.php?id=' + id + '&section=' + sec;
      });
    }
  });

})();
</script>
<?php include('templates/footer.php'); ?>
</body>
</html>